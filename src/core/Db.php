<?
declare (strict_types = 1);
namespace lim;
use PDO;
use PDOException;

class Db {
	public static $pdo;

	public static function init() {
		if (!self::$pdo) {
			$c = config('db.connections.mysql');

			$dsn = "mysql:host={$c['hostname']};dbname={$c['database']};port={$c['hostport']}";

			$option = [
				PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
				PDO::ATTR_CASE => PDO::CASE_NATURAL,
				PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
			];

			try {
				self::$pdo = new PDO($dsn, $c['username'], $c['password'], $option);
			} catch (PDOException $e) {
				die("连接数据库失败: " . $e->getMessage());
			}
			loger('db init');
		}

	}

	public static function transaction($call = null) {
		self::init();
		if ($call) {
			self::$pdo->beginTransaction();
			try {
				$call();
				self::$pdo->commit();
			} catch (PDOException $e) {
				loger($e->getMessage());
				self::$pdo->rollback();
			}
		} else {
			return self::$pdo->beginTransaction();
		}
	}

	//缓存数据库信息
	public static function schema() {
		self::init();
		$sql = "SELECT TABLE_NAME,COLUMN_NAME,ORDINAL_POSITION,IS_NULLABLE,DATA_TYPE,COLUMN_COMMENT FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'ly' ORDER BY TABLE_NAME ASC,ORDINAL_POSITION ASC ";
		$res = self::$pdo->query($sql)->fetchall();

		$config = [];
		foreach ($res as $k => $v) {
			$config[$v['TABLE_NAME']][$v['COLUMN_NAME']] = ['type' => $v['DATA_TYPE'], 'commit' => $v['COLUMN_COMMENT']];

		}
		$configContent = "<?php\nreturn " . str_replace(['{', '}', ':'], ['[', ']', '=>'], json_encode($config, 256)) . ";\n";
		file_put_contents(ROOT_PATH . 'config/model.php', $configContent);

		loger('缓存数据库schema成功');
	}

	public static function commit() {
		return self::$pdo->commit();
	}

	public static function rollback() {
		return self::$pdo->rollback();
	}

	public static function __callStatic($method, $argv) {
		self::init();
		$res = call_user_func_array([new QueryBuilder, $method], $argv);
		return $res;
	}
}

class QueryBuilder {

	private $option = [
		'debug' => false,

		'distinct' => '',
		'field' => '*',
		'table' => '',
		// 'force' => '',
		// 'join' => '',
		'where' => '1',
		'group' => '',
		// 'having' => '',
		// 'union' => '',
		'order' => '',
		'limit' => '',
		'lock' => '',

		'sql' => '',
		'execute' => [],
	];

	public function __call($method, $argv) {

		switch (strtolower($method)) {
		case 'debug':
			$this->option['debug'] = true;
			break;
		case 'table':
			$this->option['table'] = $argv[0];
			break;
		case 'field':
			$this->option['field'] = $argv[0];
			break;
		case 'limit':
			$this->option['limit'] = ' LIMIT ' . (isset($argv[1]) ? $argv[0] . ',' . $argv[1] : $argv[0]);
			break;
		case 'page':
			break;
		case 'join':
			break;
		case 'group':
			$this->option['group'] = ' GROUP BY ' . $argv[0];
			break;
		case 'union':
			break;
		case 'force':
			break;
		case 'having':
			break;
		case 'distinct':
			$this->option['distinct'] = ' DISTINCT';
			break;
		case 'where':
			$this->parseWhere('AND', ...$argv);
			break;
		case 'orwhere':
			$this->parseWhere('OR', ...$argv);
			break;
		case 'sql':
			$this->option['sql'] = $argv[0];
			break;
		//----------------
		case 'sum';
			$this->option['sql'] = "SELECT SUM({$argv[0]}) as result FROM {$this->option['table']} WHERE {$this->option['where']}";
			return $this->execute()->fetch()['result'];
		case 'count':
			$this->option['sql'] = "SELECT COUNT({$argv[0]}) as result FROM {$this->option['table']} WHERE {$this->option['where']}";
			return $this->execute()->fetch()['result'];
		default:
			// code...
			break;
		}
		return $this;
	}

	//-------------------------

	public function insert($data) {
		$columns = '`' . implode('`,`', array_keys($data)) . '`';
		$placeholders = implode(',', array_fill(0, count($data), '?'));
		$sql = "INSERT INTO `{$this->option['table']}` ({$columns}) VALUES ({$placeholders})";
		$val = array_values($data);

		$stmt = Db::$pdo->prepare($sql);
		return $stmt->execute($val);
	}

	public function insertAll($dataArray, $debug = false) {
		$columns = '`' . implode('`,`', array_keys($dataArray[0])) . '`';
		$placeholders = implode(',', array_fill(0, count($dataArray[0]), '?'));
		$sql = "INSERT INTO {$this->tableName} ({$columns}) VALUES ";
		$values = [];
		foreach ($dataArray as $data) {
			$values[] = "({$placeholders})";
		}
		$sql .= implode(', ', $values);

		$val = [];
		foreach ($dataArray as $data) {
			$val = array_merge($val, array_values($data));
		}

		$stmt = Db::$pdo->prepare($sql);
		return $stmt->execute($val);
	}

	public function delete($id) {
		$sql = "DELETE FROM {$this->tableName} WHERE id =?";
		$stmt = Db::$pdo->prepare($sql);
		return $stmt->execute([$id]);
	}

	public function update($value = '') {

	}

	public function select($debug = false) {
		$this->option['sql'] = "SELECT {$this->option['distinct']}{$this->option['field']} FROM {$this->option['table']}{$this->option['force']}{$this->option['join']} WHERE {$this->option['where']}{$this->option['group']}{$this->option['having']}{$this->option['union']}{$this->option['order']}{$this->option['limit']}{$this->option['lock']}";
		loger($this->option);
	}

	public function find($id = null, $debug = false) {
		if ($id) {
			$this->option['sql'] = "SELECT {$this->option['distinct']}{$this->option['field']} FROM `{$this->option['table']}` WHERE id = $id";
		} else {
			$this->option['sql'] = "SELECT {$this->option['distinct']}{$this->option['field']} FROM `{$this->option['table']}` WHERE {$this->option['where']}";
		}

		$res = $this->execute()->fetch();

		$this->parseResult($res);

		return $res;
	}

	//解析条件
	private function parseWhere($un = 'AND', $a = null, $b = null, $c = null) {
		if ($c) {
			$this->option['where'] .= " $un `$a` $b ?";
			$this->option['execute'][] = $c;
		} elseif ($b) {
			$this->option['where'] .= " $un `$a` = ?";
			$this->option['execute'][] = $b;
		} else {
			//原生
			if (is_string($a)) {
				$this->option['where'] .= " $un " . $a;
			}
			//数组
			if (is_array($a)) {
				foreach ($a as $k => $v) {
					$this->option['where'] .= " $un `$k` = ?";
					$this->option['execute'][] = $v;
				}
			}
		}
	}
	//解析结果
	private function parseResult(&$res) {
		$c = config('model.' . $this->option['table']);
		foreach ($res as $k => &$v) {
			if (($c[$k]['type'] ?? null) == 'json') {
				$v = json_decode((string) $v, true);
			}
		}
	}
	//执行查询
	private function execute() {
		if ($this->option['debug']) {
			return loger($this->option);
		}
		$h = Db::$pdo->prepare($this->option['sql']);
		$h->execute($this->option['execute']);

		return $h;
	}

}
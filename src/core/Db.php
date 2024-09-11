<?
declare (strict_types = 1);
namespace lim;
use PDO;
use PDOException;

class Db {
	public static $config = [];
	public static $pdo;
	public static function init() {
		if (!self::$config) {self::$config = config('db.default');}
		if (!self::$pdo) {
			$dsn = "mysql:host=" . self::$config['hostname'] . ";dbname=" . self::$config['database'] . ";port=" . self::$config['hostport'];
			$option = [
				PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
				PDO::ATTR_CASE => PDO::CASE_NATURAL,
				PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
				PDO::ATTR_EMULATE_PREPARES => false, //解析数据库类型
				PDO::ATTR_STRINGIFY_FETCHES => false,
			];
			try {
				self::$pdo = new PDO($dsn, self::$config['username'], self::$config['password'], $option);
			} catch (PDOException $e) {
				apiErr("连接数据库失败: " . $e->getMessage());
			}
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
	public static function schema() {
		self::init();
		$sql = "SELECT TABLE_NAME,COLUMN_NAME,DATA_TYPE,COLUMN_COMMENT FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '" . self::$config['database'] . "' ORDER BY TABLE_NAME ASC,ORDINAL_POSITION ASC ";
		$res = self::$pdo->query($sql)->fetchall();
		$config = [];
		foreach ($res as $k => $v) {
			switch ($v['DATA_TYPE']) {
			case 'int':case 'tinyint':case 'decimal':
				$type = 'numeric';
				break;
			case 'varchar':
			case 'text':
				$type = 'string';
				break;
			case 'json':
				$type = 'array';
				break;
			default:
				$type = $v['DATA_TYPE'];
				break;
			}
			$commit = empty($v['COLUMN_COMMENT']) ? $v['COLUMN_NAME'] : $v['COLUMN_COMMENT'];
			if (str_contains($commit, '|')) {
				[$commit, $type] = explode('|', $commit);
			}
			$config[$v['TABLE_NAME']][$v['COLUMN_NAME']] = ['type' => $type, 'commit' => $commit];
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
	private $schema = [];
	private $option = ['debug' => false, 'field' => '*', 'table' => '', 'where' => '1', 'group' => '', 'order' => '', 'limit' => '', 'lock' => '', 'sql' => '', 'execute' => []];
	public function __call($method, $argv) {
		switch (strtolower($method)) {
		case 'schema':
			return $this->schema;
		case 'debug':
			$this->option['debug'] = true;
			break;
		case 'table':
			$this->option['table'] = $argv[0];
			$this->schema = config('model.' . $argv[0]);
			break;
		case 'name':
			$this->option['table'] = Db::$config['prefix'] . $argv[0];
			$this->schema = config('model.' . $argv[0]);
			break;
		case 'field':
			$this->option['field'] = $argv[0] ?? '*';
			break;
		case 'limit':
			$this->option['limit'] = ' LIMIT ' . (isset($argv[1]) ? $argv[0] . ',' . $argv[1] : $argv[0]);
			break;
		case 'page':
			$page = $argv[0] < 2 ? 0 : $argv[0] - 1;
			$limit = $argv[1];
			$offset = $page * $limit;
			$this->option['limit'] = " LIMIT $offset,$limit";
			break;
		case 'order':
			$this->option['order'] = ' ORDER BY ' . $argv[0];
			break;
		case 'group':
			$this->option['group'] = ' GROUP BY ' . $argv[0];
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
		case 'sum':case 'max':case 'min':case 'avg':
			if (!isset($argv[0])) {return NULL;}
		case 'count':
			$this->option['sql'] = "SELECT {$method}(" . ($argv[0] ?? '*') . ") as result FROM {$this->option['table']} WHERE {$this->option['where']}";
			return $this->execute()->fetch()['result'];
		default:
			break;
		}
		return $this;
	}
	public function insert($data = [], $id = false) {
		if (!$data) {return;}
		if (!isset($data[0])) {$data = [$data];}
		foreach ($data as $k => &$v) {
			foreach ($v as $key => &$value) {
				if (!isset($this->schema[$key])) {unset($v[$key]);} else {if (in_array($this->schema[$key]['type'], ['array', 'object'])) {$value = json_encode($value, 256);}}
			} //删除无效数据
			if ($k == 0) {$keys = '(`' . implode("`,`", array_keys($v)) . '`)';}
			$values[] = '(' . implode(",", array_fill(0, count($v), '?')) . ')';
			$this->option['execute'] = array_merge($this->option['execute'], array_values($v));
		}
		$this->option['sql'] = "INSERT INTO `{$this->option['table']}` $keys VALUES " . implode(',', $values);
		$h = $this->execute();
		return $id ? Db::$pdo->lastInsertId() : $h;
	}
	public function delete($id = null) {
		$this->option['sql'] = $id ? "DELETE FROM {$this->option['table']} WHERE id = $id" : "DELETE FROM {$this->option['table']} WHERE {$this->option['where']}";
		return $this->execute();
	}
	public function update($data = []) {
		foreach ($data as $k => $v) {
			if (!isset($this->schema[$k])) {continue;} //清理无效数据
			if (in_array($this->schema[$k]['type'], ['array', 'object'])) {$v = json_encode($v, 256);} //json数据序列化
			if ($this->option['where'] == '1' && $k == 'id') {
				$this->option['where'] = ' id = ' . $v;
				continue;
			} //当没有条件且数据存在ID的时候将ID作为条件
			$sets[] = "`$k`=?";
			$execute[] = $v;
		}
		if (isset($this->schema['update_at'])) {
			$sets[] = "`update_at`=?";
			$execute[] = time();
		}
		$this->option['execute'] = array_merge($execute ?? [], $this->option['execute']); //组合参数
		$set = implode(',', $sets);
		$this->option['sql'] = "UPDATE `{$this->option['table']}` SET {$set} WHERE " . $this->option['where'];
		return $this->execute();
	}
	public function select() {
		$this->option['sql'] = "SELECT {$this->option['field']} FROM {$this->option['table']} WHERE {$this->option['where']}{$this->option['group']}{$this->option['order']}{$this->option['limit']}{$this->option['lock']}";
		if ($res = $this->execute()->fetchAll()) {
			foreach ($res as $k => &$v) {$this->parseResult($v);}
		}
		return $res;
	}
	public function find($id = null) {
		$this->option['sql'] = $id ? "SELECT {$this->option['field']} FROM `{$this->option['table']}` WHERE id = $id" : "SELECT {$this->option['field']} FROM `{$this->option['table']}` WHERE {$this->option['where']}{$this->option['order']} LIMIT 1";
		if ($res = $this->execute()->fetch()) {
			$this->parseResult($res);
		}
		return $res;
	}
	public function value($column) {
		$this->option['sql'] = "SELECT {$column} FROM `{$this->option['table']}` WHERE {$this->option['where']}{$this->option['order']} LIMIT 1";
		$res = $this->execute()->fetch();
		$this->parseResult($res);
		return $res[$column];
	}
	public function column($value = '', $key = null) {
		$field = $key ? $key . ',' . $value : $value;
		$n = substr_count($field, ',');
		$this->option['sql'] = "SELECT {$field} FROM {$this->option['table']} WHERE {$this->option['where']}{$this->option['group']}{$this->option['order']}{$this->option['limit']}{$this->option['lock']}";
		$res = $this->execute()->fetchAll();
		foreach ($res as $k => &$v) {$this->parseResult($v);}
		if ($key) {
			$tmp = [];
			foreach ($res as $kk => $vv) {
				$tk = array_shifter($vv, $key);
				$tmp[$tk] = $n > 1 ? $vv : $vv[$value];
			}
			return $tmp;
		} else {
			return $n == 1 ? $res : array_column($res, $value);
		}
	}
	private function parseWhere($un = 'AND', $a = null, $b = null, $c = null) {
		if ($c !== null) {
			$this->option['where'] .= " $un `$a` $b ?";
			$this->option['execute'][] = $c;
		} elseif ($b) {
			$this->option['where'] .= " $un `$a` = ?";
			$this->option['execute'][] = $b;
		} else {
			if (is_string($a)) {$this->option['where'] .= " $un " . $a;} //原生
			if (is_array($a)) {
				foreach ($a as $k => $v) {
					$this->option['where'] .= " $un `$k` = ?";
					$this->option['execute'][] = $v;
				}
			} //数组
		}
	} //解析条件
	private function parseResult(&$res = []) {
		foreach ($res as $k => &$v) {
			switch ($this->schema[$k]['type'] ?? null) {
			case 'array':
				$v = $v ? (array) json_decode($v, true) : [];
				break;
			case 'object':
				$v = $v ? (object) json_decode($v, true) : new \stdclass();
				break;
			case 'string':
				$v ??= '';
				break;
			default:
				break;
			}
		}
	} //解析结果
	public function execute() {
		if ($this->option['debug']) {return loger($this);}
		$h = Db::$pdo->prepare($this->option['sql']);
		$h->execute($this->option['execute']);
		return $h;
	} //执行查询
	public function check($data = [], $rule = []) {
		foreach ($this->schema as $k => $v) {$rule[$v['commit'] . '|' . $k] = $v['type'];}
		check($data, $rule)->stop();
		return $this;
	}
}
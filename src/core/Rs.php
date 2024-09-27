<?
declare (strict_types = 1);
namespace lim;

class Rs {
	private static $pool = null;
	public static function init($connection = 'default') {
		if (self::$pool == null) {
			$c = config('redis');
			foreach ($c as $k => $v) {
				self::$pool[$k] = PHP_SAPI == 'cli' ? Pool::init(fn() => new RedisHandler($v)) : (new RedisHandler($v))->init();
			}
			loger('redis init');
		}
		return self::$pool[$connection];
	}
	public static function connection($connection = '') {
		$redis = PHP_SAPI == 'cli' ? self::init($connection)->pull() : self::init($connection);
		return new RedisProxy($connection, $redis);
	}
	public static function __callStatic($method, $option) {
		$redis = PHP_SAPI == 'cli' ? self::init()->pull() : self::init();
		return (new RedisProxy('default', $redis))->$method(...$option);
	}
}

class RedisProxy {
	public function __construct(private $connection = 'default', private $redis = null) {}
	public function __call($method, $argv) {
		switch (strtolower($method)) {
		case 'table':return new RedisTable($this->redis, ...$argv);
		case 'index':return new RedisIndex($this->redis, ...$argv);
		default:return $this->redis->handler->$method(...$argv);
		}
	}
	public function __destruct() {if (PHP_SAPI == 'cli') {Rs::init($this->connection)->push($this->redis);}}
}

class RedisTable {
	private $command = '';
	function __construct(private $redis = null, private $table = '') {}
	public function insert($data = []) {
		$data['id'] ??= $this->redis->handler->hincrby('jsonTableId', $this->table, 1);
		$this->command = ['JSON.SET', $this->table . ':' . $data['id'], '.', json_encode($data)];
		return $this->run();
	}
	public function delete($id, $key = '') {
		$keyArr = $key ? explode(',', $key) : [];
		$this->command = ['JSON.DEL', $this->table . ':' . $id, ...$keyArr];
		return $this->run();
	}
	public function update($id = '', $key = '', $value = '') {
		$value = is_array($value) ? json_encode($value) : $value;
		$this->command = ['JSON.SET', $this->table . ':' . $id, '.' . $key, $value];
		return $this->run();
	}
	public function find($id, $key = null) {
		$keyArr = $key ? explode(',', $key) : [];
		$this->command = ['JSON.GET', $this->table . ':' . $id, ...$keyArr];
		$res = $this->run();
		return $res ? json_decode($res, true) : null;
	}

	public function run() {
		return $this->redis->handler->rawCommand(...$this->command);
	}
}

/**
 *
 */
class RedisIndex {
	private $command = [];

	private $option = [
		'where' => '*',
		'limit' => [],
		'order' => [],
		'field' => '',
	];

	function __construct(private $redis = null, private $index = '') {}

	public function create($table, $schema) {
		$arr = ['FT.CREATE', $this->index, 'ON', 'JSON', 'PREFIX', 1, $table, 'SCHEMA'];

		foreach ($schema as $k => $v) {
			$arr = array_merge($arr, ['$.' . $k, 'AS', $k, ...$v]);
		}
		$this->command = $arr;
		$this->run();
		return $this->result;
	}

	public function delete() {
		$this->command = ['FT.DROPINDEX', $this->index];
		$this->run();
		return $this->result;
	}

	public function select() {
		$this->command = ['FT.SEARCH', $this->index];
		$this->command[] = $this->option['where'];

		if ($this->option['field'] ?? null) {
			$this->command = array_merge($this->command, $this->option['field']);
		}

		if ($this->option['order'] ?? null) {
			$this->command = array_merge($this->command, $this->option['order']);
		}

		if ($this->option['limit'] ?? null) {
			$this->command = array_merge($this->command, $this->option['limit']);
		}

		$res = $this->run();

		$result['total'] = array_shift($res);

		if ($this->option['field']) {
			for ($i = 1; $i < count($res); $i += 2) {
				$tmp = [];
				for ($n = 0; $n < $this->option['fieldCount'] * 2; $n += 2) {
					if (!isset($res[$i][$n])) {break;}
					$tmp[$res[$i][$n]] = $res[$i][$n + 1];
				}
				$result['list'][] = $tmp;
			}
		}
		return $result;
	}

	public function page($page, $limit = 10) {
		$page = $page < 2 ? 0 : $page;
		$this->option['limit'] = ['LIMIT', $page * $limit, $limit];
		return $this;
	}

	public function limit($len = 10) {
		$this->option['limit'] = ['LIMIT', 0, $len];
		return $this;
	}

	public function field($value = '') {
		$v = explode(',', $value);
		$this->option['fieldCount'] = count($v);
		$this->option['field'] = ['RETURN', $this->option['fieldCount'], ...$v];
		return $this;
	}

	public function order($k = '', $s = '') {
		$this->option['order'] = ['SORTBY', $k, $s, 'WITHCOUNT'];
		return $this;
	}

	public function where($w) {
		$this->option['where'] = $w;
		return $this;
	}

	public function run() {
		loger(implode(' ', $this->command));
		return $this->redis->handler->rawCommand(...$this->command);
	}

}

class RedisHandler {

	public function __construct(public $option = []) {}

	public function init() {
		$redis = new \Redis();
		$redis->connect($this->option['host'], (int) $this->option['port']);
		if ($this->option['auth']) {
			$redis->auth($this->option['auth']);
		}
		$redis->select($this->option['db']);

		$pool = new \Stdclass;
		$pool->handler = $redis;
		$pool->create = time();

		return $pool;
	}
}
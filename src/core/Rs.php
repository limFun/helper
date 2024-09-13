<?
declare (strict_types = 1);
namespace lim;

class Rs {
	public static $pool = null;
	public static function init($connection = 'default') {
		if (self::$pool == null) {
			$c = config('redis');
			foreach ($c as $k => $v) {
				self::$pool[$k] = Pool::init(fn() => new RedisHandler($v));
			}
			loger('init');
		}
		return self::$pool[$connection];
	}
	public static function connection($connection = '') {return new RedisProxy($connection, self::init($connection)->pull());}
	public static function __callStatic($method, $option) {
		return (new RedisProxy('default', self::init()->pull()))->$method(...$option);
	}
}

class RedisProxy {
	private $command;
	public function __construct(private $connection = 'default', private $redis = null) {}
	public function __call($method, $argv) {
		switch (strtolower($method)) {
		default:$result = $this->redis->handler->$method(...$argv);
			break;
		}
		return $result;
	}

	public function table($table = '') {
		$query = new RedisTable($table);
		return $query;
	}

	public function index($value = '') {

	}
	public function __destruct() {
		// loger(11);
		// Rs::put($this->connect,$this->handler);
	}
}

class RedisTable {
	private $command = '';
	function __construct(private $table = '', private $id = 1) {}
	public function insert($data = []) {
		$this->command = implode(' ', ['JSON.SET', $this->table . ':' . $this->id, '.', json_encode($data)]);
		return $this;
	}
	public function delete($id) {

	}
	public function update($value = '') {

	}
	public function find($id, $key = '') {
		$keyArr = [];
		if ($key) {
			foreach (explode(',', $key) as $k) {
				$keyArr[] = '$.' . $k;
			}
		}
		$this->command = implode(' ', ['JSON.GET', $this->table . ':' . $id, ...$keyArr]);
		return $this;
	}

}

class RedisJson {
	protected $id = 1;

	protected $command = '';

	public $result = null;

	public function __construct(protected $key = '') {}

	public function insert($ins) {
		if (!$id = $ins['id'] ?? null) {
			$id = Rs::hincrby('jsonTableId', $this->key, 1);
			$ins['id'] = $id;
		}
		$this->command = ['JSON.SET', $this->key . ':' . $id, '.', json_encode($ins)];
		$this->run();
		return $this->result;
	}

	public function update($key, $value) {
		$value = is_array($value) ? json_encode($value) : $value;
		$this->command = ['JSON.SET', $this->key . ':' . $this->id, '.' . $key, $value];
		$this->run();
		return $this->result;
	}

	public function id(int $id = 1) {
		$this->id = $id;
		return $this;
	}

	public function incr($key = '', $num = 1) {
		$this->command = ['JSON.NUMINCRBY', $this->key . ':' . $this->id, '.' . $key, $num];
		$this->run();
		return $this->result;
	}

	public function info($id, $key = '') {
		$keyArr = [];
		if ($key) {
			foreach (explode(',', $key) as $k) {
				$keyArr[] = '$.' . $k;
			}
		}
		$this->command = ['JSON.GET', $this->key . ':' . $id, ...$keyArr];
		$this->run();
		return json_decode($this->result);
	}

	public function arrAppend($id, $key, $value) {
		$v = is_numeric($value) ? $value : '"' . $value . '"';
		$this->command = ['JSON.ARRAPPEND', $this->key . ':' . $id, '.' . $key, $v];
		$this->run();
		return $this->result;
	}

	public function delete($id, $key) {
		$keyArr = [];
		if ($key) {
			foreach (explode(',', $key) as $k) {
				$keyArr[] = '$.' . $k;
			}
		}
		$this->command = ['JSON.DEL', $this->key . ':' . $id, ...$keyArr];
		$this->run();
		return $this->result;
	}

	public function run() {
		try {
			$pool = Rs::init()->pull();
			loger(implode(' ', $this->command));
			$this->result = $pool->handler->rawCommand(...$this->command);
			Rs::init()->push($pool);
			return $this;
		} catch (\Throwable $e) {
			loger($e);
		}

	}
}

class RedisFt {
	protected $command = '';

	public static $query = [];

	public $result = null;

	public function __construct(protected $index = '') {}

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

	public function info() {
		$this->command = ['FT.INFO', $this->index];
		$this->run();
		return $this->result;
	}

	public function search() {

		$query = ['FT.SEARCH', $this->index];
		$query[] = self::$query['where'] ?? '*';
		if (self::$query['limit'] ?? null) {
			$query = array_merge($query, self::$query['limit']);
		}

		if (self::$query['orderby'] ?? null) {
			$query = array_merge($query, self::$query['orderby']);
		}

		$this->command = $query;

		return $this;
		$this->run();

		$result['total'] = array_shift($this->result);

		for ($i = 1; $i < count($this->result); $i += 2) {

			$v = json_decode($this->result[$i][1] ?? '[]', true);
			if (self::$query['fields'] ?? null) {
				$v = array_intersect_key($v, array_fill_keys(self::$query['fields'], 1));
			}
			$result['list'][] = $v;
		}
		self::$query = null;
		return $result;
	}

	public function page($page, $limit) {
		$page = $page < 2 ? 0 : $page;
		self::$query['limit'] = ['LIMIT', $page * $limit, $limit];

		return $this;
	}

	public function limit($len = 10) {
		self::$query['limit'] = ['LIMIT', 0, $len];
		return $this;
	}

	public function fields($value = '') {
		self::$query['fields'] = $value;
		return $this;
	}

	public function orderby($k = '', $s = '') {
		self::$query['orderby'] = ['SORTBY', $k, $s];
		return $this;
	}

	public function where($w) {
		self::$query['where'] = $w;
		return $this;
	}

	public function run() {
		try {
			$pool = Rs::init()->pull();
			// loger(implode(' ', $this->command));
			$this->result = $pool->handler->rawCommand(...$this->command);
			Rs::init()->push($pool);
			return $this->result;
		} catch (\Throwable $e) {
			loger($e);
		}
		return $this;
	}

	public function __call($method, $option) {
		loger([$method, $option, $this]);
		return $this;
	}
}

class RedisHandler {

	public function __construct(public $option = []) {}

	public function run() {
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
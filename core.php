<?php

!define('BASE_PATH', __DIR__ . '/');
date_default_timezone_set('Asia/Shanghai');
Helper::register();
use function Swoole\Coroutine\Http\request;
use function Swoole\Coroutine\Http\request_with_http_client;
use Swoole\Coroutine;
use Swoole\Process;
use Swoole\Timer;

try {
	Helper::lim($argv);
} catch (\Throwable $e) {
	loger($e);
}

class LimServer {
	public $handler = null;

	public $option = [];

	public $currSer = 'http';

	public function __call($m, $o) {
		loger('server->' . $m . '()');
		return $this;
	}

	public static function init($o = []) {
		cli_set_process_title('LimServer');
		$action = array_shift($o);
		switch ($action) {
		case 'run':
		case 'start':
			(new self)->http()->watch()->task()->run();
			break;
		case 'reload':
		case 'stop':
		case 'shutdown':
		case 'stats':
			$url = 'http://127.0.0.1:9900/';
			$headers = ['token' => token(time()), 'action' => $action];

			$a = Http::url($url)->header($headers)->method('put')->toJson();
			loger($a);

			break;
		default:

			break;
		}
	}

	public function watch() {
		$proces = new Process(function () {
			cli_set_process_title($this->option['name'] . '-Watcher');
			$lt = time();
			while (true) {
				Helper::pf(BASE_PATH . 'app', function ($f) use (&$lt) {
					if (filemtime($f) > $lt) {
						loger($f . ' change ');
						$lt = time();
						$this->handler->reload();
					}
				});
				sleep(1);
			}
		}, false, 1, true);

		$this->handler->addProcess($proces);

		return $this;
	}

	public function task() {
		if (env('APP_ENV', 'dev') == 'dev') {
			return $this;
		}
		$tasker = new Process(function ($e) {
			cli_set_process_title($this->option['name'] . '-Tasker');
			Helper::import('tasker');
		}, false, 1, true);
		if ($this->handler) {
			$this->handler->addProcess($tasker);
		} else {
			$tasker->start();
		}
		return $this;
	}

	public function http() {
		$this->option = config('service.http');
		$this->currSer = 'http';

		$this->handler = new \Swoole\WebSocket\Server('0.0.0.0', $this->option['port'], SWOOLE_PROCESS);
		$this->handler->set($this->option['option']);
		$this->handler->on('start', array($this, 'onStart'));
		$this->handler->on('managerstart', array($this, 'onManagerstart'));
		$this->handler->on('beforeReload', array($this, 'onBeforeReload'));
		$this->handler->on('afterReload', array($this, 'onAfterReload'));
		$this->handler->on('workerStart', array($this, 'onWorkerStart'));
		$this->handler->on('request', array($this, 'onRequest'));
		$this->handler->on('open', array($this, 'onOpen'));
		$this->handler->on('message', array($this, 'onMessage'));
		return $this;
	}

	public function run() {
		// loger($this->handler);
		$this->handler->start();
	}

	public function onStart() {
		cli_set_process_title($this->option['name'] . '-Server');
		loger($this->option['name'] . '-Server Start');
	}

	public function onManagerstart() {
		cli_set_process_title($this->option['name'] . '-Manager');
		loger($this->option['name'] . '-Manager Start');
	}

	public function onBeforeReload() {
		Helper::import('config,env');
		loger($this->option['name'] . '-Worker BeforeReload');
	}

	public function onAfterReload() {
		loger('onAfterReload ' . config('server.http.name'));
	}

	public function onWorkerStart(\Swoole\Server $server, int $workerId) {
		cli_set_process_title($this->option['name'] . '-Worker-' . $workerId);
		loger($this->option['name'] . '-Worker' . $workerId . ' Start');
		// loger(get_included_files());
		Helper::import('helper,api');

	}

	public function onRequest($request, $response) {
		$response->header('Server', 'LimServer');

		if ($request->server['path_info'] == '/favicon.ico' || $request->server['request_uri'] == '/favicon.ico') {
			return $response->end();
		}

		switch ($request->server['request_method']) {
		case 'PUT':
			loger($request->header);
			if ($t = token($request->header['token'] ?? '', true)) {
				$action = $request->header['action'];
				$status = $this->handler->$action();
			}

			return $response->end(json_encode($status ?? time()));
		case 'GET':
			$data = $request->get;
			break;
		case 'POST':
			$data = $request->post ?? json_decode($request->getContent(), true);
			break;
		default:
			$response->header('Access-Control-Allow-Origin', '*');
			$response->header('Access-Control-Allow-Methods', '*');
			$response->header('Access-Control-Allow-Headers', '*');
			return $response->end();
		}

		Coroutine::getContext()['request'] = $request;
		Coroutine::getContext()['response'] = $response;
		Coroutine::getContext()['data'] = $data;

		try {
			$uri = substr($request->server['request_uri'], 1);

			if ($o = $GLOBALS['config']['router'][$uri] ?? null) {
				$result = (new $o['class'])->__before()->{$o['action']}($data);
				Helper::json($result);
			} else {
				Helper::json(null, '路由不存在', 3001);
			}

		} catch (\Throwable $e) {

			if ($e instanceof \PDOException) {
				loger($e->getMessage());
			}
			// loger([$e,22]);
			Helper::json(null, $e->getMessage(), $e->getCode());
		}
	}

	public function onOpen($server, $request) {
		loger($request);
	}

	public function onMessage($server, $frame) {
		loger($frame);
		$server->push($frame->fd, $frame->data);
	}
}

class LimApi {
	public function __construct() {
		// code...
	}

	public function __before() {
		return $this;
	}
}

class Db {
	protected static $pool = null;

	protected $pdo = null;

	public $apiData = null;

	public $query = [
		'debug' => false,
		'table' => '',
		'action' => '',
		'fields' => '*',
		'where' => '',
		'orderBy' => '',
		'groupBy' => '',
		'limit' => '',
		'execute' => [],
		'sql' => '',
	];

	public static function init($connect = 'default') {
		if (self::$pool == null) {
			$c = config('db');
			foreach ($c as $k => $v) {
				self::$pool[$k] = Pool::init(fn() => new PdoConnecter($v));
			}
			loger('init');
		}
		return self::$pool[$connect];
	}

	public static function table($table = 'table') {
		$curr = new self();
		$curr->query['table'] = $table;
		return $curr;
	}

	public static function raw($sql = '') {
		$curr = new self();
		$curr->query['sql'] = $sql;
		return $curr;
	}

	// public function __construct($data=null){
	//     $this->data = $data;
	// }

	public function __destruct() {
		if ($this->query['debug']) {
			loger($this);
		}
		Db::init()->push($this->pdo);
		$this->pdo = null;

		// loger('DB __destruct');
	}

	public function __checkSql($action = '') {
		$must = [];
		switch ($action) {
		case 'insert':
			$must = ['table', 'execute'];
			break;
		case 'update':
			$must = ['table', 'execute', 'where'];
			break;
		case 'delete':
		case 'select':
			$must = ['table', 'where'];
			break;
		}
		foreach ($must as $k) {
			if (empty($this->query[$k])) {
				throw new \Exception("$k 是必须的", 1);
			}
		}
		return $this;
	}

	public function insert($data = []) {
		$data = empty($data) ? $this->apiData : $data;
		$key = '(`' . implode("`,`", array_keys($data)) . '`)';
		$val = '(' . implode(",", array_fill(0, count($data), '?')) . ')';
		$this->query['sql'] = "INSERT INTO `{$this->query['table']}` $key VALUES $val";
		$this->query['execute'] = array_values($data);
		$this->execute('insert');
		return $this->pdo->handler->lastInsertId();
	}

	public function delete() {
		$this->query['sql'] = "DELETE FROM {$this->query['table']}{$this->query['where']}";
		return $this->execute('delete')->rowCount();
	}

	public function update($data = []) {
		$data = empty($data) ? $this->apiData : $data;
		$sets = [];
		foreach ($data as $k => $v) {
			$sets[] = "`$k`=?";
			$this->query['execute'][] = $v;
		}
		$this->query['sql'] = "UPDATE `{$this->query['table']}` SET " . implode(',', $sets) . $this->query['where'];
		return $this->execute('update')->rowCount();
	}

	public function select(bool $first = false) {
		$this->query['sql'] = "SELECT {$this->query['fields']} FROM {$this->query['table']}{$this->query['where']}{$this->query['groupBy']}{$this->query['orderBy']}{$this->query['limit']}";
		$h = $this->execute('select');

		return $first ? $h->fetch(PDO::FETCH_OBJ) : $h->fetchAll(PDO::FETCH_OBJ);
	}

	public function list() {
		$this->pdo = Db::init()->pull();
		$limit = empty($this->query['limit']) ? ' LIMIT 0 , 10' : $this->query['limit'];
		$tSql = "SELECT COUNT(*) AS total FROM {$this->query['table']}{$this->query['where']}{$this->query['groupBy']}{$this->query['orderBy']}";
		$t = $this->pdo->handler->prepare($tSql);
		$t->execute();
		$result['total'] = $t->fetch(PDO::FETCH_OBJ)->total;

		$lSql = "SELECT {$this->query['fields']} FROM {$this->query['table']}{$this->query['where']}{$this->query['groupBy']}{$this->query['orderBy']}$limit";
		$l = $this->pdo->handler->prepare($lSql);
		$l->execute();
		$result['list'] = $l->fetchAll(PDO::FETCH_ASSOC);
		// Db::init()->put($pdo);
		return $result;
	}

	public function __call($method, $o) {

		switch (strtolower($method)) {
		case 'd':
		case 'debug':
			$this->query['debug'] = true;
			break;
		case 'whereraw':
			$this->query['where'] .= !empty($this->query['where']) ? ' AND ' . $o[0] : ' WHERE ' . $o[0];
			break;
		case 'where':
			$this->query['where'] .= !empty($this->query['where']) ? ' AND ' . $this->_parseWhere($o) : ' WHERE ' . $this->_parseWhere($o);
			break;
		case 'orwhere':
			$this->query['where'] = !empty($this->query['where']) ? ' WHERE (' . substr($this->query['where'], 7) . ') OR (' . $this->_parseWhere($o) . ')' : ' WHERE ' . $this->_parseWhere($o);
			break;
		case 'limit':
			$page = (int) $o[0] ?? 0;
			$limit = (int) $o[1] ?? 10;
			$start = ($page > 1 ? $page - 1 : 0) * $limit;
			$this->query['limit'] = " LIMIT $start , $limit";
			break;
		case 'fields':
			$this->query['fields'] = $o[0];
			break;
		case 'orderby':
			if (($key = $o[0] ?? null) && ($sort = $o[1] ?? null)) {
				if ($this->query['orderBy']) {
					$this->query['orderBy'] .= " , `$key` $sort";
				} else {
					$this->query['orderBy'] = " ORDER BY `$key` $sort";
				}
			}
			break;
		case 'groupby':
			if ($col = $o[0] ?? null) {
				if ($this->query['groupBy']) {
					$this->query['groupBy'] = " , `$col` ";
				} else {
					$this->query['groupBy'] = " GROUP BY `$col`";
				}
			}
			break;
		case 'parse':
			$data = $o[0] ?? [];
			if (isset($data['page']) || isset($data['limit'])) {
				$this->limit($data['page'] ?? 0, $data['limit'] ?? 10);
				unset($data['page'], $data['limit']);
			}
			if (isset($data['orderBy']['key']) && isset($data['orderBy']['sort'])) {
				$this->orderBy($data['orderBy']['key'], $data['orderBy']['sort']);
				unset($data['orderBy']);
			}
			foreach ($data as $k => $v) {
				$this->where($k, $v);
			}
			break;
		case 'count':
			$this->query['sql'] = "SELECT COUNT(*) AS total FROM {$this->query['table']}{$this->query['where']}";
			$this->query['action'] = 'count';
			break;
		default:
			loger([$method, $o]);
			break;
		}
		return $this;
	}

	public function execute($method = '') {
		$this->__checkSql($method);
		$this->pdo = Db::init()->pull();
		$h = $this->pdo->handler->prepare($this->query['sql']);
		$h->execute($this->query['execute']);
		return $h;
	}

	public function _parseWhere($v) {
		if (!isset($v[2])) {
			array_splice($v, 1, 0, "=");
		}
		[$a, $b, $c] = $v;
		if (is_null($c)) {
			$s = $b == '=' ? "`$a` IS NULL" : "`$a` IS NOT NULL";
		} else if (is_numeric($c)) {
			$s = "`$a`$b$c";
		} else if (empty($c) || is_string($c)) {
			$s = "`$a`$b'$c'";
		}
		return $s;
	}
}

class Rs {

	public static $pool = null;

	public $query = [

	];

	public function __construct() {
		// code...
	}

	public static function init($connect = 'default') {
		if (self::$pool == null) {
			$c = config('redis');
			foreach ($c as $k => $v) {
				self::$pool[$k] = Pool::init(fn() => new RedisConnecter($v));
			}
			loger('init');
		}
		return self::$pool[$connect];
	}

	public static function ft($index = '') {
		$curr = new RedisFt($index);
		return $curr;
	}

	public static function json($key = '') {
		$curr = new RedisJson($key);
		return $curr;
	}

	public static function __callStatic($method, $option) {
		$pool = self::init()->pull();

		$result = $pool->handler->$method(...$option);
		// loger([$handler,$method,$option,$result]);
		self::init()->push($pool);
		return $result;
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

class Pool {
	public $pool = null;

	public $call = null;

	public $exprie = 0;

	public $size = 0;

	public $num = 0;

	public static function init($call) {
		$curr = new self();
		$curr->call = $call();
		$curr->exprie = $curr->call->option['poolExpire'];
		$curr->size = $curr->call->option['poolSize'];
		$curr->pool = new Coroutine\Channel($curr->size);
		return $curr;
	}

	public function make($num = 1) {
		$this->pool->push($this->call->run());
		$this->num++;
		return $this;
	}

	public function pull($name = '') {
		if ($this->pool->isEmpty() && $this->num < $this->size) {
			$this->make();
			// loger('创建填充');
		}

		if ($this->num <= 0) {
			// return null;
			$this->make();
		}

		// loger($this->num);

		$p = $this->pool->pop(-1);
		$this->num--;
		//过期丢弃
		if ($p->create + $this->exprie < time()) {
			// loger($p->create . '过期丢弃');
			return $this->pull();
		}
		return $p;
	}

	public function push($call = null) {
		if ($call !== null) {
			$this->pool->push($call);
			$this->num++;
		}
	}

}

class RedisConnecter {

	public function __construct(public $option = []) {
		if (empty($this->option)) {
			$this->option = config('redis.default');
		}
	}

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

class PdoConnecter {

	public function __construct(public $option = []) {

	}

	public function run() {
		$dsn = "mysql:host={$this->option['host']};dbname={$this->option['database']};port={$this->option['port']};charset={$this->option['charset']}";
		$opt = [
			PDO::ATTR_DEFAULT_FETCH_MODE => $this->option['fetch_mode'] ?? PDO::FETCH_ASSOC,
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
			PDO::ATTR_STRINGIFY_FETCHES => false,
			PDO::ATTR_EMULATE_PREPARES => false, // 这2个是跟数字相关的设置
			PDO::ATTR_TIMEOUT => $this->option['timeout'],
			// PDO::ATTR_PERSISTENT=>true,
		];

		$pool = new \Stdclass;
		$pool->handler = new \PDO($dsn, $this->option['username'], $this->option['password'], $opt);
		$pool->create = time();
		return $pool;

	}
}

class Http {
	protected $url = '';

	protected $method = '';

	protected $data = [];

	protected $option = ['timeout' => 15];

	protected $header = [];

	protected $cookie = [];

	public $result = null;

	public function __construct($url) {
		$this->url = $url;
	}
	public static function url($url) {
		$handler = new self($url);
		return $handler;
	}
	public function data($data = []) {
		$this->data = array_merge($this->data, $data);
		return $this;
	}

	public function option($option = []) {
		$this->option = array_merge($this->option, $option);
		return $this;
	}

	public function header($header = []) {
		$this->header = array_merge($this->header, $header);
		return $this;
	}

	public function cookie($cookie = []) {
		$this->cookie = array_merge($this->cookie, $cookie);
		return $this;
	}

	public function proxy($proxy) {
		$this->option = array_merge($this->option, $proxy);
		return $this;
	}

	public function method($method = '') {
		$this->method = strtoupper($method);
		$this->result = request_with_http_client($this->url, $this->method, $this->data, $this->option, $this->header, $this->cookie);

		return $this;
	}

	public function toJson() {
		return json_decode($this->result->getBody() ?? '{}', true);

	}
}

class Helper {
	public static function register() {
		// spl_autoload_register();
		spl_autoload_register(function ($class) {
			$file = BASE_PATH . str_replace('\\', '/', $class) . '.php';
			// echo $file.PHP_EOL;
			if (is_file($file)) {
				loger('load ' . $class);
				include_once $file;
			}
		});
		// require BASE_PATH . 'vendor/autoload.php';
	}
	public static function lim($o) {
		self::import('config,env');
		array_shift($o);
		$method = array_shift($o);
		switch ($method) {
		case 'fn':
			self::import('helper');
			run(fn() => array_shift($o)(...$o));
			break;
		case 'ob':
			self::import('helper');
			// ob($o);
			run(fn() => ob($o));
			break;
		case 'server':
			LimServer::init($o);
			break;
		default:
			loger($o);
			mkdir(BASE_PATH . 'runtime', 0777);
			break;
		}
	}

	public static function app() {
		$o = func_get_args();
		$key = array_shift($o);
		switch ($key) {
		case 'curr':
			return Coroutine::getContext();
		case 'user':
			if (!$token = \Swoole\Coroutine::getContext()->request->header['authorization'] ?? null) {
				return null;
			}

			if (!$res = token(substr($token, 7), true) ?? []) {
				return null;
			}

			return (object) $res;
		default:
			break;
		}
	}

	public static function pf($path = '', $call = null, &$files = []) {

		if (is_dir($path)) {
			//是否为目录
			$dp = dir($path);
			while ($file = $dp->read()) {
				if ($file != "." && $file != "..") {
					self::pf($path . "/" . $file, $call, $files); //递归
				}
			}
			$dp->close();
		}

		if (is_file($path)) {
			$call ? $call($path) : array_push($files, $path);
		}

		return $files ?? [];
	}

	public static function import($e = '') {

		if (str_contains($e, 'env') && is_file(BASE_PATH . 'app/.env')) {
			$o = parse_ini_file(BASE_PATH . 'app/.env');
			foreach ($o as $k => $v) {
				putenv($k . '=' . $v);
			}
			self::loger('load env');
		}

		if (str_contains($e, 'helper')) {
			self::pf(BASE_PATH . 'app', function ($f) {
				if (str_contains($f, 'helper.php')) {
					include_once $f;
				}
			});
			self::loger('load helper');
		}

		if (str_contains($e, 'config')) {
			self::pf(BASE_PATH . 'app/config', fn($f) => $GLOBALS['config'][preg_replace('/.+\/|.php/', '', $f)] = include $f);
			self::loger('load config');
		}

		if (str_contains($e, 'tasker')) {
			self::pf(BASE_PATH . 'app/task', fn($f) => include_once $f);
			self::loger('load tasker');
		}

		if (str_contains($e, 'api')) {
			self::pf(BASE_PATH . 'app/api', function ($f) {
				$apiName = preg_replace('/.+\/|.php/', '', $f);
				$class = str_replace('/', '\\', '/app/api/' . $apiName);
				$obj = new \ReflectionClass($class);
				$attrName = $obj->getNamespaceName() . '\\Attr';
				foreach ($obj->getMethods() as $k => $v) {
					$method = $v->name;
					if (str_contains($method, '__')) {
						continue;
					}
					$tmp = ['class' => $class, 'action' => $method];
					$uri = strtolower($apiName) . '/' . $method;
					if ($type = $v->getAttributes($attrName)[0] ?? null) {
						// loger($type->getArguments());
						$uri = $type->getArguments()['uri'] ?? $uri;
					}
					$GLOBALS['config']['router'][$uri] = $tmp;
				}
				// loger($GLOBALS['config']['router']);
			});
			self::loger('load api');
		}
	}

	public static function ob($o = []) {
		$obj = str_replace('.', '\\', array_shift($o));
		// 判断是否有方法
		if (!$action = array_shift($o)) {
			new $obj();
			// 判断方法是否存在
		} elseif (!method_exists($obj, $action)) {
			loger($obj . ' ' . $action . ' 方法不存在');
			// 判断静态方法
		} elseif ((new \ReflectionMethod($obj, $action))->isStatic()) {
			$obj::$action(...$o);
		} else {
			(new $obj())->{$action}(...$o);
		}
	}

	public static function token($data = '', $de = false) {
		if ($de) {
			if (!$ret = openssl_decrypt(base64_decode($data), 'AES-128-CBC', 'service.yuwan.cn', 1, 'service.yuwan.cn')) {
				return null;
			}
			return json_decode($ret, true);
		}

		if (is_array($data) || is_object($data)) {
			$data = json_encode($data);
		}

		return base64_encode(openssl_encrypt((string) $data, 'AES-128-CBC', 'service.yuwan.cn', 1, 'service.yuwan.cn'));
	}

	public static function config($key = '') {
		if (!$key) {
			return $GLOBALS['config'];
		}
		if (strpos($key, '.') === false) {
			return $GLOBALS['config'][$key] ?? null;
		}

		$keys = explode('.', $key);
		$curr = $GLOBALS['config'];
		foreach ($keys as $k) {
			$curr = $curr[$k] ?? null;
		}

		return $curr;
	}

	public static function loger($v = '') {
		$t = explode(' ', date('Y-m-d H:i:s'));
		if (is_array($v) || is_object($v)) {
			$v = print_r($v, true);
		}
		echo shell_exec('echo -n "[' . $t[1] . '] "') . $v . PHP_EOL;
	}

	public static function json($data = [], $message = '请求成功', $code = 0) {
		$result = ['code' => $code, 'message' => $message, 'data' => $data];
		$response = Coroutine::getContext()['response'];

		$response->header('Access-Control-Allow-Origin', '*');
		$response->header('Access-Control-Allow-Methods', '*');
		$response->header('Access-Control-Allow-Headers', '*');
		$response->header('Content-Type', 'application/json;charset=utf-8');

		if ($response->isWritable()) {
			return $response->end(json_encode($result));
		}
	}
}

class Tasker {
	private $name = '匿名任务';
	private $space = 0;
	private $after = 0;
	public static function create($name = '') {
		$curr = new self;
		$curr->name($name);
		return $curr;
	}
	public function name(string $name = '') {
		$this->name = $name;
		return $this;
	}
	public function space(float | int $space) {
		$this->space = $space;
		return $this;
	}
	public function after(float | int $after) {
		$this->after = $after;
		return $this;
	}
	public function call(Closure $call) {
		if ($this->space == 0) {
			if ($this->after <= 0) {
				loger('立即执行一次' . $this->name);
				$call();
			} else {
				loger($this->after . '秒后执行一次' . $this->name);
				Timer::after(1000 * $this->after, fn() => $call());
			}
		} else {
			$begin = $this->space < 1 ? 1 : $this->space - (time() + 28800) % $this->space;
			loger(date('Y-m-d H:i:s', time() + $begin) . " 后每隔 " . $this->space . " 秒循环执行 {$this->name}");
			Timer::after(1000 * $begin, function () use ($call) {
				$this->after == 0 ? $call() : Timer::after(1000 * $this->after, fn() => $call());

				Timer::tick((int) (1000 * $this->space), fn() => $this->after == 0 ? $call() : Timer::after(1000 * $this->after, fn() => $call()));
			});
		}

	}
}

function app() {
	Helper::app();
}

function json() {
	return Helper::json(...func_get_args());
}

function config($e = '') {
	return Helper::config($e);
}

function pf() {
	Helper::pf(...func_get_args());
}

function ob() {
	Helper::ob(...func_get_args());
}

function loger($v = '') {
	Helper::loger($v);
}

function token($data = '', $de = false) {
	return Helper::token($data, $de);
}

function env($key = '', $default = '') {
	$r = getenv($key);
	return $r ? $r : $default;
}

function tu($fn, $value = '') {
	$s = microtime(true);
	$fn();
	$u = intval((microtime(true) - $s) * 1000);
	loger($value . '耗时:' . $u . '毫秒');
}

function run(callable $fn, ...$args) {
	$s = new Coroutine\Scheduler();
	$options = Coroutine::getOptions();
	if (!isset($options['hook_flags'])) {
		$s->set(['hook_flags' => SWOOLE_HOOK_ALL]);
	}
	$s->add($fn, ...$args);
	return $s->start();
}

function go(callable $fn, ...$args) {
	return Coroutine::create($fn, ...$args);
}

function defer(callable $fn) {
	Coroutine::defer($fn);
}

<?
declare (strict_types = 1);

class limApiErr extends \Exception {}

class limModelResult extends \Stdclass {

	public static function init($data, $handler) {
		$result = new self;
		foreach ($data as $k => $v) {
			$result->$k = $v;
		}
		$result->handler = $handler;
		return $result;
	}

	public function save($value = '') {
		loger($this);
	}

	public function delete($value = '') {
		// code...
	}

	public function update($value = '') {
		// code...
	}
}

if (!function_exists('array_shifter')) {
	function array_shifter(&$o, $k = '', $v = '') {
		$t = $o[$k] ?? $v;
		unset($o[$k]);
		return $t;
	}
}

if (!function_exists('token')) {
	function token($data = '', $de = false) {
		if ($de) {
			if (!$ret = openssl_decrypt(base64_decode((string) $data), 'AES-128-CBC', 'service.yuwan.cn', 1, 'service.yuwan.cn')) {return null;}
			return json_decode($ret, true);
		}
		if (is_array($data) || is_object($data)) {$data = json_encode($data);}
		return base64_encode(openssl_encrypt((string) $data, 'AES-128-CBC', 'service.yuwan.cn', 1, 'service.yuwan.cn'));
	}
}

if (!function_exists('loger')) {
	function loger($v = '', $type = 'debug') {
		if (PHP_SAPI == 'cli') {
			$color = ['debug' => '\\e[33m', 'info' => '\\e[32m', 'err' => '\\e[31m'];
			if (is_array($v) || is_object($v)) {$v = print_r($v, true);}
			$str = '\\033[36m[' . date('H:i:s') . '] ' . $color[$type];
			echo shell_exec('echo -e -n "' . $str . '"') . $v . PHP_EOL;
		}
	}
}

if (!function_exists('ff')) {
	function ff($dir, $call = null) {
		$result = [];
		if (is_dir($dir)) {
			$files = scandir($dir);
			foreach ($files as $file) {
				if ($file == '.' || $file == '..') {continue;}
				$filePath = $dir . DIRECTORY_SEPARATOR . $file;
				if (is_dir($filePath)) {
					$result = array_merge($result, ff($filePath, $call));
				} elseif (is_file($filePath)) {
					if ($call) {$call($filePath, $result);} else { $result[] = $filePath;}
				}
			}
		}
		return $result;
	}
}

if (!function_exists('run')) {
	function run(callable $fn, ...$args) {
		$s = new \Swoole\Coroutine\Scheduler();
		$options = \Swoole\Coroutine::getOptions();
		if (!isset($options['hook_flags'])) {
			$s->set(['hook_flags' => SWOOLE_HOOK_ALL]);
		}
		$s->add($fn, ...$args);
		return $s->start();
	}
}

if (!function_exists('curr')) {
	function curr(string $v) {
		switch ($v) {
		case 'request':
		case 'response':
		case 'server':
			return \lim\Context::get($v);
		case 'redis':
			return \lim\Rs::connection('default');
		}
	}
}
if (!function_exists('app')) {
	function app(string $v) {
		return \lim\App::run($v);
	}
}

if (!function_exists('env')) {function env(string $k = '', $v = '') {return isset($GLOBALS['env'][$k]) ? $GLOBALS['env'][$k] : $v;}}
if (!function_exists('config')) {function config(string $k = '') {return lim\Config::get($k);}}
if (!function_exists('check')) {function check($data = [], $rule = []) {return lim\Check::data($data)->rule($rule);}}
if (!function_exists('http')) {function http(string $url = '') {return lim\Http::url($url);}}
if (!function_exists('redis')) {function redis($connection = 'default') {return \lim\Rs::connection($connection);}}
if (!function_exists('apiErr')) {function apiErr($message = '', $code = 300) {throw new limApiErr($message, $code);}}

lim\Config::init();

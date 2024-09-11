<?
declare (strict_types = 1);
class limApiErr extends \Exception {}
function array_shifter(&$o, $k = '', $v = '') {
	$t = $o[$k] ?? $v;
	unset($o[$k]);
	return $t;
}

function token($data = '', $de = false) {
	if ($de) {
		if (!$ret = openssl_decrypt(base64_decode($data), 'AES-128-CBC', 'service.yuwan.cn', 1, 'service.yuwan.cn')) {return null;}
		return json_decode($ret, true);
	}
	if (is_array($data) || is_object($data)) {$data = json_encode($data);}
	return base64_encode(openssl_encrypt((string) $data, 'AES-128-CBC', 'service.yuwan.cn', 1, 'service.yuwan.cn'));
}

if (!function_exists('loger')) {
	function loger($v = '', $type = 'debug', $file = null) {
		if (PHP_SAPI == 'cli') {
			$color = ['debug' => '\\e[33m', 'info' => '\\e[32m', 'err' => '\\e[31m'];
			if (is_array($v) || is_object($v)) {$v = print_r($v, true);}
			$str = '\\033[36m[' . date('H:i:s') . '] ' . $color[$type];
			echo shell_exec('echo -e -n "' . $str . '"') . $v . PHP_EOL;
		} else {echo json_encode($v);}
	}
}

function check($data = [], $rule = []) {return lim\Check::data($data)->rule($rule);}
function http($value = '') {return lim\Http::url($value);}
if (!function_exists('config')) {
	function config($key = '') {return lim\Config::get($key);}
}
if (!function_exists('env')) {
	function env($key = '', $default = '') {
		$r = getenv($key);
		return $r ? $r : $default;
	}
}
function redis() {
	$redis = new \Redis;
	$c = config('redis');
	$redis->connect($c['host'], (int) $c['port']);
	if ($c['auth']) {$redis->auth($c['auth']);}
	return $redis;
}
function apiErr($message = '', $code = 300) {throw new limApiErr($message, $code);}
function json($data = [], $message = "success", $code = 200) {die(json_encode(['code' => $code, 'message' => $message, 'result' => $data]));}
lim\Config::init();

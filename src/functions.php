<?php
declare (strict_types = 1);

lim\Config::init();

if (!function_exists('loger')) {
	function loger($v = '', $type = 'debug', $file = null) {

		// if ($file) {
		//  $file = $file == true ? date('Y-m-d') . 'log' : $file;
		//  file_put_contents(ROOT_PATH . '/runtime/logs/' . $file, date('Y-m-d H:i:s') . ' ' . $v . PHP_EOL, FILE_APPEND);
		// }

		if (PHP_SAPI == 'cli') {
			$color = ['debug' => '\\e[33m', 'info' => '\\e[32m', 'err' => '\\e[31m'];

			if (is_array($v) || is_object($v)) {
				$v = print_r($v, true);

			}
			$str = '\\033[36m[' . date('H:i:s') . '] ' . $color[$type];
			echo shell_exec('echo -e -n "' . $str . '"') . $v . PHP_EOL;
		} else {
			echo json_encode($v);
		}
	}
}

function check($rule = [], $data = []) {
	return lim\Validator::run($data, $rule)->throw();
}

function http($value = '') {
	return lim\Http::url($value);
}

if (!function_exists('config')) {

	function config($key = '') {

		return lim\Config::config($key);

	}
}

if (!function_exists('env')) {
	function env($key = '', $default = '') {
		$r = getenv($key);
		return $r ? $r : $default;
	}
}

function apiErr($message = '', $code = 300) {
	die(json_encode(['code' => $code, 'message' => $message]));
}

function json($data = [], $message = "success", $code = 200) {
	die(json_encode(['code' => $code, 'message' => $message, 'result' => $data]));
}
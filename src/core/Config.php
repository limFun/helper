<?
declare (strict_types = 1);
namespace lim;

class Config {

	public static function init() {
		if (is_file(ROOT_PATH . '.env')) {
			$o = parse_ini_file(ROOT_PATH . '.env');
			foreach ($o as $k => $v) {
				putenv($k . '=' . $v);
			}
		}

		self::pf(ROOT_PATH . 'app', function ($f) {
			if (str_contains($f, 'helper.php')) {
				include_once $f;
			}
		});
		self::pf(ROOT_PATH . 'config', fn($f) => $GLOBALS['config'][preg_replace('/.+\/|.php/', '', $f)] = include $f);
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
}
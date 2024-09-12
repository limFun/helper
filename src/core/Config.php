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

		ff(ROOT_PATH . 'app', function ($f) {if (str_contains($f, 'helper.php')) {include_once $f;}});

		ff(ROOT_PATH . 'config', fn($f) => $GLOBALS['config'][preg_replace('/.+\/|.php/', '', $f)] = include $f);
	}

	public static function get($key = '') {
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
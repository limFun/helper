<?
declare (strict_types = 1);
namespace lim;

class Config {

	public static function init() {
		//加载环境变量
		if (is_file(ROOT_PATH . '.env')) {
			$o = parse_ini_file(ROOT_PATH . '.env');
			foreach ($o as $k => $v) {
				putenv($k . '=' . $v);
				$GLOBALS['env'][$k] = $v;
			}
		}
		//加载配置文件
		ff(ROOT_PATH . 'config', function ($f) {
			//FPM环境不加载服务配置
			if (PHP_SAPI !== 'cli' && str_contains($f, 'service.php')) {return;}
			$GLOBALS['config'][preg_replace('/.+\/|.php/', '', $f)] = include_once $f;
		});
		//加载助手函数
		ff(ROOT_PATH . 'app', function ($f) {if (str_contains($f, 'helper.php')) {include_once $f;}});
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
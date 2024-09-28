<?
declare (strict_types = 1);
namespace lim;

class App {
	private static $store = [];

	public static function init() {
		self::parseEnv();
		self::parseConfig();
		ff(ROOT_PATH . 'app', function ($path, $fileName) {
			$name = strstr($fileName, '.', true);
			$module = str_replace(ROOT_PATH . 'app/', '', dirname($path));
			switch ($module) {
			case 'model':
			case 'task':
			case 'service':
				self::makeModule($module);
				self::$store[$module]->$name = "\\app\\{$module}\\{$name}";
				break;
			case 'route':
				self::parseRoute($name);
				break;
			default:
				break;
			}
		});
		// loger(self::$store);
	}

	protected static function parseRoute($name = '') {
		self::makeModule('route');
		$obj = "\\app\\route\\{$name}";
		self::$store['route']->$name = $obj::init();
	}

	protected static function parseEnv() {
		if (is_file(ROOT_PATH . '.env')) {
			$o = parse_ini_file(ROOT_PATH . '.env');
			foreach ($o as $k => $v) {
				self::$store['env'][$k] = $v;
			}
		}
	}

	protected static function parseConfig() {
		self::makeModule('config');
		ff(ROOT_PATH . 'config', function ($f) {
			//FPM环境不加载服务配置
			if (PHP_SAPI !== 'cli' && str_contains($f, 'service.php')) {return;}
			$key = preg_replace('/.+\/|.php/', '', $f);
			self::$store['config']->$key = include_once $f;
		});
	}

	public static function get($module = null) {
		return $module ? self::$store[$module] : self::$store;
	}

	protected static function makeModule($module = '') {
		if (!isset(self::$store[$module])) {
			self::$store[$module] = new \Stdclass;
			loger('创建 module ' . $module);
		}
	}
}

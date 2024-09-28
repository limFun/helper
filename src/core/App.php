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
				return self::$store[$module]->$name = "\\app\\{$module}\\{$name}";
			case 'route':return self::parseRoute($name);

			default:
				break;
			}
		});
		// loger(self::$store);
	}

	protected static function parseRoute($name = '') {
		$routePath = [];

		//加载配置路由
		foreach (self::$store['config']->route as $class => $v) {
			foreach ($v as $method => $o) {
				$routePath['/' . $class . '/' . $method] = new \limRoute($o[0], $o[1], $o[2] ?? null, true);
			}
		}

		self::makeModule('route');
		$obj = "\\app\\route\\{$name}";
		//加载方法路由
		$ref = new \ReflectionClass($obj);
		$className = $ref->getName();
		$publicAttr = $ref->getAttributes('limRoute')[0] ?? null;
		$role = $publicAttr?->getArguments()['role'] ?? false;
		foreach ($ref->getMethods() as $method) {
			if ($method->class == $className) {

				if (!$method->isPublic()) {continue;} //非公开方法不参与路由

				if ($type = $method->getAttributes('limRoute')[0] ?? null) {
					$attr = $type->getArguments();
					if (isset($attr['path'])) {$path = $attr['path'];} //路由重置
					$role = $attr['role'] ?? false; //权限重置
				} else {
					$path = $name . '/' . $method->name;
				}
				$routePath['/' . $path] = new \limRoute($name, $method->name, $role ?? false);
			}
		}

		self::$store['routePath'] = $routePath;
		self::$store['route']->$name = $ref->newInstance();
		loger(self::$store);
	}

	protected static function parseEnv() {
		if (is_file(ROOT_PATH . '.env')) {
			$o = parse_ini_file(ROOT_PATH . '.env');
			foreach ($o as $k => $v) {self::$store['env'][$k] = $v;}
		}
	}

	protected static function parseConfig() {
		self::makeModule('config');
		ff(ROOT_PATH . 'config', function ($f) {
			//FPM环境不加载服务配置
			if (PHP_SAPI !== 'cli' && str_contains($f, 'service.php')) {return;}
			$key = preg_replace('/.+\/|.php/', '', $f);
			self::$store['config']->$key = include $f;
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

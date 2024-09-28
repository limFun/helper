<?
declare (strict_types = 1);
namespace lim;

class App {
	public static $store = [];

	public static function init() {
		if (empty(self::$store)) {
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
					//加载助手函数
					if (str_contains($fileName, 'helper.php')) {include_once $path;}
					break;
				}
			});
		}

		// loger(self::$store);
	}

	protected static function parseRoute($name = '') {
		$routePath = [];

		self::makeModule('router');
		$obj = "\\app\\route\\{$name}";
		//加载方法路由
		$ref = new \ReflectionClass($obj);
		$className = $ref->getName();
		$publicAttr = $ref->getAttributes('limRoute')[0] ?? null;
		$role = $publicAttr?->getArguments()['role'] ?? false;
		foreach ($ref->getMethods() as $method) {

			if ($method->class == $className) {

				if (!$method->isPublic()) {continue;} //非公开方法不参与路由
				$path = $name . '/' . $method->name;
				if ($type = $method->getAttributes('limRoute')[0] ?? null) {
					$attr = $type->getArguments();
					if (isset($attr['path'])) {$path = $attr['path'];} //路由重置
					$role = $attr['role'] ?? false; //权限重置
				}
				self::$store['routePath']['/' . $path] = new \limRoute($name, $method->name, $role ?? false);
			}
		}
		self::$store['router']->$name = $ref->newInstance();
	}

	protected static function parseEnv() {
		if (is_file(ROOT_PATH . '.env')) {
			$o = parse_ini_file(ROOT_PATH . '.env');
			foreach ($o as $k => $v) {self::$store['env'][$k] = $v;}
		}
	}

	protected static function parseConfig() {

		ff(ROOT_PATH . 'config', function ($f) {
			$key = preg_replace('/.+\/|.php/', '', $f);
			//FPM环境不加载服务配置
			if (PHP_SAPI !== 'cli' && $key == 'service') {return;}

			//加载配置路由
			if ($key == 'route') {
				$route = include $f;
				foreach ($route as $path => $o) {
					self::$store['routePath'][$path] = new \limRoute($o[0], $o[1], $o[2] ?? null, true);
				}
				return;
			}

			//加载路由校验规则
			if ($key == 'rule') {
				self::$store['routeRule'] = include $f;
				return;
			}

			//加载路由校验规则
			if ($key == 'schema') {
				self::$store['schema'] = include $f;
				return;
			}

			self::$store['config'][$key] = include $f;
		});
	}

	public static function get($module = null, $o = null) {
		switch ($module) {
		case 'redis':return \lim\Rs::connection($o ?? 'default');
		case 'http':return \lim\Http::url($o);
		case 'config':return self::getConfig($o);
		default:
			// code...
			return $module ? self::$store[$module] : self::$store;
		}
		// return $module ? self::$store[$module] : self::$store;
	}

	public static function getConfig($key = '') {
		if (!$key) {
			return self::$store['config'];
		}
		if (strpos($key, '.') === false) {
			return self::$store['config'][$key] ?? null;
		}

		$keys = explode('.', $key);
		$curr = self::$store['config'];
		foreach ($keys as $k) {
			$curr = $curr[$k] ?? null;
		}
		return $curr;
	}

	protected static function makeModule($module = '') {
		if (!isset(self::$store[$module])) {
			self::$store[$module] = new \Stdclass;
			// loger('创建 module ' . $module);
		}
	}
}

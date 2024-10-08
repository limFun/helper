<?
declare (strict_types = 1);
namespace lim;

class Response {

	public static function parse() {

		try {

			$uri = Request::routePath();
			if ($uri === '/') {return self::html('<h1>less is more</h1>');}

			//请求数据校验
			if (App::$store['routeRule']['public'] ?? null) {
				check($data, App::$store['routeRule']['public'])->stop();
			}

			if ($rule = App::$store['routeRule']['path'][$uri] ?? null) {
				check($data, $rule)->stop();
			}

			//完全路径
			if ($route = App::get('routePath')[$uri] ?? null) {goto result;}

			$uriArr = explode('/', $uri);
			$len = count($uriArr);
			//左匹配
			if ($len > 2) {
				$uri = '/' . $uriArr[1] . '/*';
				if ($route = App::get('routePath')[$uri] ?? null) {goto result;}
			}
			return self::html('<h1>less is more</h1>');

			result:
			$data = Request::all();

			//权限判断
			if ($route->role) {
				if (!$token = Request::header('token')) {return self::error('Token必填');}
				if (!$user = token($token, true)) {return self::error('Token错误');}
				if (!isset($user['role'])) {return self::error('Token异常');}
				if ($route->role != $user['role']) {return self::error('您无权访问');}
			}

			//除开路由方法外 其它模块必须是静态方法
			if ($route->static) {
				$result = $route->handler::{$route->method}($data);
			} else {
				$result = App::get('router')->{$route->handler}->{$route->method}($data);
			}

			return self::success($result);
		} catch (\Throwable $e) {
			return self::error($e->getMessage(), $e->getCode());
		}

	}

	public static function html($result = '') {
		if (PHP_SAPI == 'cli') {
			curr('response')->header('Content-Type', 'text/html;charset=utf-8');
			return curr('response')->end($result);
		} else {
			header('Content-Type:text/html;charset=utf-8');
			die($result);
		}
	}

	public static function json($result = []) {

		if (PHP_SAPI == 'cli') {
			curr('response')->header('Access-Control-Allow-Origin', '*');
			curr('response')->header('Access-Control-Allow-Methods', '*');
			curr('response')->header('Access-Control-Allow-Headers', '*');
			curr('response')->header('Content-Type', 'application/json;charset=utf-8');
			return curr('response')->end(json_encode($result, 256));
		} else {
			header('Access-Control-Allow-Origin:*');
			header('Access-Control-Allow-Methods:*');
			header('Access-Control-Allow-Headers:*');
			header('Content-Type:application/json;charset=utf-8');
			die(json_encode($result, 256));
		}
	}

	public static function success($result = []) {
		self::json(['code' => 200, 'message' => 'ok', 'result' => $result]);
	}

	public static function error($message = '', $code = 300) {
		self::json(['code' => $code, 'message' => $message]);
	}

	public static function view($info) {
		// print_r([debug_backtrace(), $info]);
		$html = file_get_contents(ROOT_PATH . 'app/view/admin/aa.html');
		die($html);
	}
}
<?
declare (strict_types = 1);
namespace lim;

class Response {

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
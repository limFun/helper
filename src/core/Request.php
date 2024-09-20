<?
declare (strict_types = 1);
namespace lim;

class Request {
	public static function __callStatic($method, $argv) {}

	public static function info() {
		return PHP_SAPI == 'cli' ? curr('request') : $_SERVER;
	}

	public static function get($key = null, $value = null) {
		$get = PHP_SAPI == 'cli' ? curr('request')->get ?? [] : $_GET ?? [];
		return $key ? $get[$key] ?? $value : $get;
	}

	public static function post($key = null, $value = null) {
		if (PHP_SAPI == 'cli') {
			$req = curr('request');
			$post = ($req->header['content-type'] ?? null) == 'application/json' ? json_decode($req->getContent(), true) : $req->post ?? [];
		} else {
			$post = ($_SERVER['CONTENT_TYPE'] ?? null) == 'application/json' ? json_decode(file_get_contents('php://input'), true) : $_POST ?? [];
		}
		return $key ? $post[$key] ?? $value : $post ?? [];
	}

	public static function files($key = null, $value = null) {
		$files = PHP_SAPI == 'cli' ? curr('request')->files ?? [] : $_FILES ?? [];
		return $key ? $files[$key] ?? $value : $files;
	}

	public static function header($key = null, $value = null) {
		if (PHP_SAPI == 'cli') {
			$header = curr('request')->header ?? [];
		} else {
			$header = [];
			foreach ($_SERVER as $k => $v) {
				$i = strtolower($k);
				if (str_contains($i, 'http')) {
					$header[str_replace('_', '-', substr($i, 5))] = $v;
				}
			}
		}
		return $key ? $header[$key] ?? $value : $header ?? [];
	}

	public static function server($key = null, $value = null) {
		if (PHP_SAPI == 'cli') {
			$server = curr('request')->server ?? [];
		} else {
			$server = [];
			foreach ($_SERVER as $k => $v) {
				$i = strtolower($k);
				if (!str_contains($i, 'http')) {
					$server[$i] = $v;
				}
			}
		}
		return $key ? $server[$key] ?? $value : $server ?? [];
	}

	public static function all() {
		return array_merge(self::get(), self::post());
	}

	public static function path() {
		return PHP_SAPI == 'cli' ? substr(curr('request')->server['path_info'], 1) : substr($_SERVER['PATH_INFO'] ?? '/', 1);
	}

	public static function method() {
		return PHP_SAPI == 'cli' ? curr('request')->server['request_method'] : $_SERVER['REQUEST_METHOD'];
	}

}

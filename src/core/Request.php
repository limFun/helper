<?
declare (strict_types = 1);
namespace lim;

class Request {
	public static function __callStatic($method, $argv) {return (new static )->$method(...$argv);}
	public function __call($method, $argv) {
		switch (strtolower($method)) {
		case 'get':return PHP_SAPI == 'cli' ? curr('request')->get : $_GET;
		case 'post':return PHP_SAPI == 'cli' ? curr('request')->post : $_POST;
		case 'all':return PHP_SAPI == 'cli'
			? array_merge(curr('request')->get ?? [], (curr('request')->header['content-type'] ?? null) === 'application/json' ? json_decode(curr('request')->getContent(), true) : curr('request')->post ?? [])
			: array_merge($_GET, ($_SERVER['CONTENT_TYPE'] ?? null) === 'application/json' ? json_decode(file_get_contents('php://input'), true) : $_POST);
		case 'path':return PHP_SAPI == 'cli' ? substr(curr('request')->server['path_info'], 1) : substr($_SERVER['PATH_INFO'], 1);
		case 'file':break;
		case 'header':
			$key = $argv[0] ?? null;
			$value = $argv[1] ?? null;
			if (PHP_SAPI == 'cli') {
				return $key ? curr('request')->header[$key] ?? $value : curr('request')->header;
			} else {
				$header = [];
				foreach ($_SERVER as $k => $v) {
					if (strpos($k, 'HTTP_') === 0) {$header[strtolower(str_replace(['HTTP_', '_'], ['', '-'], $k))] = $v;}
				}
				return $key ? $header[$key] ?? $value : $header;
			}
		default:break;
		}
	}
}
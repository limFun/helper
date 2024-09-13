<?
declare (strict_types = 1);
namespace lim\request;

class Fpm {
	protected $data = [];
	function __construct() {
		foreach ($_SERVER as $k => $v) {
			$i = strtolower($k);
			$this->data[$i] = $v;
		}
		print_r($this);
	}
	public function __call($method, $argv) {
		switch (strtolower($method)) {
		case 'get':return $_GET;
		case 'post':return $_POST;
		case 'all':return array_merge($_GET, ($_SERVER['CONTENT_TYPE'] ?? null) === 'application/json' ? json_decode(file_get_contents('php://input'), true) : $_POST);
		case 'path':return substr($_SERVER['PATH_INFO'], 1);
		case 'file':break;
		case 'header':
			$key = $argv[0] ?? null;
			$value = $argv[1] ?? null;
			$header = [];
			foreach ($_SERVER as $k => $v) {
				if (strpos($k, 'HTTP_') === 0) {$header[strtolower(str_replace(['HTTP_', '_'], ['', '-'], $k))] = $v;}
			}
			return $key ? $header[$key] ?? $value : $header;
		case 'getdata':return $this->data;
		default:break;
		}
	}
}

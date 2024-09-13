<?
declare (strict_types = 1);
namespace lim;

class Request {
	public static function __callStatic($method, $argv) {
		return (new RequestParse())->$method(...$argv);
	}
}

class RequestParse {
	public $data = [
		'header' => [],
		'server' => [],
		'cookie' => [],
		'get' => [],
		'post' => [],
		'files' => [],
	];

	function __construct() {
		if (PHP_SAPI == 'cli') {
			$request = curr('request');
			$this->data['header'] = $request->header ?? [];
			$this->data['server'] = $request->server ?? [];
			$this->data['cookie'] = $request->cookie ?? [];
			$this->data['files'] = $request->files ?? [];
			$this->data['get'] = $request->get ?? [];
			$this->data['post'] = ($this->data['header']['content-type'] ?? null) == 'application/json' ? json_decode($request->getContent(), true) : $request->post ?? [];
		} else {
			foreach ($_SERVER as $k => $v) {
				$i = strtolower($k);
				if ($i == 'http_cookie') {
					$t = explode(';', $v);
					foreach ($t as $c) {
						[$kk, $vv] = explode('=', trim($c));
						$this->data['cookie'][$kk] = $vv;
					}
				} elseif (str_contains($i, 'http')) {
					$this->data['header'][str_replace('_', '-', substr($i, 5))] = $v;
				} else {
					$this->data['server'][$i] = $v;
				}
			}
			$this->data['get'] = $_GET ?? [];
			$this->data['post'] = ($this->data['header']['content-type'] ?? null) == 'application/json' ? json_decode(file_get_contents('php://input'), true) : $_POST ?? [];
			$this->data['files'] = $_FILES;
		}
	}

	public function __call($method, $argv) {
		switch (strtolower($method)) {
		case 'get':return $this->data['get'];
		case 'post':return $this->data['post'];
		case 'all':return array_merge($this->data['get'], $this->data['post']);
		case 'path':return substr($this->data['server']['path_info'], 1);
		case 'file':$this->data['files'];
		case 'header':
			$key = $argv[0] ?? null;
			$value = $argv[1] ?? null;
			return $key ? $this->data['header'][$key] ?? $value : $this->data['header'];
		default:return $this;

		}
	}

}
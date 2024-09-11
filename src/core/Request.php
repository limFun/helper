<?
declare (strict_types = 1);
namespace lim;
use Swoole\Coroutine;

class Request {

	public static function __callStatic($method, $argv) {
		return (new static )->$method();
	}

	public function __call($method, $argv) {
		switch (strtolower($method)) {
		case 'get':return PHP_SAPI == 'cli' ? Coroutine::getContext()['request']->get : $_GET;
		case 'post':return PHP_SAPI == 'cli' ? Coroutine::getContext()['request']->post : $_POST;
			break;
		case 'all':
			break;
		case 'file':
			break;
		case 'header':
			if (PHP_SAPI == 'cli') {
				return Coroutine::getContext()['request']->header;
			} else {
				$header = [];
				foreach ($_SERVER as $key => $value) {
					if (strpos($key, 'HTTP_') === 0) {
						$headerKey = str_replace('HTTP_', '', $key);
						$headerKey = str_replace('_', '-', $headerKey);

						$header[strtolower($headerKey)] = $value;

					}
				}
				return $header;
			}
			break;
		default:
			break;
		}
	}
}
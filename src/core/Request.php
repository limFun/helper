<?
declare (strict_types = 1);
namespace lim;
use Swoole\Coroutine;

class Request {

	public static function __callStatic($method, $argv) {
		return new static;
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
			break;
		default:
			break;
		}
	}
}
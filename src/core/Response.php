<?
declare (strict_types = 1);
namespace lim;

class Response {
	public static function __callStatic($method, $argv) {
		return new static;
	}
	public function __call($method, $argv) {
		switch (strtolower($method)) {
		case 'html':
			break;
		case 'json':
			break;
		case 'send':
			break;
			break;
		default:
			break;
		}
	}
}
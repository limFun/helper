<?
declare (strict_types = 1);
namespace lim;

class Store extends \Stdclass {

	public static $store = [];

	public static function store() {
		$cid = PHP_SAPI == 'cli'?\Swoole\Coroutine::getCid() : 0;
		if (!isset(self::$store[$cid])) {
			self::$store[$cid] = new self;
		}
		return self::$store[$cid];
	}

	public static function clear() {
		$cid = PHP_SAPI == 'cli'?\Swoole\Coroutine::getCid() : 0;
		unset(self::$store[$cid]);
	}
}
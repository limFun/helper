<?
declare (strict_types = 1);
namespace lim;

class Store {
	private static $store;
	public static function init() {
		return self::$store ? self::$store : self::$store = new StoreProxy();
	}
}

class StoreProxy extends \Stdclass {}
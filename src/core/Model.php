<?
declare (strict_types = 1);
namespace lim;

class Model {

	protected $table = null;

	protected $schema;

	function __construct() {
		//解析数据表
		$objName = basename(str_replace('\\', '/', static::class));
		$table = '';
		for ($i = 0; $i < strlen($objName); $i++) {
			$char = $objName[$i];
			if (ctype_upper($char) && $i > 0) {
				$table .= '_' . strtolower($char);
			} else {
				$table .= strtolower($char);
			}
		}

		$this->table ??= $table;
		$this->schema = config('model.' . $table);
	}

	public static function __callStatic($method, $argv) {

		switch (strtolower($method)) {
		case 'handler':
		case 'query':
			$class = new static;
			return Db::table($class->table);
		default:
			// code...
			break;
		}

		$res = call_user_func_array([self::handler(), $method], $argv);
		return $res;
	}

	public static function list($o = []) {
		$h = self::handler();
		static::listBefore($h, $o);
		$res['count'] = $h->count('id');
		$res['list'] = $h->select();
		static::listAfter($res);
		return $res;
	}
	public static function listBefore(&$h, $o) {
		$page = self::arrayOnce($o, 'page', 1);
		$limit = self::arrayOnce($o, 'limit', 10);
		$h->where($o)->page($page, $limit)->field('id,title');
	}

	public static function listAfter($o) {
		// code...
	}

	public static function arrayOnce(&$o, $k = '', $v = '') {
		$t = $o[$k] ?? $v;
		unset($o[$k]);
		return $t;
	}

}

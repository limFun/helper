<?
declare (strict_types = 1);
namespace lim;

class Model {
	protected $table = null;

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
		loger(config('model.' . $table));
	}

	public static function __callStatic($method, $argv) {
		$class = new static;
		$a = Db::table($class->table);
		$res = call_user_func_array([$a, $method], $argv);
		return $res;
	}

	public function create($option) {

	}

}

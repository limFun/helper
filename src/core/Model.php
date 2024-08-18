<?
declare (strict_types = 1);
namespace lim;

class Model extends \think\Model
{

	public static function creater($o = []) {

		$handler = new static;

		$field = $handler->getFields();

		$keys = array_keys($field);

		$insert = [];

		foreach ($o as $k => $v) {
			if (in_array($k, $keys)) {
				$insert[$k] = $v;
			}
		}

		$id = $handler::insert($insert);

		return $id;
	}
}
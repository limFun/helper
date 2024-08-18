<?
declare (strict_types = 1);
namespace lim;
/**
 *
 */
class Console {

	public static function run($o) {
		array_shift($o);

		$method = array_shift($o);

		switch ($method) {
		case 'fn':
			$fn = array_shift($o);
			$fn(...$o);
			break;
		case 'server':

			break;
		default:
			loger('lim');
			break;
		}
	}
}
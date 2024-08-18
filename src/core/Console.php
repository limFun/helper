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
		case 'dev':
			shell_exec('php -S 0.0.0.0:' . env('APP_PORT', 99) . ' -t ' . ROOT_PATH . '/public');
			break;
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
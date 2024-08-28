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
			shell_exec('php -S 0.0.0.0:' . env('APP_PORT', 9999) . ' -t ' . ROOT_PATH . '/public');
			break;
		case 'fn':
			\think\facade\Db::setConfig(config('db'));
			$fn = array_shift($o);
			$fn(...$o);
			break;
		case 'server':
			Server::run();
			break;
		case 'task':
			if (!$c = array_shift($o)) {
				return loger('任务参数是必须的');
			}
			if (!str_contains($c, '.')) {
				return loger('参数规则：类.方法 参数1 参数2 ……');
			}
			[$class, $action] = explode('.', $c);
			$obj = '\\app\\task\\' . ucfirst($class);
			$obj::$action(...$o);
			break;
		default:
			loger(['method' => $method, 'option' => $o]);
			break;
		}

	}
}
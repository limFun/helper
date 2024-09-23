<?
declare (strict_types = 1);
namespace lim;

class Console {
	public static function run($o) {
		array_shift($o);
		$method = array_shift($o);
		switch ($method) {
		case 'dev':return shell_exec('php -S 0.0.0.0:' . env('APP_PORT', 11111) . ' -t ' . ROOT_PATH . '/public');
		case 'fn':
			$fn = array_shift($o);
			run(fn() => $fn(...$o));
			break;
		case 'init':return run(fn() => Db::schema());
		case 'server':return Server::run();
		case 'task':
			$c = array_shift($o) ?? '';
			if (str_contains($c, '.')) {
				[$class, $action] = explode('.', $c);
				$obj = '\\app\\task\\' . ucfirst($class);
				$obj::$action(...$o);
			} elseif (str_contains($c, '-run')) {

			} else {
				return loger("\n\t运行任务： -run\n\t测试任务：类.方法 参数1 参数2");
			}
			break;
		default:loger(['method' => $method, 'option' => $o]);
			break;
		}
	}
}
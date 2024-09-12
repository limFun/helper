<?
declare (strict_types = 1);
namespace lim;
use function Swoole\Coroutine\run;
use Swoole\Coroutine;
use Swoole\Coroutine\Http\Server as CoServer;
use Swoole\Process\Manager;
use Swoole\Process\Pool;

class Server {

	protected $pm = null;

	public static function fpm() {
		try {
			$pathArr = explode('/', Request::path());
			if (count($pathArr) === 2) {
				$obj = '\\app\\route\\' . $pathArr[0];
				Response::success($obj::init()->__before()->register($pathArr[1], Request::all()));
			} else {
				Response::html('lim');
			}
		} catch (\Throwable $e) {
			Response::error($e->getMessage(), $e->getCode());
		}
	}

	public static function run() {
		cli_set_process_title('Manager');
		$pm = new Manager();
		$pm->add(function (Pool $pool, int $workerId) {
			cli_set_process_title('CoServer');
			$server = new CoServer('0.0.0.0', (int) env('APP_PORT', 11111), false);
			$server->handle('/', function ($request, $response) {
				Coroutine::getContext()['request'] = $request;
				Coroutine::getContext()['response'] = $response;
				if ($request->server['path_info'] == '/favicon.ico') {return $response->end();}
				try {
					$pathArr = explode('/', Request::path());
					if (count($pathArr) === 2) {
						$obj = '\\app\\route\\' . $pathArr[0];
						Response::success($obj::init()->__before()->register($pathArr[1], Request::all()));
					} else {
						Response::html('lim');
					}
				} catch (\Throwable $e) {
					return Response::error($e->getMessage(), $e->getCode());
				}
			});
			$server->start();

		}, true);

		// $pm->add(function (Pool $pool, int $workerId) {
		// 	cli_set_process_title('HttpServer');
		// 	$http = new \Swoole\Http\Server('0.0.0.0', 9501);

		// 	$http->on('Request', function ($request, $response) {
		// 		$response->header('Content-Type', 'text/html; charset=utf-8');
		// 		$response->end('<h1>Hello Swoole. #' . rand(1000, 9999) . '</h1>');
		// 	});

		// 	$http->start();

		// });

		// $pm->add(function (Pool $pool, int $workerId) {
		// 	cli_set_process_title('Timer');
		// 	\Swoole\Timer::tick(1000 * 5, fn() => loger(date('Y-m-d H:i:s')));
		// }, true);

		$pm->start();
	}

}
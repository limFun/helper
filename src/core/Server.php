<?
declare (strict_types = 1);
namespace lim;

use function Swoole\Coroutine\run;
use Swoole\Coroutine\Http\Server as CoServer;
use Swoole\Process\Manager;
use Swoole\Process\Pool;
use think\facade\Db;

class Server {

	protected $pm = null;

	function __construct() {

	}

	public static function run() {

		cli_set_process_title('Manager');

		$pm = new Manager();

		$pm->add(function (Pool $pool, int $workerId) {
			cli_set_process_title('CoServer');
			$server = new CoServer('0.0.0.0', (int) env('APP_PORT', 9999), false);
			$server->handle('/', function ($request, $response) {

				$response->header('Access-Control-Allow-Origin', '*');
				$response->header('Access-Control-Allow-Methods', '*');
				$response->header('Access-Control-Allow-Headers', '*');
				$response->header('Content-Type', 'application/json;charset=utf-8');

				if ($request->server['path_info'] == '/favicon.ico' || $request->server['request_uri'] == '/favicon.ico') {
					$response->end();
					return;
				}

				try {
					$uri = substr($request->server['path_info'], 1);

					$res = explode('/', $uri);

					if (count($res) != 2) {
						apiErr('路由错误');
					}

					[$class, $method] = $res;

					$obj = '\\app\\route\\' . $class;

					$data = array_merge($request->get ?? [], $request->post ?? []);
					Db::setConfig(config('db'));
					$result = $obj::init()->__before()->register($method, $data);
					$response->end(json_encode(['code' => 200, 'message' => 'success', 'result' => $result]));
				} catch (\Exception $e) {
					$response->end(json_encode(['code' => $e->getCode(), 'message' => $e->getMessage()]));
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
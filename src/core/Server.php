<?
declare (strict_types = 1);
namespace lim;
use function Swoole\Coroutine\Http\request;
use Swoole\Process;

class Server {
	protected $handler = null;

	protected $option = null;

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
		(new self)->http()->watch()->start();
	}

	public function watch() {
		$proces = new Process(function () {
			cli_set_process_title($this->option['name'] . '-Watcher');
			$lt = time();
			while (true) {
				ff(ROOT_PATH . 'app', function ($f) use (&$lt) {
					if (filemtime($f) > $lt) {
						loger($f . ' change ');
						$lt = time();
						$this->handler->reload();
					}
				});
				sleep(1);
			}
		}, false, 1, true);

		$this->handler->addProcess($proces);

		return $this;
	}

	public function http() {
		$this->option = config('service');
		$this->handler = new \Swoole\WebSocket\Server('0.0.0.0', $this->option['port'], SWOOLE_PROCESS);
		$this->handler->set($this->option['option']);
		$this->handler->on('start', array($this, 'onStart'));
		$this->handler->on('managerstart', array($this, 'onManagerstart'));
		$this->handler->on('beforeReload', array($this, 'onBeforeReload'));
		$this->handler->on('afterReload', array($this, 'onAfterReload'));
		$this->handler->on('workerStart', array($this, 'onWorkerStart'));
		$this->handler->on('request', array($this, 'onRequest'));
		$this->handler->on('open', array($this, 'onOpen'));
		$this->handler->on('message', array($this, 'onMessage'));
		return $this;
	}

	public function start() {
		$this->handler->start();
	}

	public function onStart() {
		cli_set_process_title($this->option['name'] . '-Server');
		loger($this->option['name'] . '-Server Start');
	}

	public function onManagerstart() {
		cli_set_process_title($this->option['name'] . '-Manager');
	}

	public function onBeforeReload() {
		// Helper::import('config,env');
		// loger($this->option['name'] . '-Worker BeforeReload');
	}

	public function onAfterReload() {
		// loger('onAfterReload ' . config('server.name'));
	}

	public function onWorkerStart(\Swoole\Server $server, int $workerId) {
		cli_set_process_title($this->option['name'] . '-Worker-' . $workerId);
		// loger($this->option['name'] . '-Worker' . $workerId . ' Start');
		// loger(get_included_files());
		// Helper::import('helper,api');

	}

	public function onRequest($request, $response) {
		$response->header('Server', 'LimServer');
		Context::set('request', $request);
		Context::set('response', $response);
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
			Response::error($e->getMessage(), $e->getCode());
		}
		Context::clear();
	}

	public function onOpen($server, $request) {
		loger($request);
	}

	public function onMessage($server, $frame) {
		loger($frame);
		$server->push($frame->fd, $frame->data);
	}

}
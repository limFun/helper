<?
declare (strict_types = 1);
namespace lim;

use function Swoole\Coroutine\Http\request;
use Swoole\Process;

class Server
{
    protected $handler = null;

    protected $option = null;

    public static function run()
    {
        (new self)->server()->task()->handler->start();
    }

    public function watch()
    {
        $proces = new Process(function () {
            cli_set_process_title($this->option['name'] . '-Watcher'); $lt = time();
            while (true) {
                ff(ROOT_PATH . 'app', function ($f) use (&$lt) {
                    if (filemtime($f) > $lt) {
                        loger($f . ' change ');
                        $lt = time();
                        $this->handler->reload();
                    }
                }); sleep(1);
            }
        }, false, 1, true);
        $this->handler->addProcess($proces);return $this;
    }

    function task()
    {
        $proces = new Process(function () {
            cli_set_process_title($this->option['name'] . '-Tasker');
            Tasker::run();
            loger('task');
        }, false, 1, true);
        $this->handler->addProcess($proces);
        return $this;
    }

    public function server()
    {
        $this->option  = config('service');
        $this->handler = new \Swoole\WebSocket\Server('0.0.0.0', $this->option['port'], SWOOLE_PROCESS);
        $this->handler->set($this->option['option']);
        $on = [
            'start'        => 'start',
            'managerStart' => 'managerStart',
            'beforeReload' => 'beforeReload',
            'afterReload'  => 'afterReload',
            'workerStart'  => 'workerStart',
            'request'      => 'request',
            'open'         => 'open',
            'message'      => 'message',
            'close'        => 'close',
        ];
        foreach (array_merge($on, $this->option['on'] ?? []) as $on => $call) {
            $this->handler->on($on, is_array($call) ? $call : [$this, $call]);
        }
        return $this;
    }

    public function start()
    {cli_set_process_title($this->option['name'] . '-Server');}
    public function managerStart()
    {cli_set_process_title($this->option['name'] . '-Manager');}
    public function beforeReload()
    {}
    public function afterReload()
    {}
    public function workerStart($server, $workerId)
    {cli_set_process_title($this->option['name'] . '-Worker-' . $workerId);}

    public function request($request, $response)
    {
        $response->header('Server', 'LimServer');

        Context::set('request', $request);
        Context::set('response', $response);
        Context::set('server', $this->handler);
        if ($request->server['path_info'] == '/favicon.ico' || $request->server['request_method'] == 'OPTIONS') {
            $response->header('Access-Control-Allow-Origin', '*');
            $response->header('Access-Control-Allow-Methods', '*');
            $response->header('Access-Control-Allow-Headers', '*');
            return $response->end();
        }
        self::response();
    }

    public static function response()
    {
        return Response::parse();
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

    public function open($server, $request)
    {
        if ($token = Request::get('token')) {
            if ($user = token($token, true)) {
                redis()->zadd('message:user', $user['id'], $request->fd);
                loger("用户{$user['id']} => {$request->fd} 上线");
            }
        }

    }

    public function message($server, $frame)
    {Message::parse($server, $frame);}

    public function close($server, $fd, $reactorId)
    {
        // loger($fd);
        // $uid = redis()->zscore('message:user', $fd);
        // redis()->zrem('message:user', $fd);
        // loger("用户{$uid} => {$fd} 下线");
    }

}

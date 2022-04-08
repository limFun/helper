<?php
declare (strict_types = 1);
namespace lim\Server;

/**
 * @Author: Wayren
 * @Date:   2022-03-29 12:12:06
 * @Last Modified by:   Wayren
 * @Last Modified time: 2022-04-07 20:04:53
 */

use function Swoole\Coroutine\Http\get;
use function Swoole\Coroutine\Http\post;
use function Swoole\Coroutine\run;
use Swoole\Coroutine;
use swoole\Timer;

class WebsocketServer
{
    function __construct($daemonize = false)
    {
        $file = __LIM__ . '/config/websocket.php';
        
        if (!is_file($file)) {
            echo "配置文件不存在\n";
            return;
        }
        $this->opt = include $file;
        $this->opt['daemonize']=$daemonize ;
        $this->start();
    }

    public function start()
    {

        if (!is_dir(__LIM__ . '/runtime')) {
            mkdir(__LIM__ . '/runtime');
        }

        Coroutine::set(['enable_deadlock_check' => null, 'hook_flags' => SWOOLE_HOOK_ALL]);

        $config = [
            'reactor_num'        => 1,
            'enable_coroutine'   => true,
            'pid_file'           => __LIM__ . '/runtime/websocket.pid',
            'log_level'          => SWOOLE_LOG_WARNING,
            'hook_flags'         => SWOOLE_HOOK_ALL,
            'max_wait_time'      => 1,
            'reload_async'       => true,
            'package_max_length' => 5 * 1024 * 1024,
            'daemonize'          => $this->opt['daemonize'] ?? false,
        ];

        $this->server = new \Swoole\WebSocket\Server("0.0.0.0", $this->opt['port']);
        $this->server->set($config);
        $this->server->on('start', fn() => cli_set_process_title($this->opt['name']));
        $this->server->on('managerstart', [$this, 'managerstart']);
        $this->server->on('WorkerStart', [$this, 'WorkerStart']);
        // self::$server->on('task', [$this, 'task']);
        $this->server->on('request', [$this, 'request']);
        $this->server->on('message', [$this, 'message']);
        $this->server->start();
    }

    function request($request, $response)
    {
        $response->header('Content-Type', 'application/json');
        $response->header("Access-Control-Allow-Origin", "*");
        $response->header("Access-Control-Allow-Methods", "*");
        $response->header("Access-Control-Allow-Headers", "*");
        if ($request->server['path_info'] == '/favicon.ico' || $request->server['request_uri'] == '/favicon.ico') {
            $response->end();
            return;
        }

        $vars = array_merge($request->post ?? [], json_decode($request->getContent(), true) ?? [], $request->get ?? []);
        $path = $request->server['request_uri'];
    
        if (!$router = $this->route[$path]??null) {
            return $response->end(json_encode(['code'=>300,'message'=>'接口不存在'], 256));
        }
       
        list($app,$method) = $router;

        $res = (new $app())->$method($vars);
    
        return $response->end(json_encode($res, 256));
    }

    function managerstart($server)
    {
        cli_set_process_title($this->opt['name'] . '-Manager');
        // wlog ("服务启动成功");
    }

    function WorkerStart($server, int $workerId)
    {


        $file = __LIM__ . '/config/route.php';
        
        if (!is_file($file)) {
            echo "配置文件不存在\n";
            return;
        }
        $this->route = include $file;

        try {
            Timer::clearAll();
            if (function_exists('opcache_reset')) {
                opcache_reset();
            }
            if ($server->taskworker) {
                $id = $workerId - $server->setting['worker_num'];

                if ($id == 0) {
                    // print_r(get_defined_constants(true)['user']);
                    // print_r(get_included_files());
                }
                cli_set_process_title($this->opt['name'] . '-Tasker');
            } else {
                cli_set_process_title($this->opt['name'] . '-Worker');
            }
        } catch (\Swoole\ExitException $e) {
            echo ($e->getStatus());
        }

    }

    function task()
    {

    }

    function message()
    {

    }
}
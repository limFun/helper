<?php
namespace lim\Server;

/**
 * @Author: Wayren
 * @Date:   2022-03-29 12:12:06
 * @Last Modified by:   Wayren
 * @Last Modified time: 2022-04-12 11:28:20
 */

use function Swoole\Coroutine\Http\get;
use function Swoole\Coroutine\Http\post;
use function Swoole\Coroutine\run;
use Swoole\Coroutine\Http\Client\Exception;
use \swoole\Timer;

class Gateway
{

    public static $Gateway, $service = null;

    function __construct($daemonize = false)
    {
        $file = __LIM__ . '/config/gateway.php';

        if (!is_file($file)) {
            echo "配置文件不存在\n";
            return;
        }

        $opt = include $file;
        $this->widthOption($opt);
        $this->run($daemonize);
    }

    public function widthOption($opt = [])
    {
        // self::$service = $service;
        $this->ip   = (int) $opt['ip'] ?? null;
        $this->port = (int) $opt['port'] ?? 9500;

        foreach ($opt['service'] ?? [] as $serviceType => $list) {

            foreach ($list as $serviceName => $res) {
                $this->service[$serviceName] = ['type' => $serviceType, 'url' => $res['url']];
                // print_r([$serviceName,$serviceType,$res]);
                foreach ($res['list'] as $api => $path) {
                    $api = is_numeric($api) ? $path : $api;

                    $arr = explode('=>', $api);
                    if (isset($this->apiList[$api])) {
                        echo "网关接口存在重复项:" . $api . PHP_EOL;
                    }
                    switch ($serviceType) {
                        case 'http':
                            $this->apiList[$api] = ['type' => $serviceType, 'url' => $res['url'] . $path];
                            break;
                        case 'rpc':
                            $this->apiList[$api] = ['type' => $serviceType, 'name' => $serviceName, 'method' => $path, 'url' => $res['url']];
                    }
                }
            }

        }
        // print_r($this);
        return $this;
    }

    function run($daemonize = false)
    {
        if (!is_dir(__LIM__ . '/runtime')) {
            mkdir(__LIM__ . '/runtime');
        }

        \Swoole\Coroutine::set(['enable_deadlock_check' => null, 'hook_flags' => SWOOLE_HOOK_ALL]);

        $config = [

            'reactor_num'        => 1,
            // 'worker_num'            => (int) WORKER_NUM,
            // 'task_worker_num'       => (int) TASK_WORKER_NUM,
            // 'task_enable_coroutine' => true,
            'enable_coroutine'   => true,
            'pid_file'           => __LIM__ . '/runtime/gateway.pid',
            'log_level'          => SWOOLE_LOG_WARNING,
            'hook_flags'         => SWOOLE_HOOK_ALL,
            'max_wait_time'      => 1,
            'reload_async'       => true,
            'package_max_length' => 5 * 1024 * 1024,
            // 'max_coroutine'         => (int) MAX_COROUTINE,
            'daemonize'          => $daemonize,
        ];

        $this->server = new \Swoole\WebSocket\Server("0.0.0.0", $this->port);
        $this->server->set($config);
        $this->server->on('start', fn() => cli_set_process_title('Gateway'));
        $this->server->on('managerstart', [$this, 'managerstart']);
        $this->server->on('WorkerStart', [$this, 'WorkerStart']);
        // self::$server->on('task', [$this, 'task']);

        $this->server->on('receive', [$this, 'receive']);
        $this->server->on('request', [$this, 'request']);
        $this->server->on('message', [$this, 'message']);
        $this->server->start();
    }

    function managerstart($server)
    {
        cli_set_process_title('Manager');
    }

    function WorkerStart($server, int $workerId)
    {
        Timer::clearAll();
        if (function_exists('opcache_reset')) {
            opcache_reset();
        }

        if ($server->taskworker) {
            // $id = $workerId - $server->setting['worker_num'];
            cli_set_process_title('Tasker');
        } else {
            cli_set_process_title('Worker');
        }
    }

    function task()
    {

    }

    function receive($server, $fd, $reactorId, $data)
    {
        print_r([$fd, $data]);
    }

    public function request($request, $response)
    {
        $response->header('Content-Type', 'application/json');
        $response->header("Access-Control-Allow-Origin", "*");
        $response->header("Access-Control-Allow-Methods", "POST, GET, OPTIONS");
        $response->header("Access-Control-Allow-Headers", "*");

        if ($request->server['path_info'] == '/favicon.ico' || $request->server['request_uri'] == '/favicon.ico') {
            $response->end();
            return;
        }

        $result = $this->parseReq($request);
        return $response->end($result);
    }

    public function message($server, $frame)
    {
        $result = $this->parseReq($frame);
        return $server->push($frame->fd, $result);
    }

    /**
     * 解析请求
     * @Author   Wayren
     * @DateTime 2022-04-08T16:38:03+0800
     * @param    [type]                   $req [description]
     * @return   [type]                        [description]
     */
    private function parseReq($req)
    {
        $header = [];
        if ($req instanceof \Swoole\WebSocket\Frame) {
            // print_r($req);
            $tmp  = json_decode($req->data, true);
            $path = $tmp['method'] ?? null;
            $data = $tmp['data'] ?? [];

            unset($tmp['method'], $tmp['data']);

            $header               = array_merge($tmp, ['fd' => $req->fd]);
            $this->request_method = 'POST';
        } else {
            if (!$post = json_decode($req->getContent(), true)) {
                $post = $req->post ?? [];
            }
            $data                 = array_merge($post, $req->get ?? []);
            $path                 = $req->server['request_uri'];
            $this->request_method = $req->server['request_method'];

            if (isset($req->header['number'])) {
                $header['number'] = $req->header['number'];
            }
            if (isset($req->header['authorization'])) {
                $header['authorization'] = $req->header['authorization'];
            }
            if (isset($req->header['user'])) {
                $header['user'] = $req->header['user'];
            }

            if (isset($req->header['fd'])) {
                return $this->server->push($req->header['fd'], json_encode($data, 256));
            }
        }
        // print_r([$path, $data, $header]);

        $name = strstr(substr($path, 1), '/', true);

        $start = microtime(true);

        if ($api = $this->apiList[$path] ?? null) {
            $res = match($api['type']) {
                'http' => $this->http($api['url'], $data, $header),
                'rpc'  => $this->rpc($api['url'], $api['method'], [$data], $header),
            default=> [],
            };

        } else {
            if (!$srv = $this->service[$name] ?? null) {
                return json_encode(['code' => -1, 'message' => '服务不存在'], 256);
            }

            $res = match($srv['type']) {
                'http' => $this->http($srv['url'] . $path, $data, $header),
                'rpc'  => $this->rpc($srv['url'], $path, [$data], $header),
            default=> [],
            };
        }

        $res['useTime'] = intval((microtime(true) - $start) * 1000);
        return json_encode($res, 256);
    }

    private function http($url, $params, $headers = [])
    {
        try {
            if ($this->request_method == 'GET') {
                $res = get($url . '?' . http_build_query($params), null, $headers);
            } else {
                $res = post($url, $params, null, $headers);
            }
        } catch (Exception $e) {
            return ['code' => -1, 'message' => '服务连接失败'];
        }

        return json_decode($res->getBody(), true) ?? [];
    }

    private function rpc($url, $method, $params, $headers = [])
    {
        $options = [
            "jsonrpc" => "2.0",
            "method"  => $method,
            "params"  => $params,
            "id"      => time(),
            "context" => [],
        ];

        $options = array_merge($options, $headers);
        try {
            $res  = post($url, json_encode($options, 256))->getBody();
            $body = json_decode($res, true);

            if (isset($body['error'])) {
                return $body['error'];
            }

        } catch (Exception $e) {
            echo "服务连接失败\n";
            $body['result'] = ['code' => -1, 'message' => '服务连接失败'];
        }

        return $body['result'] ?? null;
    }
}

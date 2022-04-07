<?php
namespace lim\Server;

/**
 * @Author: Wayren
 * @Date:   2022-03-29 12:12:06
 * @Last Modified by:   Wayren
 * @Last Modified time: 2022-04-07 15:16:58
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

        self::$Gateway = new \Swoole\WebSocket\Server("0.0.0.0", $this->port);
        self::$Gateway->set($config);
        self::$Gateway->on('start', fn() => cli_set_process_title('Gateway'));
        self::$Gateway->on('managerstart', [$this, 'managerstart']);
        self::$Gateway->on('WorkerStart', [$this, 'WorkerStart']);
        // self::$server->on('task', [$this, 'task']);
        self::$Gateway->on('request', [$this, 'request']);
        self::$Gateway->on('message', [$this, 'message']);
        self::$Gateway->start();
    }

    function managerstart($server)
    {
        cli_set_process_title('-Manager');
        echo ("服务启动成功\n");
    }

    function WorkerStart($server, int $workerId)
    {

        try {

            // echo "服务启动\n";
            Timer::clearAll();
            if (function_exists('opcache_reset')) {
                opcache_reset();
            }

            // wlog('缓存配置');

            //同步配置文件
            // Timer::tick(10 * 1000, fn() => static::$extend = uc('config'));

            if ($server->taskworker) {
                $id = $workerId - $server->setting['worker_num'];

                if ($id == 0) {
                    // print_r(get_defined_constants(true)['user']);
                    // print_r(get_included_files());
                }
                cli_set_process_title('-Tasker');
            } else {
                cli_set_process_title('-Worker');
            }
        } catch (\Swoole\ExitException $e) {
            echo ($e->getStatus());
        }

    }

    function task()
    {

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

        if (!$post = json_decode($request->getContent(), true)) {
            $post = $request->post ?? [];
        }

        $vars = array_merge($post, $request->get ?? []);

        $path                 = $request->server['request_uri'];
        $this->request_method = $request->server['request_method'];
        echo "{$path}\n";
        //API调用

        if ($api = $this->apiList[$path] ?? null) {
            $res = match($api['type']) {
                'http' => $this->http($api['url'], $vars, $request->header),
                'rpc'  => $this->rpc($api['url'], $api['method'], [$vars], $request->header),
            default=> [],
            };
            return $response->end(json_encode($res, 256));
        }

        //服务发现
        $name = strstr(substr($path, 1), '/', true);
        // echo "{$name}\n";
        if (!$srv = $this->service[$name] ?? null) {
            return $response->end(json_encode(['code' => -1, 'message' => '服务不存在'], 256));
        }

        $res = match($srv['type']) {
            'http' => $this->http($srv['url'] . $path, $vars, $request->header),
            'rpc'  => $this->rpc($srv['url'], $path, [$vars], $request->header),
        default=> [],
        };
        return $response->end(json_encode($res, 256));
    }

    function message()
    {

    }

    private function http($url, $params, $headers = [])
    {
        unset(
            $headers['set-cookie'],
            $headers['host'],
            $headers['content-length'],
            $headers['user-agent'],
            $headers['accept'],
            $headers['accept-encoding'],
            $headers['accept-language'],
            $headers['connection'],
            $headers['content-type'],
        );

        if ($this->request_method == 'GET') {
            $res = get($url . '?' . http_build_query($params), null, $headers);
        } else {
            $res = post($url, $params, null, $headers);
        }

        // echo $res->getBody();
        return json_decode($res->getBody(), true) ?? [];
    }

    private function rpc($url, $method, $params, $headers = [])
    {
        unset(
            $headers['set-cookie'],
            $headers['host'],
            $headers['content-length'],
            $headers['user-agent'],
            $headers['accept'],
            $headers['accept-encoding'],
            $headers['accept-language'],
            $headers['connection'],
            $headers['content-type'],
        );

        $options = [
            "jsonrpc" => "2.0",
            "method"  => $method,
            "params"  => $params,
            "id"      => time(),
            "context" => [],
        ];

        $options = array_merge($options, $headers);
        // print_r($headers);
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

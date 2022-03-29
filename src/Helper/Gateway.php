<?php
namespace lim\Helper;

/**
 * @Author: Wayren
 * @Date:   2022-03-29 12:12:06
 * @Last Modified by:   Wayren
 * @Last Modified time: 2022-03-29 18:08:22
 */

use function Swoole\Coroutine\run;
use \swoole\Timer;

class Gateway
{
    private $service = null;

    public static $server;

    public function widthServer($value = '')
    {
        $this->service = $value;
        return $this;
    }

    public function run($daemonize = false)
    {

        \Swoole\Coroutine::set(['enable_deadlock_check' => null, 'hook_flags' => SWOOLE_HOOK_ALL]);
 		// if (!is_dir('/var/log/'.__LIM__)) {
 		// 	shell_exec('sudo mkdir /var/log/'.__LIM__);
 		// }
        $config = [

            'reactor_num'        => 1,
            // 'worker_num'            => (int) WORKER_NUM,
            // 'task_worker_num'       => (int) TASK_WORKER_NUM,
            // 'task_enable_coroutine' => true,
            'enable_coroutine'   => true,
            'pid_file'           => '/var/log/'.str_replace('/','_',__LIM__).'.pid',
            'log_level'          => SWOOLE_LOG_WARNING,
            'hook_flags'         => SWOOLE_HOOK_ALL,
            'max_wait_time'      => 1,
            'reload_async'       => true,
            'package_max_length' => 5 * 1024 * 1024,
            // 'max_coroutine'         => (int) MAX_COROUTINE,
            'daemonize'             => true,
            // 'document_root'         => ROOT . 'public',
            // 'enable_static_handler' => true,
        ];

        print_r([$this->service,__LIM__]);

        self::$server = new \Swoole\WebSocket\Server('0.0.0.0', $port ?? 7777);
        self::$server->set($config);
        self::$server->on('start', fn() => cli_set_process_title('Gateway'));
        self::$server->on('managerstart', [$this, 'managerstart']);
        self::$server->on('WorkerStart', [$this, 'WorkerStart']);
        self::$server->on('task', [$this, 'task']);
        self::$server->on('request', [$this, 'request']);
        self::$server->on('message', [$this, 'message']);
        self::$server->start();
    }

    public function managerstart($server)
    {
        cli_set_process_title('-Manager');

        // Timer::tick(1000 * 10, function () {
        // 	echo time().PHP_EOL;
        //     // self::$server->task(['run' => $k]);
        // });
        echo ("服务启动成功\n");
    }

    public function WorkerStart($server, int $workerId)
    {

        try {

            echo "服务启动\n";
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

    public function task($value = '')
    {

    }

    public function request($value = '')
    {
        // code...
    }

    public function message($value = '')
    {
        // code...
    }

    // public  static function __callStatic($method, $args)
    // {
    //     print_r([$method, $args]);
    //     switch ($method) {
    //         case 'reload':
    //             self::$server->reload();
    //             break;

    //         default:
    //             // code...
    //             break;
    //     }
    // }
}

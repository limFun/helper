<?php
namespace lim\Helper;

/**
 * @Author: Wayren
 * @Date:   2022-03-29 12:12:06
 * @Last Modified by:   Wayren
 * @Last Modified time: 2022-03-31 16:51:54
 */

class Console
{
    public function __construct($argv)
    {
        array_shift($argv);
        if (!$method = array_shift($argv)) {
            return;
        }

        echo $method;
        $this->vars = $argv;

        try {
            $this->$method();
        } catch (\Error $e) {
            print_r($e);
        } catch (\Swoole\ExitException $e) {
            print_r($e);
        }

    }

    public function env()
    {
        if (!is_file(__LIM__ . '/composer.json')) {
            return null;
        }

        $composer = json_decode(file_get_contents(__LIM__ . '/composer.json'), true);
        $env      = match($composer['name'] ?? null) {
            'hyperf/hyperf-skeleton' => 'hyperf',
            'laravel/laravel' => 'laravel',
        default=> null,
        };
        return $env;
    }

    public function self()
    {
        $to   = dirname(__LIM__) . '/helper';
        $sync = 'cp -r ' . dirname(__DIR__) . '/ ' . $to . ' && cd ' . $to . ' && sudo git add . && sudo git commit -m \'' . time() . '\' && sudo git push';
        shell_exec($sync);
    }

    // public function watcher()
    // {
    //     $this->scan(__LIM__, $old);
    //     if ($this->env() == 'hyperf') {
    //         $pid = file_get_contents(__LIM__ . '/runtime/hyperf.pid');
    //     }
    //     echo $pid . "\n";
    //     for ($i = 0; $i < 10; $i++) {
    //         sleep(10);
    //         $this->scan(__LIM__, $new);
    //         if ($diff = array_diff($old, $new)) {
    //             $old = $new;
    //             print_r($diff);
    //             echo "重启\n";
    //             echo shell_exec('sudo kill -15 ' . $pid);
    //             // shell_exec("sudo php " . __LIM__ . "/bin/hyperf.php start");
    //             $this->app('start');
    //         }
    //     }
    //     // proc(function () {
    //     //     \Swoole\Timer::tick(1000 * 5, fn() => $this->scan(__LIM__));
    //     //     \Swoole\Event::wait();
    //     // }, 'app-watcher');
    // }

    // public function app($action=null)
    // {
    //     $action         ??= implode(' ', $this->vars);
    //     $script = match ($this->env()) {
    //         'hyperf' => "sudo php " . __LIM__ . "/bin/hyperf.php " . $action,
    //         'laravel' => "sudo php " . __LIM__ . "/bin/laravels " . $action,
    //         default => null,
    //     };
    //     $this->exec($script);
    // }

    // public function scan($dir = null, &$argv = [])
    // {
    //     if (is_dir($dir) && $handle = opendir($dir)) {
    //         while (($file = readdir($handle)) !== false) {
    //             if (($file == ".") || ($file == "..")) {
    //                 continue;
    //             }
    //             $path = $dir . '/' . $file;

    //             if (is_dir($path)) {
    //                 if (in_array($file, ['.git', 'runtime', 'storage', 'vendor', 'test'])) {
    //                     continue;
    //                 }
    //                 $this->scan($path, $argv);

    //                 continue;
    //             }
    //             $argv[$path] = filemtime($path);
    //             // echo filemtime($path)." {$path}\n";
    //         }
    //         closedir($handle);
    //     }
    // }

    public function fnc()
    {
        $fn = array_shift($this->vars);
        $fn(...$this->vars);
    }

    public function git()
    {
        $action = array_shift($this->vars);
        switch ($action) {
            case 'push':
                $branch = array_shift($this->vars) ?? 'dev';
                $msg    = array_shift($this->vars) ?? time();
                $script = 'sudo git add . && sudo git commit -m \'' . $msg . '\' && sudo git push origin ' . $branch;
                shell_exec($script);
                break;
            default:
                // code...
                break;
        }
    }

    public function gateway()
    {
        $action = array_shift($this->vars);
        $dir    = __LIM__ . '/app/Gateway';
        switch ($action) {
            case 'build':
                if (!is_dir($dir)) {
                    shell_exec('sudo mkdir ' . $dir);
                }
                break;
            case 'start':
                // $env = trim();

                $file = match(array_shift($this->vars)) {
                // from => to,
                default=> __LIM__ . '/config/gateway.php',
                };

                if (!is_file($file)) {
                    echo "配置文件不存在\n";
                    return;
                }

                $opt       = include $file;
                $daemonize = '-d' == array_shift($this->vars) ? true : false;
                // file_put_contents(__LIM__.'/aa.json',json_encode($opt,JSON_PRETTY_PRINT));
                $srv = new Gateway;
                $srv->widthOption($opt)->run($daemonize);
                break;
            case 'reload':
                $pid = file_get_contents('/var/log/' . str_replace('/', '_', __LIM__) . '.pid');
                echo shell_exec('sudo kill -10 ' . $pid);
                break;
            case 'stop':
                $pid = file_get_contents('/var/log/' . str_replace('/', '_', __LIM__) . '.pid');
                echo shell_exec('sudo kill -15 ' . $pid);
                break;
            case 'register':
                break;
            default:
                return;
        }
        echo ' -> ' . $action . PHP_EOL;
    }

    private function exec($script = null)
    {
        if (!$script) {
            return;
        }
        $descriptorspec = [
            0 => STDIN,
            1 => STDOUT,
            2 => STDERR,
        ];
        proc_open($script, $descriptorspec, $pipes);
    }
    public function __call($method, $argv)
    {
        // code...
    }
}

<?php
namespace lim;

/**
 * @Author: Wayren
 * @Date:   2022-03-29 12:12:06
 * @Last Modified by:   Wayren
 * @Last Modified time: 2022-04-26 20:13:36
 */

class Console
{
    public function __construct($argv)
    {
        array_shift($argv);
        if (!$method = array_shift($argv)) {
            return;
        }

        // echo $method;
        $this->vars = $argv;

        try {
            $this->$method();
        } catch (\Error $e) {
            loger((array) $e, 'err');
        } catch (\Swoole\ExitException $e) {
            loger((array) $e, 'err');
        }

    }

    public function __call($method = '', $argv = '')
    {
        switch ($method) {
            //git 快捷操作
            case 'g':
                $act    = array_shift($this->vars);
                $script = match($act) {
                    'init' => 'sudo git config --global credential.helper store',
                    'log' => 'sudo git log',
                    'reset' => "sudo git reset " . (array_shift($this->vars) ?? '--hard HEAD^'),
                    'pull' => "sudo git pull",
                    'add' => "sudo git add " . (array_shift($this->vars) ?? '.'),
                    'commit' => "sudo git commit -m '" . array_shift($this->vars) . "'",
                    'push' => "sudo git push " . array_shift($this->vars),
                    '-dl' => 'sudo git branch -d ' . array_shift($this->vars),
                    '-dr' => 'sudo git push origin --delete ' . array_shift($this->vars),
                    'pusher' => $this->gitpusher(),
                    'limer' => $this->selfer(),
                default=> null,
                };

                if ($script) {
                    loger($script);
                    loger(shell_exec($script));
                }

                break;

            default:
                // code...
                break;
        }
    }

    public function gitpusher()
    {
        $branch = array_shift($this->vars) ?? 'dev';
        $msg    = array_shift($this->vars) ?? time();
        return 'sudo git add . && sudo git commit -m \'' . $msg . '\' && sudo git push origin ' . $branch;

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

    public function selfer()
    {
        $to          = dirname(__LIM__) . '/helper';
        return $sync = 'cp -r ' . __DIR__ . ' ' . $to . ' && cd ' . $to . ' && sudo git add . && sudo git commit -m \'' . time() . '\' && sudo git push';
    }

    public function stop()
    {
        $app = array_shift($this->vars);
        switch ($app) {
            case 'hf':
                $pid = file_get_contents(__LIM__ . '/runtime/hyperf.pid');
                echo shell_exec('sudo kill -15 ' . $pid);
                break;
            default:
                return;
        }
    }

    public function scan($dir = null, &$argv = [])
    {
        if (is_dir($dir) && $handle = opendir($dir)) {
            while (($file = readdir($handle)) !== false) {
                if (($file == ".") || ($file == "..")) {
                    continue;
                }
                $path = $dir . '/' . $file;

                if (is_dir($path)) {
                    if (in_array($file, ['.git', 'runtime', 'storage', 'vendor', 'test'])) {
                        continue;
                    }
                    $this->scan($path, $argv);

                    continue;
                }
                $argv[$path] = filemtime($path);
                // echo filemtime($path)." {$path}\n";
            }
            closedir($handle);
        }
    }

    public function fnc()
    {
        run(function () {
            try {
                $fn = array_shift($this->vars);
                $fn(...$this->vars);
            } catch (\Swoole\ExitException $e) {
                loger($e);
            }
        });
    }

    public function obj()
    {

        run(function () {
            try {
                $fn = array_shift($this->vars);
                objRun($fn, ...$this->vars);
            } catch (\Swoole\ExitException $e) {
                loger($e);
            }
        });

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

    public function gw()
    {
        $action = array_shift($this->vars);
        switch ($action) {
            case 'start':
                $daemonize = '-d' == array_shift($this->vars) ? true : false;
                $srv       = new Server\Gateway($daemonize);
                break;
            case 'reload':
                $pid = file_get_contents(__LIM__ . '/runtime/gateway.pid');
                echo shell_exec('sudo kill -10 ' . $pid);
                break;
            case 'stop':
                $pid = file_get_contents(__LIM__ . '/runtime/gateway.pid');
                echo shell_exec('sudo kill -15 ' . $pid);
                break;
            default:
                return;
        }
        echo ' -> ' . $action . PHP_EOL;
    }

    public function ws()
    {
        $action = array_shift($this->vars);
        switch ($action) {
            case 'start':
                $daemonize = '-d' == array_shift($this->vars) ? true : false;
                $srv       = new Server\WebsocketServer($daemonize);
                break;
            case 'reload':
                $pid = file_get_contents(__LIM__ . '/runtime/websocket.pid');
                echo shell_exec('sudo kill -10 ' . $pid);
                break;
            case 'stop':
                $pid = file_get_contents(__LIM__ . '/runtime/websocket.pid');
                echo shell_exec('sudo kill -15 ' . $pid);
                break;
            default:
                return;
        }
        // wlog('ws启动');
        // echo ' -> ' . $action . PHP_EOL;
    }
}

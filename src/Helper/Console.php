<?php
namespace lim\Helper;

/**
 * @Author: Wayren
 * @Date:   2022-03-29 12:12:06
 * @Last Modified by:   Wayren
 * @Last Modified time: 2022-03-31 10:27:04
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
        if (!is_file(__LIM__.'/composer.json')) {
            return null;
        }

        $composer = json_decode(file_get_contents(__LIM__.'/composer.json'),true);

        print_r($composer);
    }

    public function self()
    {
        $to   = dirname(__LIM__) . '/helper';
        $sync = 'cp -r ' . dirname(__DIR__) . '/ ' . $to . ' && cd ' . $to . ' && sudo git add . && sudo git commit -m \'' . time() . '\' && sudo git push';
        shell_exec($sync);
    }

    public function app()
    {
        $action=array_shift($this->vars);

        switch ($action) {
            case 'start':
                // code...
                break;
            
            default:
                // code...
                break;
        }
    }

    public function fnc()
    {
        $fn=array_shift($this->vars);
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

    public function hf()
    {
        // require __LIM__ . '/bin/hyperf.php';
        print_r($this);
    }

    public function __call($method, $argv)
    {
        // code...
    }
}

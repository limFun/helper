<?php
declare (strict_types = 1);
use lim\Helper\Gateway;

spl_autoload_register('loader');

!defined('__LIM__') && define('__LIM__', strstr(__DIR__, '/vendor', true));

function loader($class)
{
    $arr = explode('\\', $class);
    if ($arr[0] == 'lim') {
        array_shift($arr);
    }

    $file = __DIR__ . '/' . implode('/', $arr) . '.php';

    if (is_file($file)) {
        require_once $file;
    } else {
        echo $file . PHP_EOL;
        // exit(json_encode(['code' => 300, 'msg' => $file . " 不存在"], 256));
    }
}

new Colsole($argv);

class Colsole
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
        } catch (Throwable $e) {
            print_r($e);
        }

    }

    public function self()
    {
        $to   = dirname(__LIM__) . '/helper';
        $sync = 'cp -r ' . __DIR__ . '/ ' . $to . ' && cd ' . $to . ' && sudo git add . && sudo git commit -m \'' . time() . '\' && sudo git push';
        shell_exec($sync);
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
                $env = trim(array_shift($this->vars));
                switch ($env) {
                    case 'hyperf':
                        $file = __LIM__. '/config/gateway.json';
                        
                        break;
                    
                    default:
                        // code...
                        break;
                }

                if (!is_file($file)) {
                    echo "配置文件不存在\n";
                    return;
                }
                
                $opt = json_decode(file_get_contents($file), true);
                $daemonize = '-d'==array_shift($this->vars) ? true :false;
                file_put_contents(__LIM__.'/aa.json',json_encode($opt,JSON_PRETTY_PRINT));
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

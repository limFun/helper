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
        // echo $sync . PHP_EOL;
        shell_exec($sync);
        // wlog($sync);
        // wlog('composer sync');
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
                $opt = json_decode(file_get_contents($dir . '/service.json'),true);
                $srv = new Gateway;
                $srv->widthServer($opt)->run(array_shift($this->vars));
                break;
            case 'reload':
                 $pid = file_get_contents('/var/log/'.str_replace('/','_',__LIM__).'.pid');
                echo shell_exec('sudo kill -10 ' . $pid);
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

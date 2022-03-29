<?php
declare (strict_types = 1);
!defined('__LIM__') && define('__LIM__', strstr(__DIR__, '/vendor', true));

new Colsole($argv);

class Colsole
{
    public function __construct($argv)
    {
        array_shift($argv);
        if (!$method = array_shift($argv)) {
            return;
        }

        $this->vars = $argv;
        $this->$method();
        echo $method . PHP_EOL;
    }

    public function self()
    {
        $to   = dirname(__LIM__ ).'/helper';
        // $sync = 'cp -r ' . __DIR__ . '/ ' . $to;
        $sync = 'cp -r ' . __DIR__ . '/ ' . $to . ' && cd ' . $to . ' && sudo git add . && sudo git commit -m \'' . time() . '\' && sudo git push';
        echo $sync.PHP_EOL;
        shell_exec($sync);
        // wlog($sync);
        // wlog('composer sync');
    }

    public function gateway($value = '')
    {
        // code...
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

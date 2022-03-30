<?php
declare (strict_types = 1);
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

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
    }
}

if (!function_exists('env')) {
    function env($key = null, $value = 'SSS')
    {
        if (!is_file(__LIM__.'/.env')) {
            return $value;
        }
        return parse_ini_file(__LIM__.'/.env',true)[$key]??$value;
    }
}

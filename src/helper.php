<?php
declare (strict_types = 1);
spl_autoload_register('loader');

!defined('__LIM__') && define('__LIM__', strstr(__DIR__, '/vendor', true));

function loader($class)
{
    $arr  = explode('\\', $class);
    $file = __LIM__ . '/' . implode('/', $arr) . '.php';
    if (is_file($file)) {
        require_once $file;
    }
}

if (!function_exists('message')) {
    /**
     * @return bool|int
     */
    function message($contents, $event = [])
    {
        return new lim\Helper\MessageClient($contents, $event);
    }
}

if (!function_exists('conf')) {
    /**
     * @return bool|int
     */
    function conf($key = '', $value = '')
    {
        return ['name' => 'sas', 'port' => 9875];
    }
}

if (!function_exists('go')) {
    /**
     * @return bool|int
     */
    function go(callable $callable)
    {
        $id = \Swoole\Coroutine::create($callable);
        return $id > 0 ? $id : false;
    }
}

if (!function_exists('wlog')) {
    function wlog($v = '', $type = 'debug')
    {
        loger($v,$type);
    }
}

if (!function_exists('loger')) {
    function loger($v = '', $type = 'debug')
    {

        $color   = ['debug' => '\\e[34m', 'info' => '\\e[32m', 'err' => '\\e[31m'];
        $v       = is_array($v) ? print_r($v, true) : (string)$v;
        $content = '\\e[36m[' . date('H:i:s') . '] ' . $color[$type] . str_replace('`', '\`', $v) . PHP_EOL;

        if (PHP_SAPI == 'cli') {
            echo shell_exec('printf "' . $content . '"');
        }
    }
}

if (!function_exists('tu')) {
    function tu($fn, $value = '')
    {
        $s = microtime(true);
        $fn();
        $u = intval((microtime(true) - $s) * 1000);
        loger($value . '耗时:' . $u . '毫秒', 'info');
    }
}

if (!function_exists('env')) {
    function env($key = null, $value = 'SSS')
    {
        if (!is_file(__LIM__ . '/.env')) {
            return $value;
        }
        return parse_ini_file(__LIM__ . '/.env', true)[$key] ?? $value;
    }
}

if (!function_exists('proc')) {
    function proc($fn = null, $name = null)
    {
        if (!is_object($fn)) {
            return;
        }

        $proc = new \Swoole\Process($fn);
        if ($name) {
            cli_set_process_title($name);
        }
        $proc->daemon();
        $proc->start();

    }
}

if (!function_exists('rpc')) {
    function rpc($service = null, $onlyData = true)
    {
        return new lim\Helper\Rpclient($service, $onlyData);
    }
}

if (extension_loaded('yac')) {
    lim\Helper\IO::register();
}

loadHelper();

function loadHelper($dir = null)
{
    $dir = $dir ?? __LIM__ . "/app";
    if (is_dir($dir) && $handle = opendir($dir)) {
        while (($file = readdir($handle)) !== false) {
            if (($file == ".") || ($file == "..")) {
                continue;
            }
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                // wlog($path);
                loadHelper($path);

                continue;
            }

            if ($file == 'helper.php') {
                // wlog($path);
                require_once $path;
            }
        }
        closedir($handle);
    }
}

<?php
namespace lim\Helper;

class Env
{

    static $config = [];

    public static function initConfig($dir=null)
    {
        $dir = $dir ?? __LIM__ . "/config";
        if (is_dir($dir) && $handle = opendir($dir)) {
            while (($file = readdir($handle)) !== false) {
   
                if (!str_ends_with($file, '.php')) {
                	continue;
                }
                $path = $dir . '/' . $file;
                $name = strstr($file,'.',true);
                self::$config[$name]=include $path;
            }
            closedir($handle);
        }
    }

    public function loadHelper($dir = null)
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

}

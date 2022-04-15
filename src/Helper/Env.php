<?php
namespace lim\Helper;

class Env
{

    static $config = [];

    // public static function fn()
    // {
    //     // code...
    // }

    public static function initConfig($dir=null)
    {

        $f = __LIM__.'/composer.json';
        $name='phplim/helper';
        if (is_file($f)) {
            $name = json_decode(file_get_contents($f),true)['name']??null;
        }

        if ($name!='phplim/helper') {
           
            return;
        }
        
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

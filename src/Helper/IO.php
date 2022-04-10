<?php
namespace lim\Helper;

/**
 * @Author: Wayren
 * @Date:   2022-03-29 12:12:06
 * @Last Modified by:   Wayren
 * @Last Modified time: 2022-04-06 19:42:01
 */

class IO
{
    public static $io = null;

    public function __construct($argv)
    {

    }

    public static function register()
    {
        if (!self::$io) {
            self::$io = new \Yac(__LIM__);
            wlog('init io');
        }
    }

    public function __call($method, $argv)
    {
        // code...
    }
}

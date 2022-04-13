<?php
namespace lim\Helper;

/**
 * @Author: Wayren
 * @Date:   2022-03-29 12:12:06
 * @Last Modified by:   Wayren
 * @Last Modified time: 2022-04-13 12:26:52
 */

class IO
{
    public static $io = null;

    public function __construct($argv)
    {

    }

    public static function register()
    {
        // if (!self::$io) {
        //     self::$io = new \Yac(__LIM__);
        //     wlog('init io');
        // }
    }

    public function set($value='')
    {
        // code...
    }

    public function get($value='')
    {
        // code...
    }

    public function __call($method, $argv)
    {
        // code...
    }
}

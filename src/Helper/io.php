<?php
namespace lim\Helper;

/**
 * @Author: Wayren
 * @Date:   2022-03-29 12:12:06
 * @Last Modified by:   Wayren
 * @Last Modified time: 2022-04-14 09:55:47
 */

class io
{
    static $ios=[];

    public function __construct($name=null)
    {
        if ($name && isset(self::$ios[$name])) {
            $this->io = self::$ios[$name];
        }
        // $this->io = self::$ios[$name];
        return $this;
    }

    public  function register($name='',$size=100,$cols='')
    {
        $this->io = swoole_table($size, $cols);
        self::$ios[$name]= $this->io;
        loger(self::$ios);
        return $this;
    }

    public function id($value='')
    {
        $this->id = $value;
        return $this;
    }

    public function set($key='',$value)
    {
        $this->io->set($this->id,['id'=>$this->id,$key=>$value]);
        return $this;
    }

    public function get($value='')
    {
        $res = $this->io->get($this->id);
        return $res;
    }
}

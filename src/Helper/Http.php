<?php
declare (strict_types = 1);
namespace lim\Helper;

use function Swoole\Coroutine\Http\get;
use function Swoole\Coroutine\Http\post;
use Swoole\Coroutine\Http\Client\Exception;

class Http
{

    public static function __callStatic($method, $args)
    {
        try {
            return call_user_func_array([new HttpHandle(), $method], $args);
        } catch (Throwable $e) {
            print_r($e);
        }
    }
}

/**
 *
 */
class HttpHandle
{
    public $data = null,$request=null;

    private $header=null,$option=null,$cookie=null;

    public function header($header=null)
    {
        $this->header = $header;
        return $this;
    }

    public function option($option=null)
    {
        $this->option = $option;
        return $this;
    }

    public function cookie($cookie=null)
    {
        $this->cookie = $cookie;
        return $this;
    }

    public function getHeaders()
    {
        return $this->request->getHeaders();
    }

    public function getCookies()
    {
        return $this->request->getCookies();
    }

    public function get($value = '')
    {
        try {
            $this->request = get($value, $this->option, $this->header, $this->cookie);
     
            $this->data    = $this->request->getBody();
        } catch (Exception $e) {
            wlog("请求失败");
            $this->data = null;
        }
        return $this;
    }

    public function post($url = '', $data = [])
    {
        try {
            $this->request = post($url, $data,$this->option, $this->header, $this->cookie);
            $this->data    = $this->request->getBody();
        } catch (Exception $e) {
            wlog("请求失败");
            $this->data = null;
        }
        return $this;
    }

    public function json()
    {
        if (null==$this->data) {
            return null;
        }
        return json_decode($this->data, true);
    }

}

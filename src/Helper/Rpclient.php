<?php
declare (strict_types = 1);

namespace lim\Helper;

/**
 * HF RPC HTTP 远程调用类
 */
class Rpclient
{

    private $name = null, $headers = [], $port = null;

    public $message = null;
    public function __construct($name, $onlyData)
    {
        $this->onlyData = $onlyData;
        $f              = __LIM__ . '/config/gateway.php';
        if (!is_file($f)) {
            $this->message = '配置文件不存在';
            return $this;
        }
        $ser = include $f;

        foreach ($ser['service']['rpc'] as $k => $v) {
            list($type, $url) = explode('://', $v['url']);
            $this->rpc[$k]    = match($type) {
                'http' => ['type' => $type, 'url' => $v['url']],
                'tcp'  => ['type' => $type, 'url' => $url],
            default=> null,
            };
        }

        if (!$this->node = $this->rpc[$name] ?? null) {
            echo $name . "服务不存在";
            $this->message = $name . '服务不存在';
            return $this;
        }

        // print_r($this->node);

        $this->name = $name;
    }

    public function auth($auth)
    {
        $this->auth = $auth;
        return $this;
    }

    public function member($member)
    {
        $this->member = $member;
        return $this;
    }

    public function cache($value='',$exp = 0)
    {
        $this->cacheKey = $value;
        $this->cacheExp = $exp;
        return $this;
    }

    public function parse($method, $params)
    {
        if (isset($this->cacheKey)) {
            $cache = \lim\Helper\IO::$io->get($this->cacheKey);
            
            if ($cache!==false) {
                wlog('get cache');
                return $cache;
            }
        }

        if ($this->message) {
            return ['code' => -1, 'message' => $this->message];
        }

        $options = [
            "jsonrpc" => "2.0",
            "method"  => "/" . strtolower($this->name) . "/{$method}",
            "params"  => $params,
            "id"      => time(),
            "context" => [],
        ];

        if (isset($this->auth)) {
            $options['authorization'] = $this->auth;
        }

        if (isset($this->member)) {
            $options['member'] = $this->member;
        }

        if ($this->node['type'] == 'tcp') {
            $client          = new \Swoole\Client(SWOOLE_SOCK_TCP);
            list($ip, $port) = explode(':', $this->node['url']);
            if (!$client->connect($ip, (int) $port, 1)) {
                exit("connect failed. Error: {$client->errCode}\n");
            }
            $client->send(json_encode($options, 256) . "\r\n");
            $res  = $client->recv();
            $body = json_decode($res, true);
            $client->close();
        }

        if ($this->node['type'] == 'http') {
            // $res  = post($this->node['url'], json_encode($options, 256))->getBody();
            if (!$res  = $this->curlPost($this->node['url'], json_encode($options, 256))) {
                return ['code'=>-1,'message'=>'服务未开启'];
            };
            
            $body = json_decode($res, true);
        }

        if (isset($body['error'])) {
            return $body['error'];
        }

        
        // print_r($body = json_decode($res, true));
        return $body['result']['data'] ?? null;
    }

    public function curlPost($url, $post_data = array(), $header = "")
    {
        $ch = curl_init(); // 启动一个CURL会话
        curl_setopt($ch, CURLOPT_URL, $url); // 要访问的地址
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // 对认证证书来源的检查
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // 从证书中检查SSL加密算法是否存在
        curl_setopt($ch, CURLOPT_POST, true); // 发送一个常规的Post请求
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data); // Post提交的数据包
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5); // 设置超时限制防止死循环
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // 获取的信息以文件流的形式返回
        // curl_setopt($ch, CURLOPT_HTTPHEADER, $header); //模拟的header头
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }

    public function __call($method, $params)
    {
        return $this->parse($method, $params);
    }

}

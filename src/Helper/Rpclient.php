<?php
declare (strict_types = 1);

namespace lim\Helper;

use function Swoole\Coroutine\Http\post;

/**
 * HF RPC HTTP 远程调用类
 */
class Rpclient
{

    private $name = null, $headers = [], $port = null;

    public $message=null;
    public function __construct($name, $headers = [])
    {
        $f = __LIM__ . '/config/gateway.php';
        if (!is_file($f)) {
            $this->message = '配置文件不存在';
            return $this;
        }
        $ser = include $f;
        foreach ($ser['services']['rpc'] as $k => $v) {
            list($type, $url) = explode('://', $v['url']);
            $this->rpc[$k]    = match($type) {
                'http' => ['type' => $type, 'url' => $v['url']],
                'tcp'  => ['type' => $type, 'url' => $url],
            default=> null,
            };
        }

        if (!$this->node = $this->rpc[$name] ?? null) {
            echo $name."服务不存在";
            $this->message = $name.'服务不存在';
            return $this;
        }

        print_r($this->node);

        $this->name = $name;

        if ($headers) {
            unset(
                $headers['set-cookie'],
                $headers['host'],
                $headers['content-length'],
                $headers['user-agent'],
                $headers['accept'],
                $headers['accept-encoding'],
                $headers['accept-language'],
                $headers['connection'],
                $headers['content-type'],
            );
            $this->headers = $headers;
        }
    }

    public function parse($method, $params)
    {
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
            $res  = post($this->node['url'], json_encode($options, 256))->getBody();
            $body = json_decode($res, true);
        }

        if (isset($body['error'])) {
            return $body['error'];
        }

        return $body['result'];
    }

    public function __call($method, $params)
    {
        return $this->parse($method, $params);
    }

}

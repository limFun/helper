<?php
declare(strict_types=1);

namespace lim\Helper;

use function Swoole\Coroutine\Http\post;

/**
 * HF RPC HTTP 远程调用类
 */
class Rpclient
{
    
    private $name = null, $host = null, $port = null;

    public function __construct($name)
    {
        $ser = include __LIM__ . '/config/gateway.php';
        // print_r($ser);
        foreach($ser['services']['rpc'] as $k=>$v){
            // print_r([$k,$v]);
            list($type,$url)=explode('://', $v['url']);
            $this->rpc[$k] = match ($type) {
                'http' => ['type'=>$type,'url'=>$v['url']],
                'tcp' => ['type'=>$type,'url'=>$url],
                default => null,
            };

        }
        $this->name = $name;
        // print_r($this->rpc);
        // $servers = array_column(config('services')['consumers'], 'nodes', 'name');
        // if (!$nodes = $servers[$name] ?? null) {
        //     $this->message = '服务不存在';
        // } else {
        //     $this->name = $name;
        //     $node         = array_shift($nodes);
        //     $this->host   = $node['host'];
        //     $this->port   = $node['port'];
        // }
    }

    public function parse($method,$params)
    {
        // print_r([$this->rpc[$this->name],$method,$params]);
        $ser = $this->rpc[$this->name];
        $options = [
            "jsonrpc" => "2.0",
            "method"  => "/" . strtolower($this->name) . "/{$method}",
            "params"  => $params,
            "id"      => time(),
            "context" => [],
        ];

        if ($ser['type']=='tcp') {
            $client = new \Swoole\Client(SWOOLE_SOCK_TCP);
            list($ip,$port) = explode(':',$ser['url']);
            if (!$client->connect($ip, (int)$port, 1)) {
                exit("connect failed. Error: {$client->errCode}\n");
            }
            // print_r([$ser,$options]);
            $client->send(json_encode($options,256)."\r\n");
            $result =  $client->recv();
            // var_dump($result);
            $client->close();
            
            print_r(json_decode($result,true));
        }

        if ($ser['type']=='http') {
            $res  = post($ser['url'], json_encode($options, 256))->getBody();
            $body = json_decode($res, true);

            print_r($body);
            if (isset($body['error'])) {
                return $body['error'];
            }
        }
    }

    public function __call($method, $params)
    {
        return $this->parse($method, $params);
    }

}

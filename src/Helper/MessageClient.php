<?
declare (strict_types = 1);
namespace lim\Helper;

use Swoole\Coroutine\Http\Client;

class MessageClient
{
    public function __construct($content, $event = [])
    {
        $this->data = ['content' => $content, 'event' => $event];
    }

    public function from($value = '')
    {
        $this->data['sender'] = $value;
        return $this;
    }

    public function type($value = '')
    {
        $this->data['receive'] = $value;
        return $this;
    }

    public function to($value = '')
    {
        $this->data['receive'] = $value;
        return $this;
    }

    public function method($value='')
    {
        $this->method=$value;
        return $this;
    }

    public function push($fd)
    {
        $res = [
            'code'=>1,
            'message'=>'success',
            'method'=>$this->method??null,
            'data'=>$this->data['content']
        ];
        return Http::header(['fd'=>$fd])->post('http://127.0.0.1:9500',$res)->json();
    }

    public function send()
    {

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
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header); //模拟的header头
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }
}

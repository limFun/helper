<?
declare (strict_types = 1);
namespace lim;

class Message {
	public static function __callStatic($method, $argv) {
		return (new MessageHandler())->$method(...$argv);
	}

	public static function parse($server, $frame) {

		$data = json_decode($frame->data, true);
		(new MessageHandler($server))->toUser(8)->content($data);
	}
}

class MessageHandler {
	private $fdArr = [];
	function __construct(private $server = null) {}

	public function getFd($type = '', $uid = '') {
		$this->fdArr = redis()->ZRANGEBYSCORE('message:' . $type, (string) $uid, (string) $uid);
		return $this;
	}

	public function toUser($userId = 0) {return $this->getFd('user', $userId);}

	public function toGroup($value = '') {}

	public function toChan($value = '') {}

	public function content($value = '') {
		$server = $this->server ?? curr('server');
		if (!$server) {return;}
		$value = is_array($value) ? json_encode($value, 256) : $value;
		foreach ($this->fdArr as $fd) {
			if ($server->isEstablished((int) $fd)) {
				$server->push((int) $fd, $value);
			} else {
				loger($fd . '失效');
				redis()->zrem('message:user', $fd);
			}
		}
	}
}
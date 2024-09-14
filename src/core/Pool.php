<?
declare (strict_types = 1);
namespace lim;

class Pool {

	public $pool = null;

	public $call = null;

	public $exprie = 0;

	public $size = 0;

	private $num = 0;

	public static function init($call) {
		$curr = new self();
		$curr->call = $call();
		$curr->exprie = $curr->call->option['poolExpire'] ?? 60;
		$curr->size = $curr->call->option['poolSize'] ?? 50;
		$curr->pool = new \Swoole\Coroutine\Channel($curr->size);
		return $curr;
	}

	public function make() {
		$this->pool->push($this->call->init());
		$this->num++;
		return $this;
	}

	public function pull($time = -1) {
		pull: if ($this->pool->isEmpty() && $this->num < $this->size) {$this->make();}
		$handler = $this->pool->pop($time);
		$this->num--;
		if ($handler->create + $this->exprie < time()) {goto pull;}
		return $handler;
	}

	public function push($call = null) {
		if ($call !== null) {
			$this->pool->push($call);
			$this->num++;
		}
	}

}
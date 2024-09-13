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

	public function make($num = 1) {
		$this->pool->push($this->call->init());
		$this->num++;
		// loger('创建填充' . $this->num);
		return $this;
	}

	public function pull($time = -1) {
		if ($this->pool->isEmpty() && $this->num < $this->size) {$this->make();}
		// if ($this->num <= 0) {$this->make();}

		// loger('pool pull ' . $this->num);
		$p = $this->pool->pop($time);
		$this->num--;

		if ($p->create + $this->exprie < time()) {
			loger($p->create . '过期丢弃');
			return $this->pull();
		}
		return $p;
	}

	public function push($call = null) {
		if ($call !== null) {
			$this->pool->push($call);
			$this->num++;
			// loger('pool push ' . $this->num);
		}
	}

}
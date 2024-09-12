<?
declare (strict_types = 1);
namespace lim;

class Pool {

	public $pool = null;

	public $call = null;

	public $exprie = 0;

	public $size = 0;

	public $num = 0;

	public static function init($call) {
		$curr = new self();
		$curr->call = $call();
		$curr->exprie = $curr->call->option['poolExpire'];
		$curr->size = $curr->call->option['poolSize'];
		$curr->pool = new \Swoole\Coroutine\Channel($curr->size);
		return $curr;
	}

	public function make($num = 1) {
		$this->pool->push($this->call->run());
		$this->num++;
		return $this;
	}

	public function pull($name = '') {
		if ($this->pool->isEmpty() && $this->num < $this->size) {
			$this->make();
			// loger('创建填充');
		}

		if ($this->num <= 0) {
			// return null;
			$this->make();
		}

		// loger($this->num);

		$p = $this->pool->pop(-1);
		$this->num--;
		//过期丢弃
		if ($p->create + $this->exprie < time()) {
			// loger($p->create . '过期丢弃');
			return $this->pull();
		}
		return $p;
	}

	public function push($call = null) {
		if ($call !== null) {
			$this->pool->push($call);
			$this->num++;
		}
	}

}
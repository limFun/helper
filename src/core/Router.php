<?
declare (strict_types = 1);
namespace lim;
/**
 *
 */

class Router {

	protected $pubRule = [];

	protected $rule = [];

	protected $call = [];

	public static function init() {
		$h = new static;
		return $h;
	}

	public function __before() {return $this;}

	public function register($m, $o) {

		if ($this->pubRule) {
			Check::data($o)->rule($this->pubRule)->stop();
		}

		if ($rule = $this->rule[$m] ?? null) {
			Check::data($o)->rule($rule)->stop();
		}

		if ($h = $this->call[$m] ?? null) {
			if ($h[2] ?? null) {
				$token = $_SERVER['HTTP_TOKEN'] ?? '';
				if (!token($token, true)) {
					apiErr('请登录');
				}
			}
			return $h[0]::{$h[1]}($o);
		}

		if (method_exists($this, $m)) {
			return $this->$m($o);
		}

		return [];
	}
}
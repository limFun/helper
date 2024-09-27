<?
declare (strict_types = 1);
namespace lim;

class App {

	function __construct(private $handler) {}

	public static function run($value = '') {
		return new self($value);
	}

	public function __get($k) {
		loger([$this, $k]);
		switch ($this->handler) {
		case 'model':return "\\app\\model\\" . ucfirst($k);
		case 'task':return "\\app\\task\\" . ucfirst($k);
		default:
			// code...
			break;
		}
	}

}

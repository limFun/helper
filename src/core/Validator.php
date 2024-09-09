<?
declare (strict_types = 1);
namespace lim;

class Validator {
	public $error = null;

	public static function run($data, $rules) {
		$curr = new self();
		$curr->data = $data;
		$curr->rules = $rules;
		$curr->check();
		return $curr;
	}

	public function check() {
		foreach ($this->rules as $k => $v) {
			if (strpos($k, '|') === false) {
				$currName = $currKey = $k;
			} else {
				[$currName, $currKey] = explode('|', $k);
			}

			$rules = explode('|', $v);

			if (array_key_exists($currKey, $this->data)) {
				$currData = $this->data[$currKey];
			} else {
				if (in_array('required', $rules)) {
					$this->error = $currName . '是必须的';
					break;
				} else {
					continue;
				}
			}

			foreach ($rules as $key => $rule) {
				if (strpos($rule, ':') !== false) {
					[$rule, $append] = explode(':', $rule);
				}

				switch ($rule) {
				case 'string':
					if (!is_string($currData)) {
						$this->error = $currName . '必须为字符串';
					}
					break;
				case 'numeric':
					if (!is_numeric($currData)) {
						$this->error = $currName . '必须为数值';
					}
					break;
				case 'int':
					if (!is_int($currData)) {
						$this->error = $currName . '必须为整数';
					}
					break;
				case 'bool':
					if (!is_bool($currData)) {
						$this->error = $currName . '必须为布尔值';
					}
					break;
				case 'float':
					if (!is_float($currData)) {
						$this->error = $currName . '必须为浮点型';
					}
					break;
				case 'array':
					if (!is_array($currData)) {
						$this->error = $currName . '必须为数组';
					}
					break;
				case 'minlen':
					if (strlen($currData) < $append) {
						$this->error = $currName . '长度必须大于' . $append;
					}
					break;
				case 'maxlen':
					if (strlen($currData) > $append) {
						$this->error = $currName . '长度必须小于' . $append;
					}
					break;
				default:

					break;
				}

				if (isset($this->error)) {
					break;
				}
			}
		}

		return $this;
	}

	public function error() {

	}

	public function throw() {
		if ($this->error) {
			apiErr($this->error);
		}
	}

	function __call($method, $argv) {

	}
}
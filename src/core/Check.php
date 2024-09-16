<?
declare (strict_types = 1);
namespace lim;

class Check {
	protected $data = [];
	protected $errors = [];
	protected static $fliter = [
		'is_ip' => FILTER_VALIDATE_IP,
		'is_email' => FILTER_VALIDATE_EMAIL,
		'is_url' => FILTER_VALIDATE_URL,
		'is_int' => FILTER_VALIDATE_INT,
		'is_float' => FILTER_VALIDATE_FLOAT,
		'is_bool' => FILTER_VALIDATE_BOOLEAN,
	];
	protected static $pattern = [
		'is_idcard' => '/^[1-9]\d{5}(18|19|20)\d{2}((0[1-9])|(1[0-2]))((0[1-9])|([1-2][0-9])|(3[0-1]))\d{3}[0-9Xx]$/',
		'is_phone' => '/^1[3-9]\d{9}$/',
	];
	public static function __callStatic($method, $argv) {
		$h = new static;
		switch ($method) {
		case 'data':$h->data = $argv[0] ?? [];
			return $h;
		default:return $h->$method(...$argv);
		}
	}
	function __call($method, $argv) {
		$method = strtolower($method);
		$var = $argv[0] ?? null;
		switch ($method) {
		case 'is_numeric':case 'is_string':case 'is_array':case 'is_object':case 'is_null':return $method($var);
		case 'is_ip':case 'is_email':case 'is_url':case 'is_int':case 'is_float':case 'is_bool':return filter_var($var, self::$fliter[$method]) === false ? false : true;
		case 'is_required':case 'is_set':return !empty($argv);
		case 'is_idcard':case 'is_phone':return preg_match(self::$pattern[$method], (string) $var);
		case 'is_json':return is_array($var);
		case 'min':case 'max':case 'size':return $this->{'_' . $method}(...$argv);
		case 'stop':empty($this->errors) ? '' : apiErr($this->errors[0]);
		default:return true;
		}
	}
	public function rule($rule = []) {
		foreach ($rule as $k => $v) {
			[$name, $key] = strpos($k, '|') === false ? [$k, $k] : explode('|', $k);
			$rules = explode('|', (string) $v);
			if (isset($this->data[$key])) {
				foreach ($rules as $ruler) {
					if (strpos($ruler, ':') !== false) {
						[$ruler, $append] = explode(':', $ruler);
						$this->{'_' . $ruler}($this->data[$key], $append, $name);
					} else {
						$this->parseMessage($this->{'is_' . $ruler}($this->data[$key]), $name, $ruler);
					}
				}
			} elseif (in_array('required', $rules)) {$this->errors[] = $name . '是必须的';}
		}
		return $this;
	}
	private function _min($value = '', $append = '', $name = '', $status = false) {
		if (is_string($value)) {strlen($value) < $append ? $this->errors[] = $name . '的长度必须大于' . $append : $status = true;} elseif (is_numeric($value)) {$value < $append ? $this->errors[] = $name . '的值必须大于' . $append : $status = true;}
		return $status;
	}
	private function _max($value = '', $append = '', $name = '', $status = false) {
		if (is_string($value)) {strlen($value) > $append ? $this->errors[] = $name . '的长度必须小于' . $append : $status = true;} elseif (is_numeric($value)) {$value > $append ? $this->errors[] = $name . '的值必须小于' . $append : $status = true;}
		return $status;
	}
	private function _size($value = '', $append = '', $name = '', $status = false) {
		if (strpos((string) $append, ',') !== false) {
			[$min, $max] = explode(',', $append);
			if (is_string($value)) {(strlen($value) < $min || strlen($value) > $max) ? $this->errors[] = $name . '的长度必须>=' . $min . '且<=' . $max : $status = true;} elseif (is_numeric($value)) {($value < $min || $value > $max) ? $this->errors[] = $name . '的值必须>=' . $min . '且<=' . $max : $status = true;}
		} else {
			if (is_string($value)) {strlen($value) != $append ? $this->errors[] = $name . '的长度必须等于' . $append : $status = true;} elseif (is_numeric($value)) {$value != $append ? $this->errors[] = $name . '的值必须等于' . $append : $status = true;}
		}
		return $status;
	}
	private function parseMessage($status = false, $name = '', $rule = '', $append = '') {
		$status == true ? '' : $this->errors[] = $name . match ($rule) {
			'int', => '必须为整数',
			'float' => '必须为浮点数',
			'numeric' => '必须为数字',
			'string' => '必须为字符串',
			'array' => '必须为数组',
			'json' => '必须为JSON',
			'object' => '必须为对象',
			'bool' => '必须为布尔值',
			'null' => '必须为NULL',
			'ip' => '必须为IP地址',
			'email' => '错误',
			'url' => '错误',
			'idcard' => '错误',
			'phone' => '错误',
			default => '',
		};
	}
}
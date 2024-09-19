<?
declare (strict_types = 1);
namespace lim;

class Model {
	protected $connection = 'default';
	protected $table = '';
	protected $db;
	protected $data = [];
	protected $result = [];
	function __construct($data = []) {
		if (!$this->table) {
			$this->table = config('db.' . $this->connection . '.prefix');
			$name = basename(str_replace('\\', '/', static::class)); //解析数据表
			for ($i = 0; $i < strlen($name); $i++) {
				$this->table .= ctype_upper($name[$i]) && $i > 0 ? '_' . strtolower($name[$i]) : strtolower($name[$i]);
			}
		}
		$this->db = Db::table($this->table, $this->connection);
		$this->data = $data;
	}
	public static function __callStatic($method, $argv) {
		return self::query()->$method(...$argv);
	}

	public static function query(?array $o = []) {
		return (new static($o))->db;
	}

	public static function h(?array $o = []) {
		return new static($o);
	}

	public static function create(?array $o = []) {
		return self::h($o)->check('create')->createBefore()->createDoing()->createAfter()->result();
	}

	public static function delete(?array $o = []) {
		return self::h($o)->check('delete')->deleteBefore()->deleteDoing()->deleteAfter()->result();
	}

	public static function update(?array $o = []) {
		return self::h($o)->check('update')->updateBefore()->updateDoing()->updateAfter()->result();
	}

	public static function detail(?array $o = []) {
		return self::h($o)->check('detail')->detailBefore()->detailDoing()->detailAfter()->result();
	}

	public static function list(?array $o = []) {
		return self::h($o)->listBefore()->listDoing()->listAfter()->result();
	}

	public function createDoing() {
		if ($id = $this->db->insert($this->data, true)) {
			$this->result = Db::table($this->table)->find($id);
		}
		return $this;
	}

	public function deleteDoing() {
		$this->result['deleteRow'] = $this->db->where($this->data)->delete()->rowCount();
		return $this;
	}

	public function updateDoing() {
		$this->result['updatedRow'] = $this->db->update($this->data)->rowCount();
		return $this;
	}

	public function detailDoing() {
		$this->result = $this->db->where($this->data)->find();
		return $this;
	}

	public function listDoing() {
		$page = array_shifter($this->data, 'page', 1);
		$limit = array_shifter($this->data, 'limit', 10);
		$this->result['count'] = $this->db->count('*');
		$this->result['list'] = $this->db->page($page, $limit)->select();
		return $this;
	}

	public function check(string $method = '') {
		$rule = [];
		foreach ($this->db->schema() as $k => $v) {
			$keyRule = $this->rule[$method][$k] ?? '';
			$rule[$v['commit'] . '|' . $k] = $v['type'] . '|' . $keyRule;
		}
		check($this->data, $rule)->stop();
		return $this;
	}

	public function debug() {
		$this->db->debug();
		return $this;
	}

	public function result(): mixed {
		return $this->result;
	}

	public function __call($method, $argv) {
		return $this;
	}
}

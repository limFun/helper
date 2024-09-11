<?
declare (strict_types = 1);
namespace lim;

class Model {
	protected $table = '';
	protected $db;
	protected $data = [];
	public $result = [];
	function __construct($data = []) {
		if (!$this->table) {
			$name = basename(str_replace('\\', '/', static::class)); //解析数据表
			for ($i = 0; $i < strlen($name); $i++) {
				$this->table .= ctype_upper($name[$i]) && $i > 0 ? '_' . strtolower($name[$i]) : strtolower($name[$i]);
			}
		}
		$this->db = Db::table($this->table);
		$this->data = $data;
	}
	public static function __callStatic($method, $argv) {
		switch (strtolower($method)) {
		case 'h':case 'query':
			return new static(...$argv);
		case 'create':case 'delete':case 'update':case 'list':case 'detail':
			return self::h(...$argv)->check($method)->{$method . 'Before'}()->{$method . 'Doing'}()->{$method . 'After'}();
		default:
			return call_user_func_array([self::h()->db, $method], $argv);
		}
	}
	public function __call($method, $argv) {
		switch (strtolower($method)) {
		case 'debug':
			$this->db->debug();
			break;
		case 'check': //数据合法性校验
			$rule = [];
			foreach ($this->db->schema() as $k => $v) {
				$keyRule = $this->rule[$argv[0]][$k] ?? '';
				$rule[$v['commit'] . '|' . $k] = $v['type'] . '|' . $keyRule;
			}
			check($this->data, $rule)->stop();
			break;
		case 'createdoing':
			if ($id = $this->db->insert($this->data, true)) {
				$this->result = Db::table($this->table)->find($id);
			}
			break;
		case 'deletedoing':
			$this->result['deleteRow'] = $this->db->where($this->data)->delete()->rowCount();
			break;
		case 'updatedoing':
			$this->result['updatedRow'] = $this->db->update($this->data)->rowCount();
			break;
		case 'listdoing':
			$page = array_shifter($this->data, 'page', 1);
			$limit = array_shifter($this->data, 'limit', 10);
			$this->result['count'] = $this->db->count('id');
			$this->result['list'] = $this->db->page($page, $limit)->select();
			break;
		case 'detaildoing':
			$this->result = $this->db->where($this->data)->find();
			break;
		case 'createafter':case 'deleteafter':case 'updateafter':case 'listafter':case 'detailafter': //执行后钩子
			return $this->result;
		}
		return $this;
	}
}

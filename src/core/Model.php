<?
declare (strict_types = 1);
namespace lim;

class Model {
	protected $table = null;
	protected $db;
	protected $data = [];
	public $result = [];
	function __construct($data = []) {
		$name = basename(str_replace('\\', '/', static::class)); //解析数据表
		$table = '';
		for ($i = 0; $i < strlen($name); $i++) {
			$table .= ctype_upper($name[$i]) && $i > 0 ? '_' . strtolower($name[$i]) : strtolower($name[$i]);
		}
		$this->table ??= $table;
		$this->db = Db::table($this->table);
		$this->data = $data;
	}
	public static function __callStatic($method, $argv) {
		switch (strtolower($method)) {
		case 'h':
		case 'query':
			return new static(...$argv);
		case 'create':
		case 'delete':
		case 'update':
		case 'list':
		case 'detail':
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
			Validator::run($this->data, $rule)->throw();
			break;
		case 'createbefore':
			break;
		case 'createdoing':
			$this->result['createId'] = $this->db->insert($this->data, true);
			break;
		case 'createafter':
			return $this->result;
		case 'deletebefore':
			break;
		case 'deletedoing':
			$this->result['deleteRow'] = $this->db->where($this->data)->delete()->rowCount();
			break;
		case 'deleteafter':
			return $this->result;
		case 'updatebefore':
			break;
		case 'updatedoing':
			$this->result['updatedRow'] = $this->db->update($this->data)->rowCount();
			break;
		case 'updateafter':
			return $this->result;
		case 'listbefore':
			break;
		case 'listdoing':
			$page = array_once($this->data, 'page', 1);
			$limit = array_once($this->data, 'limit', 10);
			$this->result['count'] = $this->db->count('id');
			$this->result['list'] = $this->db->page($page, $limit)->select();
			break;
		case 'listafter':
			return $this->result;
		case 'detailbefore':
			break;
		case 'detaildoing':
			$this->result = $this->db->where($this->data)->find();
			break;
		case 'detailafter':
			return $this->result;
		}
		return $this;
	}
}

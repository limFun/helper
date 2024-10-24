<?
declare (strict_types = 1);
namespace lim;

use PDO;
use PDOException;

class Db
{
    public static $pool;
    public static $connection = 'default';
    public static function init($connection = 'default')
    {
        if (self::$pool == null) {
            $c = config('db');
            foreach ($c as $k => $v) {
                self::$pool[$k] = PHP_SAPI == 'cli' ? Pool::init(fn() => new PdoHandler($v)) : (new PdoHandler($v))->init();
            }
        }
        self::$connection = $connection;
        return self::$pool[$connection];
    }

    public static function connection($connection = 'default')
    {
        if (PHP_SAPI == 'cli') {
            if (! $pdo = Context::get('pdo' . $connection)) {
                $pdo = self::init($connection)->pull();
                Context::set('pdo' . $connection, $pdo);
            }
            return $pdo;
        } else {
            return self::$pool[$connection];
        }
    }
    public static function transaction($connection = 'default', $call = null)
    {
        self::init($connection);
        if ($call) {
            self::connection(self::$connection)->handler->beginTransaction();
            try {
                $call();
                self::connection(self::$connection)->handler->commit();
            } catch (PDOException $e) {
                loger($e->getMessage());
                self::connection(self::$connection)->handler->rollback();
            }
            self::init(self::$connection)->push(self::connection(self::$connection));
            Context::delete('pdo' . self::$connection);
            self::$connection = 'default';
        } else {
            self::connection(self::$connection)->handler->beginTransaction();
        }
    }
    public static function schema()
    {
        self::init();
        $config = [];
        foreach (config('db') as $con => $d) {
            $sql = "SELECT TABLE_NAME,COLUMN_NAME,DATA_TYPE,COLUMN_COMMENT FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '" . $d['database'] . "' ORDER BY TABLE_NAME ASC,ORDINAL_POSITION ASC ";
            $res = self::connection($con)->handler->query($sql)->fetchall();

            foreach ($res as $k => $v) {
                switch ($v['DATA_TYPE']) {
                    case 'int':case 'bigint':case 'tinyint':case 'decimal':$type = 'numeric';
                        break;
                    case 'varchar':case 'text':$type = 'string';
                        break;
                    default:$type = $v['DATA_TYPE'];
                        break;
                }
                $commit = empty($v['COLUMN_COMMENT']) ? $v['COLUMN_NAME'] : $v['COLUMN_COMMENT'];
                if (str_contains($commit, '|')) {
                    [$commit, $type] = explode('|', $commit);
                }
                if (str_contains($commit, ':')) {
                    [$commit, $e] = explode(':', $commit);
                }
                // $config[$con][$v['TABLE_NAME']][$v['COLUMN_NAME']] = ['type' => $type, 'commit' => $commit];
                $config[$con][$v['TABLE_NAME']][$v['COLUMN_NAME']] = [$type, $commit];
            }
        }

        $configContent = "<?\nreturn " . str_replace(['{', '}', ':'], ['[', ']', '=>'], json_encode($config, 256)) . ";\n";
        file_put_contents(ROOT_PATH . 'config/schema.php', $configContent);
        loger('缓存数据库schema成功');
    }
    public static function commit()
    {
        self::connection(self::$connection)->handler->commit();
        self::init(self::$connection)->push(self::connection(self::$connection));
        Context::delete('pdo' . self::$connection);
        self::$connection = 'default';
    }
    public static function rollback()
    {
        self::connection(self::$connection)->handler->rollback();
        self::init(self::$connection)->push(self::connection(self::$connection));
        Context::delete('pdo' . self::$connection);
        self::$connection = 'default';
    }

    public static function __callStatic($method, $argv)
    {
        self::init();
        return call_user_func_array([new QueryBuilder, $method], $argv);
    }
}

class QueryBuilder
{
    private $handler;
    private $schema = [];
    private $option = [
        'connection' => 'default',
        'debug'      => false,
        'field'      => '*',
        'table'      => '',
        'where'      => '1',
        'group'      => '',
        'order'      => '',
        'limit'      => '',
        'lock'       => '',
        'sql'        => '',
        'execute'    => [],
        'join'       => [], // Add this new option for joins
    ];

    public function __destruct()
    {
        if (PHP_SAPI == 'cli') {
            Db::init($this->option['connection'])->push(Db::connection($this->option['connection']));
            Context::delete('pdo' . $this->option['connection']);
        }
    }
    public function __call($method, $argv)
    {
        switch (strtolower($method)) {
            case 'schema':return $this->schema;
            case 'debug':$this->option['debug'] = true;
                break;
            case 'table':
                $this->option['table']      = $argv[0];
                $this->option['connection'] = $argv[1] ?? 'default';
                $this->schema               = App::$store['schema'][$this->option['connection']][$argv[0]] ?? [];
                break;
            case 'name':
                $this->option['connection'] = $argv[1] ?? 'default';
                $this->option['table']      = config('db.' . $this->option['connection'] . '.prefix') . $argv[0];
                $this->schema               = App::$store['schema'][$this->option['connection']][$this->option['table']] ?? [];
                break;
            case 'field':$this->option['field'] = $argv[0] ?? '*';
                break;
            case 'limit':$this->option['limit'] = ' LIMIT ' . (isset($argv[1]) ? $argv[0] . ',' . $argv[1] : $argv[0]);
                break;
            case 'page':
                $page                  = $argv[0] < 2 ? 0 : $argv[0] - 1;
                $limit                 = $argv[1];
                $offset                = $page * $limit;
                $this->option['limit'] = " LIMIT $offset,$limit";
                break;
            case 'order':$this->option['order'] = ' ORDER BY ' . $argv[0];
                break;
            case 'group':$this->option['group'] = ' GROUP BY ' . $argv[0];
                break;
            case 'where':$this->parseWhere('AND', ...$argv);
                break;
            case 'orwhere':$this->parseWhere('OR', ...$argv);
                break;
            case 'wherein':$this->parseWhereIn('AND', ...$argv);
                break;
            case 'orwherein':$this->parseWhereIn('OR', ...$argv);
                break;
            case 'sql':$this->option['sql'] = $argv[0];
                break;
            case 'sum':case 'max':case 'min':case 'avg':if (! isset($argv[0])) {return null;}
            case 'count':
                $this->option['sql'] = "SELECT {$method}(" . ($argv[0] ?? '*') . ") as result FROM {$this->option['table']} WHERE {$this->option['where']}";
                return $this->execute()->fetch()['result'];
            case 'join':
                $this->join($argv[0], $argv[1], $argv[2] ?? 'INNER');
                break;
            case 'leftjoin':
                $this->leftJoin($argv[0], $argv[1]);
                break;
            case 'rightjoin':
                $this->rightJoin($argv[0], $argv[1]);
                break;
            default:break;
        }
        return $this;
    }
    public function incr(string $key = '', int $num = 1): mixed
    {
        if (! $key || ! isset($this->schema[$key])) {return null;}
        $this->option['sql'] = "UPDATE {$this->option['table']} SET `{$key}` = `{$key}` + {$num} WHERE {$this->option['where']}";
        return $this->execute()->errorCode() == '00000' ? true : false;
    }
    public function decr(string $key = '', int $num = 1): mixed
    {
        if (! $key || ! isset($this->schema[$key])) {return null;}
        $this->option['sql'] = "UPDATE {$this->option['table']} SET `{$key}` = `{$key}` - {$num} WHERE {$this->option['where']}";
        return $this->execute()->errorCode() == '00000' ? true : false;
    }
    public function insert($data = [], $id = false)
    {
        if (! $data) {return null;}
        if (! isset($data[0])) {$data = [$data];}
        foreach ($data as $k => &$v) {
            foreach ($v as $key => &$value) {
                if (! isset($this->schema[$key])) {unset($v[$key]);} else {if (in_array($this->schema[$key][0], ['array', 'json'])) {$value = json_encode($value, 256);}}
            } //删除无效数据
            if ($k == 0) {$keys = '(`' . implode("`,`", array_keys($v)) . '`)';}
            $values[]                = '(' . implode(",", array_fill(0, count($v), '?')) . ')';
            $this->option['execute'] = array_merge($this->option['execute'], array_values($v));
        }
        $this->option['sql'] = "INSERT INTO `{$this->option['table']}` $keys VALUES " . implode(',', $values);
        $h                   = $this->execute();

        return $id ? Db::connection($this->option['connection'])->handler->lastInsertId() : $h;
    }
    public function delete($id = null)
    {
        $this->option['sql'] = $id ? "DELETE FROM {$this->option['table']} WHERE id = $id" : "DELETE FROM {$this->option['table']} WHERE {$this->option['where']}";
        return $this->execute();
    }
    public function update($data = [])
    {
        foreach ($data as $k => $v) {
            if (! isset($this->schema[$k])) {continue;}                                          //清理无效数据
            if (in_array($this->schema[$k][0], ['array', 'json'])) {$v = json_encode($v, 256);} //json数据序列化
            if ($this->option['where'] == '1' && $k == 'id') {
                $this->option['where'] = ' id = ' . $v;
                continue;
            } //当没有条件且数据存在ID的时候将ID作为条件
            $sets[]    = "`$k`=?";
            $execute[] = $v;
        }
        if (! isset($sets)) {return null;}
        $this->option['execute'] = array_merge($execute ?? [], $this->option['execute']); //组合参数
        $set                     = implode(',', $sets);
        $this->option['sql']     = "UPDATE `{$this->option['table']}` SET {$set} WHERE " . $this->option['where'];
        return $this->execute();
    }
    public function select()
    {
        $joinClause = '';
        foreach ($this->option['join'] as $join) {
            $joinClause .= " {$join['type']} JOIN {$join['table']} ON {$join['condition']}";
        }

        $this->option['sql'] = "SELECT {$this->option['field']} FROM {$this->option['table']}{$joinClause} WHERE {$this->option['where']}{$this->option['group']}{$this->option['order']}{$this->option['limit']}{$this->option['lock']}";

        if ($res = $this->execute()?->fetchAll()) {
            foreach ($res as $k => &$v) {$this->parseResult($v);}
        }
        return $res;
    }
    public function find($id = null)
    {
        $joinClause = '';
        foreach ($this->option['join'] as $join) {
            $joinClause .= " {$join['type']} JOIN {$join['table']} ON {$join['condition']}";
        }

        if ($id !== null) {
            $this->option['where'] = "id = " . intval($id);
        }

        $this->option['sql'] = "SELECT {$this->option['field']} FROM `{$this->option['table']}`{$joinClause} WHERE {$this->option['where']}{$this->option['order']} LIMIT 1";

        if ($res = $this->execute()?->fetch()) {
            $this->parseResult($res);
        }
        return $res;
    }
    public function value($column)
    {
        $this->option['sql'] = "SELECT {$column} FROM `{$this->option['table']}` WHERE {$this->option['where']}{$this->option['order']} LIMIT 1";
        $res                 = $this->execute()?->fetch();
        $this->parseResult($res);
        return $res[$column] ?? null;
    }

    public function column($value = '', $key = null, $unsetKey = true)
    {
        $field               = $key ? $key . ',' . $value : $value;
        $n                   = substr_count($field, ',');
        $this->option['sql'] = "SELECT {$field} FROM {$this->option['table']} WHERE {$this->option['where']}{$this->option['group']}{$this->option['order']}{$this->option['limit']}{$this->option['lock']}";
        $res                 = $this->execute()?->fetchAll();
        foreach ($res as $k => &$v) {$this->parseResult($v);}
        if ($key) {
            $tmp = [];
            foreach ($res as $kk => $vv) {
                if ($unsetKey) {
                    $tk = array_shifter($vv, $key);
                } else {
                    $tk = $vv[$key];
                }
                $tmp[$tk] = $n > 1 ? $vv : $vv[$value];
            }
            return $tmp;
        } else {
            return $n == 1 ? $res : array_column($res, $value);
        }
    }
    private function parseWhereIn($connector, $column, $values)
    {
        if (! is_array($values) || empty($values)) {
            return;
        }

        $field        = $this->parseField($column);
        $placeholders = implode(',', array_fill(0, count($values), '?'));
        $this->option['where'] .= " {$connector} {$field} IN ({$placeholders})";
        $this->option['execute'] = array_merge($this->option['execute'], $values);
    }
    private function parseWhere()
    {
        $v = func_get_args(); //连接符 字段 比较符号 值
        switch (count($v)) {
            case 4:
                $field = $this->parseField($v[1]);
                $this->option['where'] .= " {$v[0]} {$field} {$v[2]} ?";
                $this->option['execute'][] = $v[3];
                break;
            case 3:
                $field = $this->parseField($v[1]);
                $this->option['where'] .= " {$v[0]} {$field} = ?";
                $this->option['execute'][] = $v[2];
                break;
            case 2:
                //原生条件语句
                if (is_string($v[1])) {
                    $this->option['where'] .= " {$v[0]} " . $v[1];
                }
                //数组条件语句
                if (is_array($v[1])) {
                    foreach ($v[1] as $k => $value) {
                        $field = $this->parseField($k);
                        $this->option['where'] .= " {$v[0]} {$field} = ?";
                        $this->option['execute'][] = $value;
                    }
                }
                break;
            default:
                // code...
                break;
        }
    } //解析条件

    private function parseField($field)
    {
        if (strpos($field, '.') !== false) {
            // If the field contains a dot, we assume it's already qualified with a table name
            return $field;
        } else {
            // If not, we wrap it in backticks
            return "`{$field}`";
        }
    }
    private function parseResult(&$res = [])
    {
        if (! $res) {
            return;
        }
        foreach ($res as $k => &$v) {
            switch ($this->schema[$k][0] ?? null) {
                case 'array':$v = $v ? (array) json_decode($v, true) : [];
                    break;
                case 'json':$v = $v ? (object) json_decode($v, true) : new \stdclass();
                    break;
                // case 'string':$v ??= '';
                // 	break;
                default:break;
            }
        }
    } //解析结果
    public function execute()
    {
        if ($this->option['debug']) {return loger($this);}
        // loger($this);
        $h = Db::connection($this->option['connection'])->handler->prepare($this->option['sql']);
        $h->execute($this->option['execute']);
        return $h;
    } //执行查询
    public function check($data = [], $rule = [])
    {
        foreach ($this->schema as $k => $v) {$rule[$v[1] . '|' . $k] = $v[0];}
        check($data, $rule)->stop();
        return $this;
    }
    // Add this new method
    public function join($table, $condition, $type = 'INNER')
    {
        $this->option['join'][] = [
            'table'     => $table,
            'condition' => $condition,
            'type'      => strtoupper($type),
        ];
        return $this;
    }

    public function leftJoin($table, $condition)
    {
        return $this->join($table, $condition, 'LEFT');
    }

    public function rightJoin($table, $condition)
    {
        return $this->join($table, $condition, 'RIGHT');
    }
}

class PdoHandler
{

    public function __construct(public $option = [])
    {}

    public function init()
    {
        $dsn = "mysql:host={$this->option['host']};dbname={$this->option['database']};port={$this->option['port']};charset={$this->option['charset']}";
        $opt = [
            PDO::ATTR_DEFAULT_FETCH_MODE => $this->option['fetch_mode'] ?? PDO::FETCH_ASSOC,
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_STRINGIFY_FETCHES  => false,
            PDO::ATTR_EMULATE_PREPARES   => false, // 这2个是跟数字相关的设置
            PDO::ATTR_TIMEOUT => $this->option['timeout'],
            // PDO::ATTR_PERSISTENT=>true,
        ];

        $pool           = new \Stdclass;
        $pool->create   = time();
        $pool->database = $this->option['database'];
        $pool->handler  = new \PDO($dsn, $this->option['username'], $this->option['password'], $opt);
        return $pool;

    }
}

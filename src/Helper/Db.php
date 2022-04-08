<?
declare (strict_types = 1);
namespace lim\Helper;

/**
 *
 */
class Db
{

    public static $opt = [
        'database' => 'default',
        'table'    => null,
        'where'    => [],
        'field'    => '*',
        'limit'    => '',
    ];

    public function __construct()
    {
        // code...
    }

    public function insert($value = '')
    {
        // code...
    }

    public function exec($value = '')
    {
        // code...
    }

    public static function __callStatic($method, $args)
    {
        try {
            wlog('static ' . $method);
            switch ($method) {
                case 'use':
                    wlog('use');
                    break;
                case 'exec':
                    break;
                case 'table' && $table = array_shift($args):
                    self::$opt['table']    = $table;
                    break;
                default:
                    break;
            }
            // print_r(['static',$method, $args]);
            return new self($args);
        } catch (\Swoole\ExitException $e) {
            print_r($e);

        }
    }

    public function __call($method, $args)
    {
        try {
            wlog('call ' . $method);
            switch ($method) {
                //聚合查询
                case 'max':
                case 'min':
                case 'count':
                case 'sum':
                case 'avg':
                    return $this->gather($method, ...$args);
                case 'cols':
                case 'field':
                    $this->_field(...$args);

                case 'order':
                    self::$opt['value'] = $args;
                    return $this;
                case 'join':
                    self::$opt['value'] = $args;
                    return $this;
                case 'insert':
                    self::$opt['value'] = $args;
                    return $this;
                case 'update':
                    self::$opt['value'] = $args;
                    return $this;
                case 'delete':
                    self::$opt['value'] = $args;
                    return $this;
                case 'select':
                    $this->where(...$args);
                    self::$opt['sql'] = "SELECT " . self::$opt['field'] . " FROM " . self::$opt['table'] . " " . $this->_parseWhere() . self::$opt['limit'];
                    break;
                case 'find':
                    $this->where(...$args);
                    self::$opt['sql'] = "SELECT " . self::$opt['field'] . " FROM " . self::$opt['table'] . " " . $this->_parseWhere() . " LIMIT 1";          
                    // wlog($this->sql);
                    return $this;
                default:
                    break;
            }
            // print_r(['call',$method, $args]);
        } catch (\Swoole\ExitException $e) {
            print_r($e);
        }
    }

    public function limit($start = null, $num = null)
    {

        if ($start) {
            $limit = " LIMIT " . $start;
        }

        if ($num) {
            $limit .= ' , ' . $num;
        }

        self::$opt['limit'] = $limit;

        return $this;
    }

    public function field($cols = null)
    {
        if ($cols) {
            self::$opt['field'] = is_array($cols) ? implode(',', $cols) : $cols;
        }
        return $this;
    }

    /**
     * 条件解析
     * @Author   Wayren
     * @DateTime 2022-02-09T14:06:59+0800
     * @param    string                   $k [description]
     * @param    [type]                   $s [description]
     * @param    [type]                   $v [description]
     * @return   [type]                      [description]
     */
    public function where($k = '', $s = null, $v = null)
    {
        if (empty($k)) {
            return $this;
        }
        //解析条件数组
        if (is_array($k)) {
            foreach ($k as $key => $v) {

                if (!is_numeric($key)) {
                    $this->_parseWhere([$key, $v]);
                    continue;
                }
                $this->_parseWhere(is_string($v) ? [$v] : $v);
            }
            return $this;
        }
        //解析单个条件
        $v == null ? ($s == null ? $this->_parseWhere([$k]) : $this->_parseWhere([$k, $s])) : $this->_parseWhere([$k, $s, $v]);
        return $this;
    }

    /**
     * 解析条件
     * @Author   Wayren
     * @DateTime 2022-02-09T12:05:15+0800
     * @param    [type]                   $v [description]
     * @return   [type]                      [description]
     */
    private function _parseWhere($v = null)
    {
        //生成SQL语句
        if ($v == null) {
            if (self::$opt['where']) {
                return ' WHERE ' . implode(' AND ', self::$opt['where']);
            }
            return '';
        }
        //解析条件语句
        $len = count($v);
        switch ($len) {
            case 1:
                self::$opt['where'][] = $v[0];
                break;
            case 2:
                self::$opt['where'][] = '`' . $v[0] . '` = \'' . $v[1] . '\'';
                break;
            case 3:
                switch (strtolower($v[1])) {
                    case 'like':
                        self::$opt['where'][] = $v[0] . ' LIKE \'%' . $v[2] . '%\'';
                        break;
                    case 'in':
                    case 'not in':
                        if (!is_array($v[2])) {
                            return;
                        }
                        self::$opt['where'][] = $v[0] . ' ' . strtoupper($v[1]) . ' (\'' . implode('\',\'', $v[2]) . '\')';
                        break;
                    default:
                        self::$opt['where'][] = $v[0] . ' ' . $v[1] . ' \'' . $v[2] . '\'';
                        break;
                }
                break;
        }
    }

    private function gather($method, $col = '*')
    {
        if (!self::$opt['table']) {
            return null;
        }
        $table = self::$opt['table'] ?? '';
        $act   = strtoupper($method);
        $sql   = "SELECT $act($col) AS $method FROM $table" . $this->_parseWhere() . " LIMIT 1;";
        wlog($sql);
        return 2;
    }

}
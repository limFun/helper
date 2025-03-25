<?php
declare(strict_types=1);
namespace lim;

use Throwable;

/**
 * 查询构建器类
 */
class NQueryBuilder
{
    /**
     * PDO处理器
     * @var object|null
     */
    private ?object $handler = null;
    
    /**
     * 数据库结构
     * @var array
     */
    private array $schema = [];
    
    /**
     * 查询选项
     * @var array
     */
    private array $option = [
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
        'join'       => [],
    ];
    
    /**
     * 析构函数，释放连接
     */
    public function __destruct()
    {
        if (isset($this->option['connection'])) {
            Ndb::init($this->option['connection'])->push(Ndb::connection($this->option['connection']));
            Context::delete('pdo' . $this->option['connection']);
        }
    }
    
    /**
     * 魔术方法，处理链式调用
     * @param string $method 方法名
     * @param array $argv 参数
     * @return mixed
     */
    public function __call(string $method, array $argv): mixed
    {
        switch (strtolower($method)) {
            case 'schema':
                return $this->schema;
                
            case 'debug':
                $this->option['debug'] = true;
                break;
                
            case 'table':
                $this->option['table'] = $argv[0];
                $this->option['connection'] = $argv[1] ?? 'default';
                $this->schema = App::$store['schema'][$this->option['connection']][$argv[0]] ?? [];
                break;
                
            case 'name':
                $this->option['connection'] = $argv[1] ?? 'default';
                $this->option['table'] = config('db.' . $this->option['connection'] . '.prefix') . $argv[0];
                $this->schema = App::$store['schema'][$this->option['connection']][$this->option['table']] ?? [];
                break;
                
            case 'field':
                $this->option['field'] = $argv[0] ?? '*';
                break;
                
            case 'limit':
                $this->option['limit'] = ' LIMIT ' . (isset($argv[1]) ? $argv[0] . ',' . $argv[1] : $argv[0]);
                break;
                
            case 'page':
                $page = $argv[0] < 2 ? 0 : $argv[0] - 1;
                $limit = $argv[1];
                $offset = $page * $limit;
                $this->option['limit'] = " LIMIT $offset,$limit";
                break;
                
            case 'order':
                $this->option['order'] = ' ORDER BY ' . $argv[0];
                break;
                
            case 'group':
                $this->option['group'] = ' GROUP BY ' . $argv[0];
                break;
                
            case 'where':
                $this->parseWhere('AND', ...$argv);
                break;
                
            case 'orwhere':
                $this->parseWhere('OR', ...$argv);
                break;
                
            case 'wherein':
                $this->parseWhereIn('AND', ...$argv);
                break;
                
            case 'orwherein':
                $this->parseWhereIn('OR', ...$argv);
                break;
                
            case 'sql':
                $this->option['sql'] = $argv[0];
                break;
                
            case 'sum':
            case 'max':
            case 'min':
            case 'avg':
                if (!isset($argv[0])) {
                    return null;
                }
                // 继续执行count的逻辑
                
            case 'count':
                $joinClause = $this->buildJoinClause();
                $this->option['sql'] = "SELECT {$method}(" . ($argv[0] ?? '*') . ") as result FROM `{$this->option['table']}`{$joinClause} WHERE {$this->option['where']}";
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
                
            case 'wherebetween':
                $this->whereBetween($argv[0], $argv[1], $argv[2] ?? false);
                break;
                
            case 'wherenotbetween':
                $this->whereBetween($argv[0], $argv[1], true);
                break;
                
            case 'wherenull':
                $this->whereNull($argv[0], false);
                break;
                
            case 'wherenotnull':
                $this->whereNull($argv[0], true);
                break;
                
            case 'wherelike':
                $this->whereLike($argv[0], $argv[1], false);
                break;
                
            case 'wherenotlike':
                $this->whereLike($argv[0], $argv[1], true);
                break;
                
            case 'whereraw':
                $this->whereRaw($argv[0], $argv[1] ?? []);
                break;
                
            default:
                break;
        }
        return $this;
    }
    
    /**
     * 添加JOIN条件
     * @param string $table 表名
     * @param string $condition 条件
     * @param string $type JOIN类型
     * @return $this
     */
    public function join(string $table, string $condition, string $type = 'INNER'): self
    {
        $this->option['join'][] = [
            'table' => $table,
            'condition' => $condition,
            'type' => $type
        ];
        return $this;
    }
    
    /**
     * 添加LEFT JOIN条件
     * @param string $table 表名
     * @param string $condition 条件
     * @return $this
     */
    public function leftJoin(string $table, string $condition): self
    {
        return $this->join($table, $condition, 'LEFT');
    }
    
    /**
     * 添加RIGHT JOIN条件
     * @param string $table 表名
     * @param string $condition 条件
     * @return $this
     */
    public function rightJoin(string $table, string $condition): self
    {
        return $this->join($table, $condition, 'RIGHT');
    }
    
    /**
     * 构建JOIN子句
     * @return string
     */
    private function buildJoinClause(): string
    {
        $joinClause = '';
        foreach ($this->option['join'] as $join) {
            $joinClause .= " {$join['type']} JOIN {$join['table']} ON {$join['condition']}";
        }
        return $joinClause;
    }
    
    /**
     * BETWEEN条件查询
     * @param string $column 字段名
     * @param array $values 范围值 [最小值, 最大值]
     * @param bool $not 是否为NOT BETWEEN
     * @return $this
     */
    public function whereBetween(string $column, array $values, bool $not = false): self
    {
        if (count($values) !== 2) {
            return $this;
        }
        
        $field = $this->parseField($column);
        $operator = $not ? 'NOT BETWEEN' : 'BETWEEN';
        $this->option['where'] .= " AND {$field} {$operator} ? AND ?";
        $this->option['execute'][] = $values[0];
        $this->option['execute'][] = $values[1];
        
        return $this;
    }
    
    /**
     * NULL条件查询
     * @param string $column 字段名
     * @param bool $not 是否为NOT NULL
     * @return $this
     */
    public function whereNull(string $column, bool $not = false): self
    {
        $field = $this->parseField($column);
        $operator = $not ? 'IS NOT NULL' : 'IS NULL';
        $this->option['where'] .= " AND {$field} {$operator}";
        
        return $this;
    }
    
    /**
     * LIKE条件查询
     * @param string $column 字段名
     * @param string $value 模糊匹配值
     * @param bool $not 是否为NOT LIKE
     * @return $this
     */
    public function whereLike(string $column, string $value, bool $not = false): self
    {
        $field = $this->parseField($column);
        $operator = $not ? 'NOT LIKE' : 'LIKE';
        $this->option['where'] .= " AND {$field} {$operator} ?";
        $this->option['execute'][] = $value;
        
        return $this;
    }
    
    /**
     * 原生WHERE条件
     * @param string $rawWhere 原生WHERE条件
     * @param array $bindings 绑定参数
     * @return $this
     */
    public function whereRaw(string $rawWhere, array $bindings = []): self
    {
        $this->option['where'] .= " AND ({$rawWhere})";
        if (!empty($bindings)) {
            $this->option['execute'] = array_merge($this->option['execute'], $bindings);
        }
        
        return $this;
    }
    
    /**
     * 增加字段值
     * @param string $key 字段名
     * @param float|int $num 增加的值
     * @return bool|null
     */
    public function incr(string $key = '', float|int $num = 1): ?bool
    {
        if (!$key || !isset($this->schema[$key])) {
            return null;
        }
        $this->option['sql'] = "UPDATE `{$this->option['table']}` SET `{$key}` = `{$key}` + {$num} WHERE {$this->option['where']}";
        return $this->execute()->errorCode() == '00000';
    }
    
    /**
     * 减少字段值
     * @param string $key 字段名
     * @param float|int $num 减少的值
     * @return bool|null
     */
    public function decr(string $key = '', float|int $num = 1): ?bool
    {
        if (!$key || !isset($this->schema[$key])) {
            return null;
        }
        $this->option['sql'] = "UPDATE `{$this->option['table']}` SET `{$key}` = `{$key}` - {$num} WHERE {$this->option['where']}";
        return $this->execute()->errorCode() == '00000';
    }
    
    /**
     * 执行SQL语句
     * @param string|null $sql SQL语句
     * @return object|null PDOStatement或null
     */
    public function execute(?string $sql = null): ?object
    {
        if ($sql) {
            $this->option['sql'] = $sql;
        }
        
        if (empty($this->option['sql'])) {
            return null;
        }
        
        try {
            $handler = Ndb::connection($this->option['connection'])->handler;
            
            if ($this->option['debug']) {
                loger($this->option['sql']);
                loger($this->option['execute']);
            }
            
            if (!empty($this->option['execute'])) {
                $stmt = $handler->prepare($this->option['sql']);
                $stmt->execute($this->option['execute']);
            } else {
                $stmt = $handler->query($this->option['sql']);
            }
            
            // 重置执行参数
            $this->option['execute'] = [];
            $this->option['sql'] = '';
            
            return $stmt;
        } catch (Throwable $e) {
            loger("SQL执行错误: {$e->getMessage()}, SQL: {$this->option['sql']}");
            if ($this->option['debug']) {
                loger($this->option['execute']);
            }
            throw $e;
        }
    }
    
    /**
     * 解析WHERE条件
     * @param string $connector 连接符
     * @param mixed ...$args 参数
     */
    private function parseWhere(string $connector, ...$args): void
    {
        switch (count($args)) {
            case 3:
                // 形式: where('field', 'operator', 'value')
                $field = $this->parseField($args[0]);
                $this->option['where'] .= " {$connector} {$field} {$args[1]} ?";
                $this->option['execute'][] = $args[2];
                break;
                
            case 2:
                // 形式: where('field', 'value')
                if (is_string($args[0])) {
                    $field = $this->parseField($args[0]);
                    $this->option['where'] .= " {$connector} {$field} = ?";
                    $this->option['execute'][] = $args[1];
                }
                // 形式: where(['field' => 'value', ...])
                elseif (is_array($args[0]) && is_array($args[1])) {
                    foreach ($args[1] as $k => $value) {
                        $field = $this->parseField($k);
                        $this->option['where'] .= " {$connector} {$field} = ?";
                        $this->option['execute'][] = $value;
                    }
                }
                // 形式: where('raw sql')
                elseif (is_string($args[0]) && is_string($args[1])) {
                    $this->option['where'] .= " {$connector} " . $args[1];
                }
                break;
                
            case 1:
                // 形式: where('raw sql')
                if (is_string($args[0])) {
                    $this->option['where'] .= " {$connector} " . $args[0];
                }
                // 形式: where(['field' => 'value', ...])
                elseif (is_array($args[0])) {
                    foreach ($args[0] as $k => $value) {
                        $field = $this->parseField($k);
                        $this->option['where'] .= " {$connector} {$field} = ?";
                        $this->option['execute'][] = $value;
                    }
                }
                break;
                
            default:
                break;
        }
    }
    
    /**
     * 解析WHERE IN条件
     * @param string $connector 连接符
     * @param string $column 字段
     * @param array $values 值数组
     */
    private function parseWhereIn(string $connector, string $column, array $values): void
    {
        if (empty($values)) {
            return;
        }
        
        $field = $this->parseField($column);
        $placeholders = implode(',', array_fill(0, count($values), '?'));
        $this->option['where'] .= " {$connector} {$field} IN ({$placeholders})";
        $this->option['execute'] = array_merge($this->option['execute'], $values);
    }
    
    /**
     * 解析字段名
     * @param string $field 字段名
     * @return string 处理后的字段名
     */
    private function parseField(string $field): string
    {
        if (strpos($field, '.') !== false) {
            // 如果字段包含点，假设它已经有表名限定
            return $field;
        } else {
            // 如果没有，用反引号包裹
            return "`{$field}`";
        }
    }
    
    /**
     * 解析结果
     * @param array|null $res 结果
     */
    private function parseResult(?array &$res = []): void
    {
        if (!$res) {
            return;
        }
        
        foreach ($res as $k => &$v) {
            switch ($this->schema[$k][0] ?? null) {
                case 'array':
                    $v = $v ? (array)json_decode($v, true) : [];
                    break;
                    
                case 'json':
                    $v = $v ? (object)json_decode($v, true) : new \stdClass();
                    break;
                    
                default:
                    break;
            }
        }
    }
    
    /**
     * 插入数据
     * @param array $data 数据
     * @param bool $id 是否返回插入ID
     * @return mixed
     */
    public function insert(array $data = [], bool $id = false): mixed
    {
        if (empty($data)) {
            return null;
        }
        
        // 如果不是二维数组，转换为二维数组
        if (!isset($data[0]) || !is_array($data[0])) {
            $data = [$data];
        }
        
        $values = [];
        foreach ($data as $k => &$v) {
            // 过滤无效数据并处理特殊类型
            foreach ($v as $key => &$value) {
                if (!isset($this->schema[$key])) {
                    unset($v[$key]);
                } elseif (in_array($this->schema[$key][0], ['array', 'json'])) {
                    $value = json_encode($value, 256);
                }
            }
            
            // 第一个元素用于构建字段列表
            if ($k == 0) {
                $keys = '(`' . implode("`,`", array_keys($v)) . '`)';
            }
            
            $values[] = '(' . implode(",", array_fill(0, count($v), '?')) . ')';
            $this->option['execute'] = array_merge($this->option['execute'], array_values($v));
        }
        
        if (empty($values)) {
            return null;
        }
        
        $this->option['sql'] = "INSERT INTO `{$this->option['table']}` {$keys} VALUES " . implode(',', $values);
        $stmt = $this->execute();
        
        return $id ? Ndb::connection($this->option['connection'])->handler->lastInsertId() : $stmt;
    }
    
    /**
     * 删除数据
     * @param int|null $id ID
     * @return object PDOStatement
     */
    public function delete(?int $id = null): object
    {
        if ($id !== null) {
            $this->option['where'] = 'id = ?';
            $this->option['execute'] = [$id];
        }
        
        $this->option['sql'] = "DELETE FROM `{$this->option['table']}` WHERE {$this->option['where']}";
        return $this->execute();
    }
    
    /**
     * 更新数据
     * @param array $data 数据
     * @param int|null $id ID
     * @return object PDOStatement
     */
    public function update(array $data = [], ?int $id = null): object
    {
        if (empty($data)) {
            throw new \InvalidArgumentException('更新数据不能为空');
        }
        
        if ($id !== null) {
            $this->option['where'] = 'id = ?';
            $this->option['execute'] = [$id];
        }
        
        $sets = [];
        foreach ($data as $key => $value) {
            if (!isset($this->schema[$key])) {
                continue;
            }
            
            if (in_array($this->schema[$key][0], ['array', 'json'])) {
                $value = json_encode($value, 256);
            }
            
            $sets[] = "`{$key}` = ?";
            $this->option['execute'][] = $value;
        }
        
        if (empty($sets)) {
            throw new \InvalidArgumentException('没有有效的更新字段');
        }
        
        $this->option['sql'] = "UPDATE `{$this->option['table']}` SET " . implode(',', $sets) . " WHERE {$this->option['where']}";
        return $this->execute();
    }
    
    /**
     * 查询单条记录
     * @param int|null $id ID
     * @return array|null 记录
     */
    public function find(?int $id = null): ?array
    {
        if ($id !== null) {
            $this->option['where'] = 'id = ?';
            $this->option['execute'] = [$id];
        }
        
        $joinClause = $this->buildJoinClause();
        $this->option['sql'] = "SELECT {$this->option['field']} FROM `{$this->option['table']}`{$joinClause} WHERE {$this->option['where']}{$this->option['order']} LIMIT 1";
        $res = $this->execute()->fetch();
        
        if ($res) {
            $this->parseResult($res);
        }
        
        return $res ?: null;
    }
    
    /**
     * 查询多条记录
     * @return array 记录列表
     */
    public function select(): array
    {
        $joinClause = $this->buildJoinClause();
        $this->option['sql'] = "SELECT {$this->option['field']} FROM `{$this->option['table']}`{$joinClause} WHERE {$this->option['where']}{$this->option['group']}{$this->option['order']}{$this->option['limit']}{$this->option['lock']}";
        $res = $this->execute()->fetchAll();
        
        if ($res) {
            foreach ($res as &$row) {
                $this->parseResult($row);
            }
        }
        
        return $res ?: [];
    }
    
    /**
     * 添加锁
     * @param string $type 锁类型
     * @return $this
     */
    public function lock(string $type = 'FOR UPDATE'): self
    {
        $this->option['lock'] = ' ' . $type;
        return $this;
    }
    
    /**
     * 分页查询
     * @param int $page 页码
     * @param int $size 每页数量
     * @return array 分页数据
     */
    public function paginate(int $page = 1, int $size = 15): array
    {
        // 计算总记录数
        $total = $this->count();
        
        // 设置分页参数
        $this->page($page, $size);
        
        // 查询当前页数据
        $data = $this->select();
        
        // 计算总页数
        $pages = ceil($total / $size);
        
        return [
            'total' => $total,
            'page' => $page,
            'size' => $size,
            'pages' => $pages,
            'data' => $data
        ];
    }
    
    /**
     * 获取第一列数据
     * @param string $column 列名
     * @return array 列数据
     */
    public function column(string $column): array
    {
        $this->option['field'] = $column;
        $data = $this->select();
        
        if (empty($data)) {
            return [];
        }
        
        return array_column($data, $column);
    }
    
    /**
     * 获取键值对数组
     * @param string $key 键名
     * @param string $value 值名
     * @return array 键值对数组
     */
    public function keyValue(string $key, string $value): array
    {
        $this->option['field'] = "{$key},{$value}";
        $data = $this->select();
        
        if (empty($data)) {
            return [];
        }
        
        $result = [];
        foreach ($data as $row) {
            $result[$row[$key]] = $row[$value];
        }
        
        return $result;
    }
    
    /**
     * 获取单个值
     * @param string $field 字段名
     * @return mixed 字段值
     */
    public function value(string $field): mixed
    {
        $this->option['field'] = $field;
        $res = $this->find();
        
        return $res[$field] ?? null;
    }
    
    /**
     * 批量插入或更新
     * @param array $data 数据
     * @param array $update 更新字段
     * @return object PDOStatement
     */
    public function insertOrUpdate(array $data, array $update): object
    {
        if (empty($data) || empty($update)) {
            throw new \InvalidArgumentException('数据或更新字段不能为空');
        }
        
        // 如果不是二维数组，转换为二维数组
        if (!isset($data[0]) || !is_array($data[0])) {
            $data = [$data];
        }
        
        $values = [];
        foreach ($data as $k => &$v) {
            // 过滤无效数据并处理特殊类型
            foreach ($v as $key => &$value) {
                if (!isset($this->schema[$key])) {
                    unset($v[$key]);
                } elseif (in_array($this->schema[$key][0], ['array', 'json'])) {
                    $value = json_encode($value, 256);
                }
            }
            
            // 第一个元素用于构建字段列表
            if ($k == 0) {
                $keys = '(`' . implode("`,`", array_keys($v)) . '`)';
            }
            
            $values[] = '(' . implode(",", array_fill(0, count($v), '?')) . ')';
            $this->option['execute'] = array_merge($this->option['execute'], array_values($v));
        }
        
        if (empty($values)) {
            return null;
        }
        
        // 构建ON DUPLICATE KEY UPDATE部分
        $updateParts = [];
        foreach ($update as $field) {
            if (isset($this->schema[$field])) {
                $updateParts[] = "`{$field}` = VALUES(`{$field}`)";
            }
        }
        
        if (empty($updateParts)) {
            throw new \InvalidArgumentException('没有有效的更新字段');
        }
        
        $this->option['sql'] = "INSERT INTO `{$this->option['table']}` {$keys} VALUES " . implode(',', $values) . 
                              " ON DUPLICATE KEY UPDATE " . implode(',', $updateParts);
        
        return $this->execute();
    }
    
    /**
     * 执行事务
     * @param callable $callback 回调函数
     * @return mixed 回调函数返回值
     */
    public function transaction(callable $callback): mixed
    {
        return Ndb::transaction($this->option['connection'], $callback);
    }
    
    /**
     * 开始事务
     * @return bool 是否成功
     */
    public function beginTransaction(): bool
    {
        return Ndb::transaction($this->option['connection']);
    }
    
    /**
     * 提交事务
     * @return bool 是否成功
     */
    public function commit(): bool
    {
        return Ndb::commit();
    }
    
    /**
     * 回滚事务
     * @return bool 是否成功
     */
    public function rollback(): bool
    {
        return Ndb::rollback();
    }
}
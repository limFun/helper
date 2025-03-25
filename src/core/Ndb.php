<?php
declare(strict_types=1);
namespace lim;

use PDO;
use PDOException;
use Throwable;

/**
 * 数据库操作类
 * 支持连接池、事务、查询构建器等功能
 */
class Ndb
{
    /**
     * 数据库连接池
     * @var array
     */
    protected static array $pool = [];
    
    /**
     * 当前连接名
     * @var string
     */
    protected static string $connection = 'default';
    
    /**
     * 最大重试次数
     * @var int
     */
    protected static int $maxRetries = 3;
    
    /**
     * 初始化数据库连接池
     * @param string $connection 连接名
     * @return object 连接对象
     */
    public static function init(string $connection = 'default'): object
    {
        if (empty(self::$pool)) {
            $configs = config('db');
            if (empty($configs)) {
                throw new \RuntimeException('数据库配置不存在');
            }
            
            foreach ($configs as $name => $config) {
                if (class_exists('Swoole\Database\PDOPool')) {
                    // 使用 Swoole 官方连接池
                    $poolConfig = new \Swoole\Database\PDOConfig();
                    $poolConfig->withHost($config['host'])
                        ->withPort($config['port'])
                        ->withDbName($config['database'])
                        ->withCharset($config['charset'])
                        ->withUsername($config['username'])
                        ->withPassword($config['password']);
                    
                    // 设置PDO属性
                    $poolConfig->withOptions([
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$config['charset']}"
                    ]);
                    
                    // 创建连接池，默认大小为10
                    self::$pool[$name] = new \Swoole\Database\PDOPool($poolConfig, 10);
                } else {
                    // 非Swoole环境使用普通连接
                    self::$pool[$name] = (new NdbHandler($config))->init();
                }
            }
        }
        
        if (!isset(self::$pool[$connection])) {
            throw new \RuntimeException("数据库连接 '{$connection}' 不存在");
        }
        
        self::$connection = $connection;
        return self::$pool[$connection];
    }
    
    /**
     * 获取数据库连接
     * @param string $connection 连接名
     * @return object 连接对象
     */
    public static function connection(string $connection = 'default'): object
    {
        if (class_exists('Swoole\Database\PDOPool')) {
            $contextKey = 'pdo' . $connection;
            if (!$pdo = Context::get($contextKey)) {
                try {
                    if (!isset(self::$pool[$connection])) {
                        self::init($connection);
                    }
                    // 从连接池获取连接
                    $pdo = self::$pool[$connection]->get();
                    Context::set($contextKey, $pdo);
                } catch (Throwable $e) {
                    self::handleConnectionError($e, $connection);
                }
            }
            return $pdo;
        } else {
            if (!isset(self::$pool[$connection])) {
                self::init($connection);
            }
            return self::$pool[$connection];
        }
    }
    
    /**
     * 处理连接错误
     * @param Throwable $e 异常
     * @param string $connection 连接名
     * @throws Throwable 如果重试失败，抛出原始异常
     */
    protected static function handleConnectionError(Throwable $e, string $connection): void
    {
        loger("数据库连接失败: {$e->getMessage()}, 尝试重连...");
        
        for ($i = 0; $i < self::$maxRetries; $i++) {
            try {
                $pdo = self::init($connection)->pull();
                Context::set('pdo' . $connection, $pdo);
                loger("数据库重连成功");
                return;
            } catch (Throwable $retryError) {
                loger("数据库重连失败 ({$i}): {$retryError->getMessage()}");
                usleep(100000); // 等待100ms后重试
            }
        }
        
        throw $e; // 重试失败，抛出原始异常
    }
    
    /**
     * 开启事务
     * @param string $connection 连接名
     * @param callable|null $callback 回调函数
     * @return mixed 回调函数的返回值
     * @throws Throwable 事务执行失败时抛出异常
     */
    public static function transaction(string $connection = 'default', callable $callback = null): mixed
    {
        self::init($connection);
        $pdo = self::connection(self::$connection)->handler;
        
        try {
            // 如果已经在事务中，直接执行回调
            if ($pdo->inTransaction()) {
                return $callback ? $callback() : null;
            }
            
            // 开启事务
            $pdo->beginTransaction();
            
            if ($callback) {
                $result = $callback();
                $pdo->commit();
                return $result;
            }
            
            return true;
        } catch (Throwable $e) {
            // 如果事务已开启则回滚
            if ($pdo && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            loger("事务执行失败: {$e->getMessage()}");
            throw $e;
        } finally {
            if (!$callback) {
                self::releaseConnection();
            }
        }
    }
    
    /**
     * 提交事务
     * @return bool 是否成功
     */
    public static function commit(): bool
    {
        $pdo = self::connection(self::$connection)->handler;
        
        try {
            if (!$pdo->inTransaction()) {
                throw new PDOException('没有活动的事务可提交');
            }
            
            $result = $pdo->commit();
            return $result;
        } finally {
            self::releaseConnection();
        }
    }
    
    /**
     * 回滚事务
     * @return bool 是否成功
     */
    public static function rollback(): bool
    {
        $pdo = self::connection(self::$connection)->handler;
        
        try {
            if (!$pdo->inTransaction()) {
                throw new PDOException('没有活动的事务可回滚');
            }
            
            $result = $pdo->rollBack();
            return $result;
        } finally {
            self::releaseConnection();
        }
    }
    
    /**
     * 释放连接回连接池
     */
    protected static function releaseConnection(): void
    {
        if (class_exists('Swoole\Database\PDOPool')) {
            $pdo = Context::get('pdo' . self::$connection);
            if ($pdo) {
                // 归还连接到连接池
                self::$pool[self::$connection]->put($pdo);
                Context::delete('pdo' . self::$connection);
            }
        }
        self::$connection = 'default';
    }
    
    /**
     * 缓存数据库结构
     * @return void
     */
    public static function schema(): void
    {
        self::init();
        $config = [];
        foreach (config('db') as $con => $d) {
            try {
                $sql = "SELECT TABLE_NAME,COLUMN_NAME,DATA_TYPE,COLUMN_COMMENT 
                        FROM INFORMATION_SCHEMA.COLUMNS 
                        WHERE TABLE_SCHEMA = :database 
                        ORDER BY TABLE_NAME ASC,ORDINAL_POSITION ASC";
                
                $stmt = self::connection($con)->handler->prepare($sql);
                $stmt->execute(['database' => $d['database']]);
                $res = $stmt->fetchAll();
                
                foreach ($res as $v) {
                    $type = self::mapDataType($v['DATA_TYPE']);
                    $commit = empty($v['COLUMN_COMMENT']) ? $v['COLUMN_NAME'] : $v['COLUMN_COMMENT'];
                    
                    if (str_contains($commit, '|')) {
                        [$commit, $type] = explode('|', $commit);
                    }
                    
                    if (str_contains($commit, ':')) {
                        [$commit, $e] = explode(':', $commit);
                    }
                    
                    $config[$con][$v['TABLE_NAME']][$v['COLUMN_NAME']] = [$type, $commit];
                }
            } catch (Throwable $e) {
                loger("获取数据库结构失败: {$e->getMessage()}");
            }
        }
        
        $configContent = "<?php\nreturn " . str_replace(['{', '}', ':'], ['[', ']', '=>'], json_encode($config, 256)) . ";\n";
        file_put_contents(ROOT_PATH . 'config/schema.php', $configContent);
        loger('缓存数据库schema成功');
    }
    
    /**
     * 映射数据类型
     * @param string $dataType 数据库类型
     * @return string PHP类型
     */
    protected static function mapDataType(string $dataType): string
    {
        return match($dataType) {
            'int', 'bigint', 'tinyint', 'decimal' => 'numeric',
            'varchar', 'text' => 'string',
            default => $dataType,
        };
    }
    
    /**
     * 魔术方法，调用查询构建器
     * @param string $method 方法名
     * @param array $arguments 参数
     * @return mixed
     */
    public static function __callStatic(string $method, array $arguments): mixed
    {
        self::init();
        return call_user_func_array([new NQueryBuilder, $method], $arguments);
    }
    
    /**
     * 设置最大重试次数
     * @param int $retries 重试次数
     */
    public static function setMaxRetries(int $retries): void
    {
        self::$maxRetries = $retries;
    }
}

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
            $this->option['sql'] = "DELETE FROM `{$this->option['table']}` WHERE id = " . intval($id);
        } else {
            $this->option['sql'] = "DELETE FROM `{$this->option['table']}` WHERE {$this->option['where']}";
        }
        return $this->execute();
    }
    
    /**
     * 更新数据
     * @param array $data 数据
     * @return object|null PDOStatement或null
     */
    public function update(array $data = []): ?object
    {
        $sets = [];
        $execute = [];
        
        foreach ($data as $k => $v) {
            // 清理无效数据
            if (!isset($this->schema[$k])) {
                continue;
            }
            
            // JSON数据序列化
            if (in_array($this->schema[$k][0], ['array', 'json'])) {
                $v = json_encode($v, 256);
            }
            
            // 当没有条件且数据存在ID的时候将ID作为条件
            if ($this->option['where'] == '1' && $k == 'id') {
                $this->option['where'] = 'id = ' . intval($v);
                continue;
            }
            
            $sets[] = "`{$k}`=?";
            $execute[] = $v;
        }
        
        if (empty($sets)) {
            return null;
        }
        
        // 组合参数
        $this->option['execute'] = array_merge($execute, $this->option['execute']);
        $set = implode(',', $sets);
        $this->option['sql'] = "UPDATE `{$this->option['table']}` SET {$set} WHERE {$this->option['where']}";
        
        return $this->execute();
    }
    
    /**
     * 查询多条数据
     * @return array|null 结果集
     */
    public function select(): ?array
    {
        $joinClause = $this->buildJoinClause();
        $this->option['sql'] = "SELECT {$this->option['field']} FROM `{$this->option['table']}`{$joinClause} WHERE {$this->option['where']}{$this->option['group']}{$this->option['order']}{$this->option['limit']}{$this->option['lock']}";
        
        $res = $this->execute()?->fetchAll();
        if ($res) {
            foreach ($res as &$v) {
                $this->parseResult($v);
            }
        }
        return $res;
    }
    
    /**
     * 分页查询
     * @param array $options 分页选项
     * @return array 结果集
     */
    public function list(array $options = []): array
    {
        // 处理分页参数
        if (!$this->option['limit']) {
            $page = ($options['page'] ?? 1) < 2 ? 0 : ($options['page'] ?? 1) - 1;
            $limit = $options['limit'] ?? 10;
            $offset = $page * $limit;
            $this->option['limit'] = " LIMIT {$offset},{$limit}";
        }
        
        $joinClause = $this->buildJoinClause();
        $this->option['sql'] = "SELECT {$this->option['field']} FROM `{$this->option['table']}`{$joinClause} WHERE {$this->option['where']}{$this->option['group']}{$this->option['order']}{$this->option['limit']}{$this->option['lock']}";
        
        // 获取总数
        $countSql = "SELECT COUNT(1) AS total FROM `{$this->option['table']}`{$joinClause} WHERE {$this->option['where']}";
        $result['count'] = $this->execute($countSql)?->fetch()['total'] ?? 0;
        
        // 获取数据列表
        $res = $this->execute()?->fetchAll();
        if ($res) {
            foreach ($res as &$v) {
                $this->parseResult($v);
            }
        }
        $result['list'] = $res ?? [];
        
        return $result;
    }
    
    /**
     * 查询单条数据
     * @param int|null $id ID
     * @return array|null 结果
     */
    public function find(?int $id = null): ?array
    {
        $joinClause = $this->buildJoinClause();
        
        if ($id !== null) {
            $this->option['where'] = "id = " . intval($id);
        }
        
        $this->option['sql'] = "SELECT {$this->option['field']} FROM `{$this->option['table']}`{$joinClause} WHERE {$this->option['where']}{$this->option['order']} LIMIT 1";
        
        $res = $this->execute()?->fetch();
        if ($res) {
            $this->parseResult($res);
        }
        
        return $res;
    }
    
    /**
     * 获取单个字段值
     * @param string $column 字段名
     * @return mixed 字段值
     */
    public function value(string $column): mixed
    {
        $this->option['sql'] = "SELECT {$column} FROM `{$this->option['table']}` WHERE {$this->option['where']}{$this->option['order']} LIMIT 1";
        $res = $this->execute()?->fetch();
        
        if ($res) {
            $this->parseResult($res);
        }
        
        return $res[$column] ?? null;
    }
    
    /**
     * 获取列数据
     * @param string $value 值字段
     * @param string|null $key 键字段
     * @param bool $unsetKey 是否移除键字段
     * @return array 结果集
     */
    public function column(string $value = '', ?string $key = null, bool $unsetKey = true): array
    {
        $field = $key ? "{$key},{$value}" : $value;
        $n = substr_count($field, ',');
        
        $this->option['sql'] = "SELECT {$field} FROM `{$this->option['table']}` WHERE {$this->option['where']}{$this->option['group']}{$this->option['order']}{$this->option['limit']}{$this->option['lock']}";
        $res = $this->execute()?->fetchAll() ?? [];
        
        foreach ($res as &$v) {
            $this->parseResult($v);
        }
        
        if ($key) {
            $tmp = [];
            foreach ($res as $vv) {
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
     * 获取最后执行的SQL语句
     * @return string SQL语句
     */
    public function getLastSql(): string
    {
        return $this->option['sql'];
    }
}

/**
 * PDO处理器类
 */
class NdbHandler
{
    /**
     * PDO实例
     * @var PDO|null
     */
    public ?PDO $handler = null;
    
    /**
     * 数据库配置
     * @var array
     */
    private array $config = [];
    
    /**
     * 构造函数
     * @param array $config 数据库配置
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }
    
    /**
     * 初始化PDO连接
     * @return $this
     */
    public function init(): self
    {
        if ($this->handler === null) {
            $this->connect();
        }
        return $this;
    }
    
    /**
     * 连接数据库
     * @throws PDOException 连接失败时抛出异常
     */
    private function connect(): void
    {
        try {
            $dsn = "mysql:host={$this->config['host']};port={$this->config['port']};dbname={$this->config['database']};charset={$this->config['charset']}";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$this->config['charset']}"
            ];
            
            $this->handler = new PDO($dsn, $this->config['username'], $this->config['password'], $options);
        } catch (PDOException $e) {
            loger("数据库连接失败: {$e->getMessage()}");
            throw $e;
        }
    }
    
    /**
     * 检查连接状态并尝试重连
     * @return bool 是否连接成功
     */
    public function checkConnection(): bool
    {
        if ($this->handler === null) {
            $this->connect();
            return $this->handler !== null;
        }
        
        try {
            $this->handler->query('SELECT 1');
            return true;
        } catch (PDOException $e) {
            loger("数据库连接已断开，尝试重连: {$e->getMessage()}");
            $this->handler = null;
            $this->connect();
            return $this->handler !== null;
        }
    }
    
    /**
     * 关闭连接
     */
    public function close(): void
    {
        $this->handler = null;
    }
}

/**
 * 上下文管理类
 */
class Context
{
    /**
     * 上下文数据
     * @var array
     */
    private static array $context = [];
    
    /**
     * 设置上下文数据
     * @param string $key 键名
     * @param mixed $value 值
     */
    public static function set(string $key, mixed $value): void
    {
        self::$context[$key] = $value;
    }
    
    /**
     * 获取上下文数据
     * @param string $key 键名
     * @return mixed 值
     */
    public static function get(string $key): mixed
    {
        return self::$context[$key] ?? null;
    }
    
    /**
     * 删除上下文数据
     * @param string $key 键名
     */
    public static function delete(string $key): void
    {
        unset(self::$context[$key]);
    }
    
    /**
     * 清空上下文数据
     */
    public static function clear(): void
    {
        self::$context = [];
    }
}

/**
 * 辅助函数：从数组中移除并返回指定键的值
 * @param array $array 数组
 * @param string $key 键名
 * @return mixed 值
 */
function array_shifter(array &$array, string $key): mixed
{
    $value = $array[$key] ?? null;
    unset($array[$key]);
    return $value;
}
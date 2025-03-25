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
<?php
declare(strict_types=1);
namespace lim;

use PDO;
use PDOException;

/**
 * PDO处理器类
 */
class NdbHandler
{
    /**
     * 数据库配置
     * @var array
     */
    private array $config;
    
    /**
     * PDO实例
     * @var PDO|null
     */
    public ?PDO $handler = null;
    
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
     * @throws PDOException
     */
    public function init(): self
    {
        try {
            $dsn = "mysql:host={$this->config['host']};port={$this->config['port']};dbname={$this->config['database']};charset={$this->config['charset']}";
            $this->handler = new PDO(
                $dsn,
                $this->config['username'],
                $this->config['password'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$this->config['charset']}"
                ]
            );
        } catch (PDOException $e) {
            loger("数据库连接失败: {$e->getMessage()}");
            throw $e;
        }
        
        return $this;
    }
    
    /**
     * 获取PDO实例
     * @return PDO|null
     */
    public function pull(): ?PDO
    {
        return $this->handler;
    }
    
    /**
     * 设置PDO实例
     * @param PDO $pdo PDO实例
     * @return $this
     */
    public function push(PDO $pdo): self
    {
        $this->handler = $pdo;
        return $this;
    }
}
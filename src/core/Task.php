<?
declare (strict_types = 1);
namespace lim;
use Exception;

class Task {
    private static $tasks = [];
    private $taskName;
    private $timer;
    private $class;
    private $method;
    private $options;
    
    public function __construct($name) {
        $this->taskName = $name;
    }

    /**
     * 创建新的任务实例
     * @param string $name 任务名称
     * @return Task
     */
    public static function name($name) {
        $task = new self($name);
        self::$tasks[] = $task;
        return $task;
    }

    /**
     * 设置定时器表达式
     * @param string $expression cron表达式
     * @return $this
     */
    public function timer($expression) {
        $this->timer = $expression;
        return $this;
    }

    /**
     * 设置要调用的类和方法
     * @param string $class 类名
     * @param string $method 方法名
     * @return $this
     */
    public function call($class, $method) {
        $this->class = $class;
        $this->method = $method;
        return $this;
    }

    /**
     * 设置可选参数
     * @param array $options 参数数组
     * @return $this
     */
    public function option($options) {
        $this->options = $options;
        return $this;
    }

    /**
     * 运行所有注册的任务
     */
    public static function run() {
        // 创建定时器
        \Swoole\Timer::tick(1000, function () {
            $now = time();
            foreach (self::$tasks as $task) {
                if ($task->shouldRun($now)) {
                    $task->execute();
                }
            }
        });
    }

    /**
     * 检查任务是否应该在当前时间运行
     * @param int $now 当前时间戳
     * @return bool
     */
    private function shouldRun($now) {
        $date = new \DateTime('@' . $now);
        return $this->matchCron($this->timer, $date);
    }

    /**
     * 执行任务
     */
    private function execute() {
        if (class_exists($this->class)) {
            $instance = new $this->class();
            if (method_exists($instance, $this->method)) {
                if ($this->options) {
                    call_user_func_array([$instance, $this->method], [$this->options]);
                } else {
                    call_user_func([$instance, $this->method]);
                }
            }
        }
    }

    /**
     * 匹配cron表达式
     * @param string $expression cron表达式
     * @param \DateTime $date 要检查的时间
     * @return bool
     */
    private function matchCron($expression, $date) {
        $parts = explode(' ', $expression);
        if (count($parts) !== 6) {
            return false;
        }

        list($second, $minute, $hour, $day, $month, $weekday) = $parts;

        // 处理 /n 格式
        if (strpos($second, '/') !== false) {
            $n = (int)substr($second, 1);
            return (int)$date->format('s') % $n === 0;
        }

        if ($second !== '*' && $second != $date->format('s')) return false;
        if ($minute !== '*' && $minute != $date->format('i')) return false;
        if ($hour !== '*' && $hour != $date->format('H')) return false;
        if ($day !== '*' && $day != $date->format('d')) return false;
        if ($month !== '*' && $month != $date->format('m')) return false;
        if ($weekday !== '*' && $weekday != $date->format('w')) return false;

        return true;
    }
} 
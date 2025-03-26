<?php
declare (strict_types = 1);
namespace lim;

/**
 * 上下文管理类
 * 用于在协程环境中存储和获取数据
 */
class Context
{
    /**
     * 存储数据
     * @var array
     */
    private static array $container = [];

    /**
     * 设置上下文数据
     * @param string $key 键名
     * @param mixed $value 值
     * @return void
     */
    public static function set(string $key, mixed $value): void
    {
        if (class_exists('Swoole\Coroutine') && \Swoole\Coroutine::getCid() > 0) {
            \Swoole\Coroutine::getContext()[$key] = $value;
        } else {
            self::$container[$key] = $value;
        }
    }

    /**
     * 获取上下文数据
     * @param string $key 键名
     * @param mixed $default 默认值
     * @return mixed
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        if (class_exists('Swoole\Coroutine') && \Swoole\Coroutine::getCid() > 0) {
            return \Swoole\Coroutine::getContext()[$key] ?? $default;
        }

        return self::$container[$key] ?? $default;
    }

    /**
     * 删除上下文数据
     * @param string $key 键名
     * @return void
     */
    public static function delete(string $key): void
    {
        if (class_exists('Swoole\Coroutine') && \Swoole\Coroutine::getCid() > 0) {
            unset(\Swoole\Coroutine::getContext()[$key]);
        } else {
            unset(self::$container[$key]);
        }
    }

    /**
     * 获取所有上下文数据
     * @return array
     */
    public static function getAll(): array
    {
        if (class_exists('Swoole\Coroutine') && \Swoole\Coroutine::getCid() > 0) {
            return (array) \Swoole\Coroutine::getContext();
        }

        return self::$container;
    }

    /**
     * 清空上下文数据
     * @return void
     */
    public static function clear(): void
    {

    }
}

<?
declare (strict_types = 1);
namespace lim;

use Swoole\Coroutine;

class Context {
	protected static $pool = [];
	static function get(string $key) {
		return self::$pool[Coroutine::getCid()][$key] ?? null;
	}
	static function all(): array {
		return self::$pool[Coroutine::getCid()] ?? [];
	}
	static function set(string $key, $obj): void {
		self::$pool[Coroutine::getCid()][$key] = $obj;
	}
	static function clear(): void {
		unset(self::$pool[Coroutine::getCid()]);
	}
}

<?
declare (strict_types = 1);
namespace lim;

class Request {
	public static function __callStatic($method, $argv) {
		$handle = PHP_SAPI == 'cli'?request\Cli::class : request\Fpm::class;
		return (new $handle)->$method(...$argv);
	}
}
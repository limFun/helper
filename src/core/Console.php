<?
declare (strict_types = 1);
namespace lim;

class Console {
	public static function run($o) {
		array_shift($o);
		$method = array_shift($o);
		switch ($method) {
		case 'dev':return shell_exec('php -S 0.0.0.0:' . env('APP_PORT', 11111) . ' -t ' . ROOT_PATH . '/public');
		case 'fn':
			$fn = array_shift($o);
			run(fn() => $fn(...$o));
			break;
		case 'init':return run(fn() => Db::schema());
		case 'server':return Server::run();
		case 'task':
			$c = array_shift($o) ?? '';
			if (str_contains($c, '.')) {
				[$class, $action] = explode('.', $c);
				$obj = '\\app\\task\\' . ucfirst($class);
				$obj::$action(...$o);
			} elseif (str_contains($c, '-run')) {

			} else {
				return loger("\n\t运行任务： -run\n\t测试任务：类.方法 参数1 参数2");
			}
			break;
		case 'make': //新增文件
			$type = array_shift($o);
			$name = array_shift($o);
			$header = "<?\ndeclare (strict_types = 1);";
			$content = match ($type) {
				'model' => $header . "\nnamespace app\model;\n\nclass {$name} extends \\lim\\Model {\n\n}",
				'route' => $header . "\nnamespace app\\route;\n\nclass {$name} extends \\lim\\Router {\n\n\tprotected \$call=[\n\n\t];\n}",
				'task' => $header . "\nnamespace app\\task;\n\nclass {$name} {\n\n}",
				'service' => $header . "\nnamespace app\\service;\n\nclass {$name} {\n\n}",
				'config' => $header . "\nreturn[\n\n];",
				default => null,
			};
			$path = ROOT_PATH . match ($type) {
				'model' => "app/model/{$name}.php",
				'route' => "app/route/{$name}.php",
				'task' => "app/task/{$name}.php",
				'service' => "app/service/{$name}.php",
				'config' => "config/{$name}.php",
				default => null,
			};
			//文件不存在啥也不做
			if ($content === null) {return;}
			//目录不存在就先创建目录
			$dir = dirname($path);
			if (!is_dir($dir)) {
				mkdir($dir, 777, true);
				loger("新增 目录 {$dir} 成功");
			}
			//文件存在就跳过创建
			if (is_file($path)) {return loger($path . ' 已存在!');}
			file_put_contents($path, $content);
			loger("新增 {$type} {$name} 成功");
			break;
		default:loger(['method' => $method, 'option' => $o]);
			break;
		}
	}
}
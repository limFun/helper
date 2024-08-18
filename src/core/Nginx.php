<?
declare (strict_types = 1);
namespace lim;
/**
 *
 */
use think\facade\Db;

class Nginx {
	public static function run() {
		header('Access-Control-Allow-Origin:*');
		header('Access-Control-Allow-Methods:*');
		header('Access-Control-Allow-Headers:*');
		header('Content-Type:application/json;charset=utf-8');

		// Helper::import('config');

		$uri = substr($_SERVER['REQUEST_URI'], 1);

		if (!in_array($_SERVER['REQUEST_METHOD'], ['POST', 'GET'])) {
			return;
		}

		if (str_contains($uri, '?')) {
			[$uri, $get] = explode('?', $uri);
		}

		$res = explode('/', $uri);

		if (count($res) != 2) {
			die('fuck');
		}

		[$class, $method] = $res;

		$obj = '\\app\\route\\' . $class;

		if (($_SERVER['CONTENT_TYPE'] ?? null) === 'application/json') {
			$data = json_decode(file_get_contents('php://input'), true);
		} else {
			$data = array_merge($_GET, $_POST);
		}

		try {
			Db::setConfig(config('db'));
			$result = $obj::init()->__before()->register($method, $data);

			json($result);
		} catch (\Exception $e) {
			apiErr($e->getMessage());
		}

	}
}

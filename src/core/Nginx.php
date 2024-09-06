<?
declare (strict_types = 1);
namespace lim;
/**
 *
 */

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

		try {

			if (str_contains($uri, '?')) {
				[$uri, $get] = explode('?', $uri);
			}

			$res = explode('/', $uri);

			if (count($res) != 2) {
				apiErr('路由错误');
			}

			[$class, $method] = $res;

			$obj = '\\app\\route\\' . $class;

			if (($_SERVER['CONTENT_TYPE'] ?? null) === 'application/json') {
				$data = json_decode(file_get_contents('php://input'), true);
			} else {
				$data = array_merge($_GET, $_POST);
			}

			$result = $obj::init()->__before()->register($method, $data);
			echo json_encode(['code' => 200, 'message' => 'success', 'result' => $result]);

		} catch (\Exception $e) {
			echo json_encode(['code' => $e->getCode(), 'message' => $e->getMessage()]);
		}
	}
}

<?
declare (strict_types = 1);
namespace lim\request;

class Cli {

	function __construct() {

	}
	public function __call($method, $argv) {
		switch (strtolower($method)) {
		case 'get':return curr('request')->get;
		case 'post':return curr('request')->post;
		case 'all':return array_merge(curr('request')->get ?? [], (curr('request')->header['content-type'] ?? null) === 'application/json' ? json_decode(curr('request')->getContent(), true) : curr('request')->post ?? []);
		case 'path':return substr(curr('request')->server['path_info'], 1);
		case 'file':break;
		case 'header':
			$key = $argv[0] ?? null;
			$value = $argv[1] ?? null;
			return $key ? curr('request')->header[$key] ?? $value : curr('request')->header;
		default:break;
		}
	}
}

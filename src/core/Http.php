<?
declare (strict_types = 1);
namespace lim;

use Exception;

class Http {

	private $url = '';

	private $option = null;

	private $header = null;

	private $cookie = null;

	private $data = [];

	public static function url($url = '') {
		$h = new self;

		$h->url = $url;

		return $h;
	}

	public function data($data = []) {
		$this->data = $data;
		return $this;
	}

	public function option($option = null) {
		$this->option = $option;
		return $this;
	}

	public function header($header = null) {
		$this->header = $header;
		return $this;
	}

	public function cookie($cookie = null) {
		$this->cookie = $cookie;
		return $this;
	}

	public function get($value = '') {
		$res = $this->request($this->url, 'GET', $this->data, $this->option, $this->header, $this->cookie);
		return $res;
	}

	public function post($data = []) {
		$res = $this->request($this->url, 'POST', array_merge($this->data, $data), $this->option, $this->header, $this->cookie);
		return $res;
	}

	public function method($action) {
		$res = $this->request($this->url, strtoupper($action), $this->data, $this->option, $this->header, $this->cookie);
		return $res;
	}

	public function request($url, $method, $data = null, $options = null, $headers = null, $cookies = null) {
		$ch = curl_init($url);
		if (empty($ch)) {
			throw new Exception('failed to curl_init');
		}
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
		$responseHeaders = $responseCookies = [];
		curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($ch, $header) use (&$responseHeaders, &$responseCookies) {
			$len = strlen($header);
			$header = explode(':', $header, 2);
			if (count($header) < 2) {
				return $len;
			}
			$headerKey = strtolower(trim($header[0]));
			if ($headerKey == 'set-cookie') {
				[$k, $v] = explode('=', $header[1]);
				$responseCookies[$k] = $v;
			} else {
				$responseHeaders[$headerKey][] = trim($header[1]);
			}
			return $len;
		});
		if ($data) {
			curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		}
		if ($headers) {
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		}
		if ($cookies) {
			$cookie_str = '';
			foreach ($cookies as $k => $v) {
				$cookie_str .= "{$k}={$v}; ";
			}
			curl_setopt($ch, CURLOPT_COOKIE, $cookie_str);
		}
		if (isset($options['timeout'])) {
			if (is_float($options['timeout'])) {
				curl_setopt($ch, CURLOPT_TIMEOUT_MS, intval($options['timeout'] * 1000));
				curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, intval($options['timeout'] * 1000));
			} else {
				curl_setopt($ch, CURLOPT_TIMEOUT, intval($options['timeout']));
				curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, intval($options['timeout']));
			}
		}
		if (isset($options['connect_timeout'])) {
			if (is_float($options['connect_timeout'])) {
				curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, intval($options['connect_timeout'] * 1000));
			} else {
				curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, intval($options['connect_timeout']));
			}
		}
		$body = curl_exec($ch);
		if ($body !== false) {
			return new HttpRes($body, curl_getinfo($ch, CURLINFO_RESPONSE_CODE), $responseHeaders, $responseCookies);
		}
		throw new Exception(curl_error($ch), curl_errno($ch));
	}
}

class HttpRes {
	private array $headers;

	private array $cookies;

	public function __construct(private string $body, private int $statusCode, ?array $headers, ?array $cookies) {
		$this->headers = $headers ?? [];
		$this->cookies = $cookies ?? [];
	}

	public function getBody(): string {
		return $this->body;
	}

	public function getStatusCode(): int {
		return $this->statusCode;
	}

	public function getHeaders(): array {
		return $this->headers;
	}

	public function getCookies(): array {
		return $this->cookies;
	}

	public function getJson() {
		return json_decode($this->body, true);
	}
}
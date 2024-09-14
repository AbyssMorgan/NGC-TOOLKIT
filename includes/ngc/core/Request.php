<?php

/* NGC-TOOLKIT v2.3.0 */

declare(strict_types=1);

namespace NGC\Core;

class Request {

	private bool $json;
	private bool $cookies;
	private array $header;
	private array $options;
	private string $cookie_file;

	public function __construct(bool $json = true, array $header = [], ?array $options = null){
		$this->json = $json;
		$this->cookies = false;
		$this->cookie_file = 'NGC-TOOLKIT-COOKIE.txt';
		$this->header = $header;
		if(is_null($options)){
			$this->options = [
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_ENCODING => '',
				CURLOPT_MAXREDIRS => 10,
				CURLOPT_TIMEOUT => 120,
				CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
				CURLOPT_SSL_VERIFYHOST => false,
				CURLOPT_SSL_VERIFYPEER => false,
				CURLOPT_USERAGENT => 'NGC-TOOLKIT',
			];
		} else {
			$this->options = $options;
		}
	}

	public function toggle_cookie(bool $toggle, string $file = 'NGC-TOOLKIT-COOKIE.txt') : void {
		$this->cookies = $toggle;
		$this->cookie_file = $file;
	}

	public function get_cookie_file() : string {
		return $this->cookie_file;
	}

	public function set_header(array $header) : void {
		$this->header = $header;
	}

	public function get_header() : array {
		return $this->header;
	}

	public function set_options(array $options) : void {
		$this->options = $options;
	}

	public function set_option(int $option, mixed $value) : void {
		$this->options[$option] = $value;
	}

	public function get(string $url, array $data = [], bool $follow = false) : array {
		return $this->request($url, 'GET', $data, $follow);
	}

	public function head(string $url, array $data = [], bool $follow = false) : array {
		return $this->request($url, 'HEAD', $data, $follow);
	}

	public function post(string $url, array $data = [], bool $follow = false) : array {
		return $this->request($url, 'POST', $data, $follow);
	}

	public function put(string $url, array $data = [], bool $follow = false) : array {
		return $this->request($url, 'PUT', $data, $follow);
	}

	public function delete(string $url, array $data = [], bool $follow = false) : array {
		return $this->request($url, 'DELETE', $data, $follow);
	}

	public function connect(string $url, array $data = [], bool $follow = false) : array {
		return $this->request($url, 'CONNECT', $data, $follow);
	}

	public function options(string $url, array $data = [], bool $follow = false) : array {
		return $this->request($url, 'OPTIONS', $data, $follow);
	}

	public function trace(string $url, array $data = [], bool $follow = false) : array {
		return $this->request($url, 'TRACE', $data, $follow);
	}

	public function patch(string $url, array $data = [], bool $follow = false) : array {
		return $this->request($url, 'PATCH', $data, $follow);
	}

	private function request(string $url, string $method, array $data, bool $follow) : array {
		$options = $this->options;
		$options[CURLOPT_FOLLOWLOCATION] = $follow;
		$options[CURLOPT_CUSTOMREQUEST] = $method;
		if(!empty($data)){
			if($method == 'POST'){
				$options[CURLOPT_POSTFIELDS] = $this->json ? json_encode($data) : urldecode(http_build_query($data));
			} else {
				$params = "?".urldecode(http_build_query($data));
			}
		}
		$curl = curl_init($url.($params ?? ''));
		$options[CURLOPT_HTTPHEADER] = $this->header;
		if($this->json){
			array_push($options[CURLOPT_HTTPHEADER], 'Content-Type: application/json');
		}
		if($this->cookies){
			$options[CURLOPT_COOKIEFILE] = $this->cookie_file;
			$options[CURLOPT_COOKIEJAR] = $this->cookie_file;
		}
		curl_setopt_array($curl, $options);
		$response = curl_exec($curl);
		curl_close($curl);
		if(!$response){
			return [
				'code' => curl_getinfo($curl, CURLINFO_HTTP_CODE),
				'data' => []
			];
		}
		return [
			'code' => curl_getinfo($curl, CURLINFO_HTTP_CODE),
			'data' => $this->json ? json_decode($response, true) : $response
		];
	}

}

?>
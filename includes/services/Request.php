<?php

declare(strict_types=1);

namespace App\Services;

class Request {

	private bool $json;
	private bool $cookies;
	private array $header;
	private array $options;

	public int $version = 10200;

	public function __construct(bool $json = true, bool $cookies = false, array $header = []){
		$this->json = $json;
		$this->cookies = $cookies;
		$this->header = $header;
		$this->options = [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => '',
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 120,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_SSL_VERIFYHOST => false,
			CURLOPT_SSL_VERIFYPEER => false,
		];
	}

	public function setHeader(array $header) : void {
		$this->header = $header;
	}

	public function setOptions(array $options) : void {
		$this->options = $options;
	}

	public function setOption(int $option, mixed $value) : void {
		$this->options[$option] = $value;
	}

	public function get(string $url, array $data = [], bool $follow = false) : array {
		return $this->request($url, 'GET', $data, $follow);
	}

	public function post(string $url, array $data = [], bool $follow = false) : array {
		return $this->request($url, 'POST', $data, $follow);
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
		if($this->json) array_push($options[CURLOPT_HTTPHEADER], 'Content-Type: application/json');
		if($this->cookies){
			$options[CURLOPT_COOKIEFILE] = 'AVE-COOKIE.txt';
			$options[CURLOPT_COOKIEJAR] = 'AVE-COOKIE.txt';
		}
		curl_setopt_array($curl, $options);
		$response = curl_exec($curl);
		curl_close($curl);
		if(!$response) return ['code' => curl_getinfo($curl, CURLINFO_HTTP_CODE), 'data' => []];
		return [
			'code' => curl_getinfo($curl, CURLINFO_HTTP_CODE),
			'data' => $this->json ? json_decode($response, true) : $response
		];
	}

}

?>

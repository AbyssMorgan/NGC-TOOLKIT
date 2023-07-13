<?php

declare(strict_types=1);

namespace App\Services;

class Request {

	private bool $json;
	private bool $cookies;

	public int $version = 10100;

	public function __construct(bool $json = true, bool $cookies = false){
		$this->json = $json;
		$this->cookies = $cookies;
	}

	public function get(string $url, array $data = [], bool $follow = false) : array {
		return $this->request($url, 'GET', $data, $follow);
	}

	public function post(string $url, array $data = [], bool $follow = false) : array {
		return $this->request($url, 'POST', $data, $follow);
	}

	private function request(string $url, string $method, array $data, bool $follow) : array {
		$options = [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => '',
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 120,
			CURLOPT_FOLLOWLOCATION => $follow,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => $method,
			CURLOPT_SSL_VERIFYHOST => false,
			CURLOPT_SSL_VERIFYPEER => false,
		];
		if(!empty($data)){
			if($method == 'POST'){
				$options[CURLOPT_POSTFIELDS] = $this->json ? json_encode($data) : urldecode(http_build_query($data));
			} else {
				$params = "?".urldecode(http_build_query($data));
			}
		}
		$curl = curl_init($url.($params ?? ''));
		if($this->json) $options[CURLOPT_HTTPHEADER] = ['Content-Type: application/json'];
		if($this->cookies){
			$options[CURLOPT_COOKIEFILE] = 'AVE-COOKIE.txt';
			$options[CURLOPT_COOKIEJAR] = 'AVE-COOKIE.txt';
		}
		curl_setopt_array($curl, $options);
		$response = curl_exec($curl);
		if(!$response) return ['code' => curl_getinfo($curl, CURLINFO_HTTP_CODE), 'data' => ['error' => curl_error($curl)]];
		curl_close($curl);
		return [
			'code' => curl_getinfo($curl, CURLINFO_HTTP_CODE),
			'data' => $this->json ? json_decode($response, true) : $response
		];
	}

}

?>

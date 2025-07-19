<?php

/**
 * NGC-TOOLKIT v2.7.1 – Component
 *
 * © 2025 Abyss Morgan
 *
 * This component is free to use in both non-commercial and commercial projects.
 * No attribution required, but appreciated.
 */

declare(strict_types=1);

namespace NGC\Core;

/**
 * The Request class provides a convenient way to make HTTP requests using cURL.
 * It supports various HTTP methods, JSON encoding/decoding, cookie handling,
 * and custom headers and cURL options.
 */
class Request {

	/**
	 * Flag to determine if request/response body should be treated as JSON.
	 * @var bool
	 */
	private bool $json;

	/**
	 * Flag to determine if cookies should be used.
	 * @var bool
	 */
	private bool $cookies;

	/**
	 * An array of custom HTTP headers to send with requests.
	 * @var array
	 */
	private array $header;

	/**
	 * An array of cURL options.
	 * @var array
	 */
	private array $options;

	/**
	 * The file path for storing and reading cookies.
	 * @var string
	 */
	private string $cookie_file;

	/**
	 * The path to a custom CA certificate file.
	 * @var string|null
	 */
	private ?string $cacert;

	/**
	 * The default filename for the cookie file.
	 * @const string
	 */
	public const DEFAULT_COOKIE_FILE = 'NGC-TOOLKIT-COOKIE.txt';

	/**
	 * The default User-Agent string for requests.
	 * @const string
	 */
	public const DEFAULT_USER_AGENT = 'NGC-TOOLKIT';

	/**
	 * Constructor for the Request class.
	 *
	 * @param bool $json Whether to treat request/response body as JSON. Defaults to true.
	 * @param array $header An associative array of custom HTTP headers. Defaults to an empty array.
	 * @param array $options An associative array of cURL options. Defaults to an empty array.
	 * @param int $http_version The HTTP protocol version to use. Defaults to CURL_HTTP_VERSION_1_1.
	 */
	public function __construct(bool $json = true, array $header = [], array $options = [], int $http_version = CURL_HTTP_VERSION_1_1){
		$this->json = $json;
		$this->cookies = false;
		$this->cookie_file = self::DEFAULT_COOKIE_FILE;
		$this->header = $header;
		$this->cacert = null;
		$this->options = [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => '',
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 60,
			CURLOPT_HTTP_VERSION => $http_version,
			CURLOPT_USERAGENT => self::DEFAULT_USER_AGENT,
		];
		foreach($options as $option_id => $option_value){
			$this->options[$option_id] = $option_value;
		}
	}

	/**
	 * Sets the path to a CA certificate file for SSL verification.
	 *
	 * @param string $path The path to the CA certificate file.
	 * @return bool True if the file exists and is a regular file, false otherwise.
	 */
	public function set_cacert(string $path) : bool {
		if(!file_exists($path) || !is_file($path)) return false;
		$this->cacert = $path;
		return true;
	}

	/**
	 * Attempts to negotiate the best available HTTP version for a given URL.
	 * It tests for HTTP/3, then HTTP/2, and falls back to HTTP/1.1 if neither is supported.
	 *
	 * @param string $url The URL to test the HTTP version against.
	 * @return int The negotiated cURL HTTP version constant (CURL_HTTP_VERSION_3, CURL_HTTP_VERSION_2, or CURL_HTTP_VERSION_1_1).
	 */
	public function negotiate_http_version(string $url) : int {
		if(defined('CURL_HTTP_VERSION_3')){
			if($this->test_http_version($url, CURL_HTTP_VERSION_3)) return CURL_HTTP_VERSION_3;
		}
		if($this->test_http_version($url, CURL_HTTP_VERSION_2)) return CURL_HTTP_VERSION_2;
		return CURL_HTTP_VERSION_1_1;
	}

	/**
	 * Sets the HTTP protocol version for requests.
	 *
	 * @param int $version The cURL HTTP version constant (e.g., CURL_HTTP_VERSION_1_1, CURL_HTTP_VERSION_2_0).
	 * @return bool True if the version is valid and set, false otherwise.
	 */
	public function set_http_version(int $version) : bool {
		$versions = [CURL_HTTP_VERSION_1_0, CURL_HTTP_VERSION_1_1, CURL_HTTP_VERSION_2];
		// if(defined('CURL_HTTP_VERSION_3')){
		// 	array_push($versions, CURL_HTTP_VERSION_3, CURL_HTTP_VERSION_3ONLY);
		// }
		if(in_array($version, $versions)){
			$this->set_option(CURLOPT_HTTP_VERSION, $version);
			return true;
		}
		return false;
	}

	/**
	 * Toggles JSON encoding/decoding for request/response bodies.
	 *
	 * @param bool $toggle True to enable JSON, false to disable.
	 */
	public function toggle_json(bool $toggle) : void {
		$this->json = $toggle;
	}

	/**
	 * Toggles cookie handling and optionally sets a custom cookie file.
	 *
	 * @param bool $toggle True to enable cookies, false to disable.
	 * @param string|null $file Optional. The path to the cookie file. If null, the default cookie file will be used.
	 */
	public function toggle_cookie(bool $toggle, ?string $file = null) : void {
		$this->cookies = $toggle;
		if(!is_null($file)){
			$this->cookie_file = $file;
		} else {
			$this->cookie_file = self::DEFAULT_COOKIE_FILE;
		}
	}

	/**
	 * Returns the current cookie file path.
	 *
	 * @return string The path to the cookie file.
	 */
	public function get_cookie_file() : string {
		return $this->cookie_file;
	}

	/**
	 * Sets the custom HTTP headers to be sent with requests.
	 *
	 * @param array $header An associative array of custom HTTP headers.
	 */
	public function set_header(array $header) : void {
		$this->header = $header;
	}

	/**
	 * Returns the current custom HTTP headers.
	 *
	 * @return array An associative array of custom HTTP headers.
	 */
	public function get_header() : array {
		return $this->header;
	}

	/**
	 * Returns the current cURL options.
	 *
	 * @return array An associative array of cURL options.
	 */
	public function get_options() : array {
		return $this->options;
	}

	/**
	 * Sets multiple cURL options at once.
	 *
	 * @param array $options An associative array of cURL options.
	 */
	public function set_options(array $options) : void {
		$this->options = $options;
	}

	/**
	 * Sets a single cURL option.
	 *
	 * @param int $option The cURL option constant (e.g., CURLOPT_TIMEOUT).
	 * @param mixed $value The value for the cURL option.
	 */
	public function set_option(int $option, mixed $value) : void {
		$this->options[$option] = $value;
	}

	/**
	 * Makes a GET request to the specified URL.
	 *
	 * @param string $url The URL for the request.
	 * @param array $data Optional. An associative array of data to send as query parameters.
	 * @param bool $follow Optional. Whether to follow HTTP redirects. Defaults to false.
	 * @return array An associative array containing the HTTP status code and the response data.
	 */
	public function get(string $url, array $data = [], bool $follow = false) : array {
		return $this->request($url, 'GET', $data, $follow);
	}

	/**
	 * Makes a HEAD request to the specified URL.
	 *
	 * @param string $url The URL for the request.
	 * @param array $data Optional. An associative array of data to send as query parameters.
	 * @param bool $follow Optional. Whether to follow HTTP redirects. Defaults to false.
	 * @return array An associative array containing the HTTP status code and the response data.
	 */
	public function head(string $url, array $data = [], bool $follow = false) : array {
		return $this->request($url, 'HEAD', $data, $follow);
	}

	/**
	 * Makes a POST request to the specified URL.
	 *
	 * @param string $url The URL for the request.
	 * @param array $data Optional. An associative array of data to send in the request body.
	 * @param bool $follow Optional. Whether to follow HTTP redirects. Defaults to false.
	 * @return array An associative array containing the HTTP status code and the response data.
	 */
	public function post(string $url, array $data = [], bool $follow = false) : array {
		return $this->request($url, 'POST', $data, $follow);
	}

	/**
	 * Makes a PUT request to the specified URL.
	 *
	 * @param string $url The URL for the request.
	 * @param array $data Optional. An associative array of data to send in the request body.
	 * @param bool $follow Optional. Whether to follow HTTP redirects. Defaults to false.
	 * @return array An associative array containing the HTTP status code and the response data.
	 */
	public function put(string $url, array $data = [], bool $follow = false) : array {
		return $this->request($url, 'PUT', $data, $follow);
	}

	/**
	 * Makes a DELETE request to the specified URL.
	 *
	 * @param string $url The URL for the request.
	 * @param array $data Optional. An associative array of data to send in the request body.
	 * @param bool $follow Optional. Whether to follow HTTP redirects. Defaults to false.
	 * @return array An associative array containing the HTTP status code and the response data.
	 */
	public function delete(string $url, array $data = [], bool $follow = false) : array {
		return $this->request($url, 'DELETE', $data, $follow);
	}

	/**
	 * Makes a CONNECT request to the specified URL.
	 *
	 * @param string $url The URL for the request.
	 * @param array $data Optional. An associative array of data to send.
	 * @param bool $follow Optional. Whether to follow HTTP redirects. Defaults to false.
	 * @return array An associative array containing the HTTP status code and the response data.
	 */
	public function connect(string $url, array $data = [], bool $follow = false) : array {
		return $this->request($url, 'CONNECT', $data, $follow);
	}

	/**
	 * Makes an OPTIONS request to the specified URL.
	 *
	 * @param string $url The URL for the request.
	 * @param array $data Optional. An associative array of data to send.
	 * @param bool $follow Optional. Whether to follow HTTP redirects. Defaults to false.
	 * @return array An associative array containing the HTTP status code and the response data.
	 */
	public function options(string $url, array $data = [], bool $follow = false) : array {
		return $this->request($url, 'OPTIONS', $data, $follow);
	}

	/**
	 * Makes a TRACE request to the specified URL.
	 *
	 * @param string $url The URL for the request.
	 * @param array $data Optional. An associative array of data to send.
	 * @param bool $follow Optional. Whether to follow HTTP redirects. Defaults to false.
	 * @return array An associative array containing the HTTP status code and the response data.
	 */
	public function trace(string $url, array $data = [], bool $follow = false) : array {
		return $this->request($url, 'TRACE', $data, $follow);
	}

	/**
	 * Makes a PATCH request to the specified URL.
	 *
	 * @param string $url The URL for the request.
	 * @param array $data Optional. An associative array of data to send in the request body.
	 * @param bool $follow Optional. Whether to follow HTTP redirects. Defaults to false.
	 * @return array An associative array containing the HTTP status code and the response data.
	 */
	public function patch(string $url, array $data = [], bool $follow = false) : array {
		return $this->request($url, 'PATCH', $data, $follow);
	}

	/**
	 * Internal method to perform the actual cURL request.
	 *
	 * @param string $url The URL for the request.
	 * @param string $method The HTTP method (e.g., 'GET', 'POST').
	 * @param array $data An associative array of data to send with the request.
	 * @param bool $follow Whether to follow HTTP redirects.
	 * @return array An associative array containing the HTTP status code and the response data.
	 */
	private function request(string $url, string $method, array $data, bool $follow) : array {
		$options = $this->options;
		$options[CURLOPT_FOLLOWLOCATION] = $follow;
		$options[CURLOPT_CUSTOMREQUEST] = $method;
		$params = '';
		if(!empty($data)){
			if(in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'])){
				$options[CURLOPT_POSTFIELDS] = $this->json ? json_encode($data) : urldecode(http_build_query($data));
			} elseif($method === 'GET'){
				$params = '?'.urldecode(http_build_query($data));
			}
		}
		$curl = curl_init("{$url}{$params}");
		$options[CURLOPT_HTTPHEADER] = $this->header;
		if($this->json){
			array_push($options[CURLOPT_HTTPHEADER], 'Content-Type: application/json');
			if(!array_filter($this->header, fn(string $h) : bool => stripos($h, 'Accept:') === 0)){
				array_push($options[CURLOPT_HTTPHEADER], 'Accept: application/json');
			}
		} else {
			if(in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'])){
				array_push($options[CURLOPT_HTTPHEADER], 'Content-Type: application/x-www-form-urlencoded');
				array_push($options[CURLOPT_HTTPHEADER], 'Content-Length: '.strlen($options[CURLOPT_POSTFIELDS]));
			}
		}
		if($this->cookies){
			$options[CURLOPT_COOKIEFILE] = $this->cookie_file;
			$options[CURLOPT_COOKIEJAR] = $this->cookie_file;
		}
		if(str_starts_with($url, 'https://')){
			$options[CURLOPT_SSL_VERIFYHOST] = 2;
			$options[CURLOPT_SSL_VERIFYPEER] = true;
			if(!is_null($this->cacert) && file_exists($this->cacert)){
				$options[CURLOPT_CAINFO] = $this->cacert;
			}
		}
		curl_setopt_array($curl, $options);
		$response = curl_exec($curl);
		$info = curl_getinfo($curl);
		$error = curl_error($curl);
		curl_close($curl);
		if($response === false){
			return [
				'code' => $info['http_code'] ?? 0,
				'data' => [
					'message' => $error ?: 'Unknown cURL error',
				]
			];
		}
		return [
			'code' => $info['http_code'] ?? 0,
			'data' => $this->json ? json_decode($response, true) : $response
		];
	}

	/**
	 * Internal method to test if a specific HTTP version is supported by a given URL.
	 *
	 * @param string $url The URL to test.
	 * @param int $version The cURL HTTP version constant to test for.
	 * @return bool True if the HTTP version is supported and the request returns a non-zero status code, false otherwise.
	 */
	private function test_http_version(string $url, int $version) : bool {
		$options = $this->get_options();
		$header = $this->get_header();
		$this->set_options([
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_NOBODY => true,
			CURLOPT_TIMEOUT => 5,
			CURLOPT_HTTP_VERSION => $version,
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_USERAGENT => self::DEFAULT_USER_AGENT,
		]);
		$this->set_header([]);
		$response = $this->get($url, [], true);
		$this->set_options($options);
		$this->set_header($header);
		return $response['code'] != 0;
	}

}
?>
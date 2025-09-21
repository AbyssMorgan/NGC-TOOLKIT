<?php

declare(strict_types=1);

/*
 * This file is part of the `nicolab/php-ftp-client` package.
 *
 * (c) Nicolas Tallefourtane <dev@nicolab.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @copyright Nicolas Tallefourtane http://nicolab.net
 */
namespace FtpClient;

use Countable;
use Exception;

/**
 * The FTP and SSL-FTP client for PHP.
 *
 * @method bool alloc(int $filesize, string &$result = null) Allocates space for a file to be uploaded
 * @method bool append(string $remote_file, string $local_file, int $mode = FTP_BINARY) Append the contents of a file to another file on the FTP server
 * @method bool cdup() Changes to the parent directory
 * @method bool chdir(string $directory) Changes the current directory on a FTP server
 * @method int chmod(int $mode, string $filename) Set permissions on a file via FTP
 * @method bool delete(string $path) Deletes a file on the FTP server
 * @method bool exec(string $command) Requests execution of a command on the FTP server
 * @method bool fget($handle, string $remote_file, int $mode, int $resumepos = 0) Downloads a file from the FTP server and saves to an open file
 * @method bool fput(string $remote_file, $handle, int $mode, int $startpos = 0) Uploads from an open file to the FTP server
 * @method mixed get_option(int $option) Retrieves various runtime behaviours of the current FTP stream
 * @method bool get(string $local_file, string $remote_file, int $mode, int $resumepos = 0) Downloads a file from the FTP server
 * @method int mdtm(string $remote_file) Returns the last modified time of the given file
 * @method array mlsd(string $remote_dir) Returns a list of files in the given directory
 * @method int nb_continue() Continues retrieving/sending a file (non-blocking)
 * @method int nb_fget($handle, string $remote_file, int $mode, int $resumepos = 0) Retrieves a file from the FTP server and writes it to an open file (non-blocking)
 * @method int nb_fput(string $remote_file, $handle, int $mode, int $startpos = 0) Stores a file from an open file to the FTP server (non-blocking)
 * @method int nb_get(string $local_file, string $remote_file, int $mode, int $resumepos = 0) Retrieves a file from the FTP server and writes it to a local file (non-blocking)
 * @method int nb_put(string $remote_file, string $local_file, int $mode, int $startpos = 0) Stores a file on the FTP server (non-blocking)
 * @method bool pasv(bool $pasv) Turns passive mode on or off
 * @method bool put(string $remote_file, string $local_file, int $mode, int $startpos = 0) Uploads a file to the FTP server
 * @method string pwd() Returns the current directory name
 * @method bool quit() Closes an FTP connection
 * @method array raw(string $command) Sends an arbitrary command to an FTP server
 * @method bool rename(string $oldname, string $newname) Renames a file or a directory on the FTP server
 * @method bool set_option(int $option, mixed $value) Set miscellaneous runtime FTP options
 * @method bool site(string $command) Sends a SITE command to the server
 * @method int size(string $remote_file) Returns the size of the given file
 * @method string systype() Returns the system type identifier of the remote FTP server
 *
 * @author Nicolas Tallefourtane <dev@nicolab.net>
 */
class FtpClient implements Countable {

	/**
	 * The connection with the server.
	 *
	 * @var resource
	 */
	protected mixed $conn;

	/**
	 * PHP FTP functions wrapper.
	 *
	 * @var FtpWrapper
	 */
	private FtpWrapper $ftp;

	/**
	 * Constructor.
	 *
	 * @param resource|null $connection
	 * @throws FtpException If FTP extension is not loaded.
	 */
	public function __construct(mixed $connection = null){
		if(!extension_loaded('ftp')){
			throw new FtpException('FTP extension is not loaded!');
		}
		if($connection){
			$this->conn = $connection;
		}
		$this->set_wrapper(new FtpWrapper($this->conn));
	}

	/**
	 * Close the connection when the object is destroyed.
	 */
	public function __destruct(){
		if($this->conn){
			$this->ftp->close();
		}
	}

	/**
	 * Call an internal method or a FTP method handled by the wrapper.
	 *
	 * Wrap the FTP PHP functions to call as method of FtpClient object.
	 * The connection is automaticaly passed to the FTP PHP functions.
	 *
	 * @param string $method
	 * @param array $arguments
	 * @return mixed
	 * @throws FtpException When the function is not valid
	 */
	public function __call(string $method, array $arguments) : mixed {
		return $this->ftp->__call($method, $arguments);
	}

	/**
	 * Get the help information of the remote FTP server.
	 *
	 * @return array
	 */
	public function help() : array {
		return $this->ftp->raw('help');
	}

	/**
	 * Open a FTP connection.
	 *
	 * @param string $host
	 * @param bool $ssl
	 * @param int $port
	 * @param int $timeout
	 *
	 * @return FtpClient
	 * @throws FtpException If unable to connect
	 */
	public function connect(string $host, bool $ssl = false, int $port = 21, int $timeout = 90) : static {
		if($ssl){
			$this->conn = $this->ftp->ssl_connect($host, $port, $timeout);
		} else {
			$this->conn = $this->ftp->connect($host, $port, $timeout);
		}
		if(!$this->conn){
			throw new FtpException('Unable to connect');
		}
		return $this;
	}

	/**
	 * Closes the current FTP connection.
	 *
	 * @return bool
	 */
	public function close() : void {
		if($this->conn){
			$this->ftp->close();
			$this->conn = null;
		}
	}

	/**
	 * Get the connection with the server.
	 *
	 * @return resource
	 */
	public function get_connection() : mixed {
		return $this->conn;
	}

	/**
	 * Get the wrapper.
	 *
	 * @return FtpWrapper
	 */
	public function get_wrapper() : FtpWrapper {
		return $this->ftp;
	}

	/**
	 * Logs in to an FTP connection.
	 *
	 * @param string $username
	 * @param string $password
	 *
	 * @return FtpClient
	 * @throws FtpException If the login is incorrect
	 */
	public function login(string $username = 'anonymous', string $password = '') : static {
		$result = $this->ftp->login($username, $password);
		if($result === false){
			throw new FtpException('Login incorrect');
		}
		return $this;
	}

	/**
	 * Returns the last modified time of the given file.
	 * Return -1 on error
	 *
	 * @param string $remote_file
	 * @param string|null $format
	 *
	 * @return int
	 */
	public function modified_time(string $remote_file, ?string $format = null) : string|int {
		$time = $this->ftp->mdtm($remote_file);
		if($time !== -1 && $format !== null){
			return date($format, $time);
		}
		return $time;
	}

	/**
	 * Changes to the parent directory.
	 *
	 * @throws FtpException
	 * @return FtpClient
	 */
	public function up() : static {
		$result = $this->ftp->cdup();
		if($result === false){
			throw new FtpException('Unable to get parent folder');
		}
		return $this;
	}

	/**
	 * Returns a list of files in the given directory.
	 *
	 * @param string $directory The directory, by default is "." the current directory
	 * @param bool $recursive
	 * @param callable $filter	A callable to filter the result, by default is asort() PHP function.
	 *							The result is passed in array argument,
	 *							must take the argument by reference !
	 *							The callable should proceed with the reference array
	 *							because is the behavior of several PHP sorting
	 *							functions (by reference ensure directly the compatibility
	 *							with all PHP sorting functions).
	 *
	 * @return array
	 * @throws FtpException If unable to list the directory
	 */
	public function nlist(string $directory = '.', bool $recursive = false, string $filter = 'sort') : array {
		if(!$this->is_dir($directory)){
			throw new FtpException('"'.$directory.'" is not a directory');
		}
		$files = $this->ftp->nlist($directory);
		if($files === false){
			throw new FtpException('Unable to list directory');
		}
		$result = [];
		$dir_len = strlen($directory);
		if(false !== ($kdot = array_search('.', $files))){
			unset($files[$kdot]);
		}
		if(false !== ($kdot = array_search('..', $files))){
			unset($files[$kdot]);
		}
		if(!$recursive){
			$result = $files;
			$filter($result);
			return $result;
		}
		$flatten = function(array $arr) use (&$flatten) : array {
			$flat = [];
			foreach($arr as $k => $v){
				if(is_array($v)){
					$flat = array_merge($flat, $flatten($v));
				} else {
					$flat[] = $v;
				}
			}
			return $flat;
		};
		foreach($files as $file){
			$file = $directory.'/'.$file;
			if(0 === strpos($file, $directory, $dir_len)){
				$file = substr($file, $dir_len);
			}
			if($this->is_dir($file)){
				$result[] = $file;
				$items = $flatten($this->nlist($file, true, $filter));
				foreach($items as $item){
					$result[] = $item;
				}
			} else {
				$result[] = $file;
			}
		}
		$result = array_unique($result);
		$filter($result);
		return $result;
	}

	/**
	 * Creates a directory.
	 *
	 * @see FtpClient::rmdir()
	 * @see FtpClient::remove()
	 * @see FtpClient::put()
	 * @see FtpClient::put_all()
	 *
	 * @param string $directory The directory
	 * @param bool $recursive
	 * @return string|bool
	 */
	public function mkdir(string $directory, bool $recursive = false) : string|bool {
		if(!$recursive || $this->is_dir($directory)){
			return $this->ftp->mkdir($directory);
		}
		$result = false;
		$pwd = $this->ftp->pwd();
		$parts = explode('/', $directory);
		foreach($parts as $part){
			if($part == '') continue;
			if(!@$this->ftp->chdir($part)){
				$result = $this->ftp->mkdir($part);
				$this->ftp->chdir($part);
			}
		}
		$this->ftp->chdir($pwd);
		return $result;
	}

	/**
	 * Remove a directory.
	 *
	 * @see FtpClient::mkdir()
	 * @see FtpClient::clean_dir()
	 * @see FtpClient::remove()
	 * @see FtpClient::delete()
	 * @param string $directory
	 * @param bool $recursive Forces deletion if the directory is not empty
	 * @return bool
	 * @throws FtpException If unable to list the directory to remove
	 */
	public function rmdir(string $directory, bool $recursive = true) : bool {
		if($recursive){
			$files = $this->nlist($directory, false, 'rsort');
			foreach($files as $file){
				$this->remove($file, true);
			}
		}
		return $this->ftp->rmdir($directory);
	}

	/**
	 * Empty directory.
	 *
	 * @see FtpClient::remove()
	 * @see FtpClient::delete()
	 * @see FtpClient::rmdir()
	 *
	 * @param string $directory
	 * @return bool
	 */
	public function clean_dir(string $directory) : bool {
		if(!$files = $this->nlist($directory)){
			return $this->is_empty($directory);
		}
		foreach($files as $file){
			$this->remove($file, true);
		}
		return $this->is_empty($directory);
	}

	/**
	 * Remove a file or a directory.
	 *
	 * @see FtpClient::rmdir()
	 * @see FtpClient::clean_dir()
	 * @see FtpClient::delete()
	 * @param string $path The path of the file or directory to remove
	 * @param bool $recursive Is effective only if $path is a directory, {@see FtpClient::rmdir()}
	 * @return bool
	 */
	public function remove(string $path, bool $recursive = false) : bool {
		if($path == '.' || $path == '..') return false;
		try {
			if(@$this->ftp->delete($path) || ($this->is_dir($path) && $this->rmdir($path, $recursive))) return true;
			$new_path = preg_replace('/[^A-Za-z0-9\/]/', '', $path);
			if($this->rename($path, $new_path)){
				if(@$this->ftp->delete($new_path) || ($this->is_dir($new_path) && $this->rmdir($new_path, $recursive))){
					return true;
				}
			}
			return false;
		}
		catch(Exception $e){
			return false;
		}
	}

	/**
	 * Check if a directory exist.
	 *
	 * @param string $directory
	 * @return bool
	 * @throws FtpException
	 */
	public function is_dir(string $directory) : bool {
		$pwd = $this->ftp->pwd();
		if($pwd === false){
			throw new FtpException('Unable to resolve the current directory');
		}
		if(@$this->ftp->chdir($directory)){
			$this->ftp->chdir($pwd);
			return true;
		}
		$this->ftp->chdir($pwd);
		return false;
	}

	/**
	 * Check if a directory is empty.
	 *
	 * @param string $directory
	 * @return bool
	 */
	public function is_empty(string $directory) : bool {
		return $this->count_items($directory, null, false) === 0 ? true : false;
	}

	/**
	 * Scan a directory and returns the details of each item.
	 *
	 * @see FtpClient::nlist()
	 * @see FtpClient::rawlist()
	 * @see FtpClient::parse_raw_list()
	 * @see FtpClient::dir_size()
	 * @param string $directory
	 * @param bool $recursive
	 * @return array
	 */
	public function scan_dir(string $directory = '.', bool $recursive = false) : array {
		return $this->parse_raw_list($this->rawlist($directory, $recursive));
	}

	/**
	 * Returns the total size of the given directory in bytes.
	 *
	 * @param string $directory The directory, by default is the current directory.
	 * @param bool $recursive true by default
	 * @return int The size in bytes.
	 */
	public function dir_size(string $directory = '.', bool $recursive = true) : int {
		$items = $this->scan_dir($directory, $recursive);
		$size = 0;
		foreach($items as $item){
			$size += (int) $item['size'];
		}
		return $size;
	}

	/**
	 * Count the items (file, directory, link, unknown).
	 *
	 * @param string $directory The directory, by default is the current directory.
	 * @param string|null $type	 The type of item to count (file, directory, link, unknown)
	 * @param bool $recursive true by default
	 * @return int
	 */
	public function count_items(string $directory = '.', ?string $type = null, bool $recursive = true) : int {
		if(is_null($type)){
			$items = $this->nlist($directory, $recursive);
		} else {
			$items = $this->scan_dir($directory, $recursive);
		}
		$count = 0;
		foreach($items as $item){
			if(null === $type || $item['type'] == $type){
				$count++;
			}
		}
		return $count;
	}

	/**
	 * Count the items (file, directory, link, unknown).
	 * This method call `count_items()` with the default arguments.
	 *
	 * @see count_items
	 * @return int
	 */
	public function count() : int {
		return $this->count_items();
	}

	/**
	 * Downloads a file from the FTP server into a string
	 *
	 * @param string $remote_file
	 * @param int $mode
	 * @param int $resumepos
	 * @return string|null
	 */
	public function get_content(string $remote_file, int $mode = FTP_BINARY, int $resumepos = 0) : string|null {
		$handle = fopen('php://temp', 'r+');
		if(!$handle) return null;
		if($this->ftp->fget($handle, $remote_file, $mode, $resumepos)){
			rewind($handle);
			return stream_get_contents($handle);
		}
		return null;
	}

	/**
	 * Uploads a file to the server from a string.
	 *
	 * @param string $remote_file
	 * @param string $content
	 * @return FtpClient
	 * @throws FtpException When the transfer fails
	 */
	public function put_from_string(string $remote_file, string $content) : static {
		$handle = fopen('php://temp', 'w');
		if(!$handle){
			throw new FtpException('Unable to put the file "'.$remote_file.'"');
		}
		fwrite($handle, $content);
		rewind($handle);
		if($this->ftp->fput($remote_file, $handle, FTP_BINARY)){
			return $this;
		}
		throw new FtpException('Unable to put the file "'.$remote_file.'"');
	}

	/**
	 * Uploads a file to the server.
	 *
	 * @param string $local_file
	 * @return FtpClient
	 * @throws FtpException When the transfer fails
	 */
	public function put_from_path(string $local_file) : static {
		$remote_file = pathinfo($local_file, PATHINFO_BASENAME);
		$handle = fopen($local_file, 'r');
		if(!$handle) throw new FtpException('Unable to open local file "'.$local_file.'"');
		if($this->ftp->fput($remote_file, $handle, FTP_BINARY)){
			rewind($handle);
			return $this;
		}
		throw new FtpException('Unable to put the remote file from the local file "'.$local_file.'"');
	}

	/**
	 * Upload files.
	 *
	 * @param string $source_directory
	 * @param string $target_directory
	 * @param int $mode
	 * @return FtpClient
	 */
	public function put_all(string $source_directory, string $target_directory, int $mode = FTP_BINARY) : static {
		$d = dir($source_directory);
		while($file = $d->read()){
			if($file == '.' || $file == '..') continue;
			if(is_dir("$source_directory/$file")){
				if(!$this->is_dir("$target_directory/$file")){
					$this->ftp->mkdir("$target_directory/$file");
				}
				$this->put_all("$source_directory/$file", "$target_directory/$file", $mode);
			} else {
				$this->ftp->put("$target_directory/$file", "$source_directory/$file", $mode);
			}
		}
		$d->close();
		return $this;
	}

	/**
	 * Downloads all files from remote FTP directory
	 *
	 * @param string $source_directory The remote directory
	 * @param string $target_directory The local directory
	 * @param int $mode
	 * @return FtpClient
	 */
	public function get_all(string $source_directory, string $target_directory, int $mode = FTP_BINARY, int $permissions = 0755) : static {
		if($source_directory != "."){
			if($this->ftp->chdir($source_directory) === false){
				throw new FtpException("Unable to change directory: ".$source_directory);
			}
			if(!file_exists($target_directory)){
				mkdir($target_directory, $permissions, true);
			}
			chdir($target_directory);
		}
		$contents = $this->ftp->nlist(".");
		foreach($contents as $file){
			if($file == '.' || $file == '..') continue;
			$this->ftp->get("$target_directory/$file", $file, $mode);
		}
		$this->ftp->chdir("..");
		chdir("..");
		return $this;
	}

	/**
	 * Returns a detailed list of files in the given directory.
	 *
	 * @see FtpClient::nlist()
	 * @see FtpClient::scan_dir()
	 * @see FtpClient::dir_size()
	 * @param string $directory The directory, by default is the current directory
	 * @param bool $recursive
	 * @return array
	 * @throws FtpException
	 */
	public function rawlist(string $directory = '.', bool $recursive = false) : array {
		if(!$this->is_dir($directory)){
			throw new FtpException('"'.$directory.'" is not a directory.');
		}
		if(strpos($directory, " ") > 0){
			$ftproot = $this->ftp->pwd();
			$this->ftp->chdir($directory);
			$list = $this->ftp->rawlist("");
			$this->ftp->chdir($ftproot);
		} else {
			$list = $this->ftp->rawlist($directory);
		}
		$items = [];
		if(!$list){
			return $items;
		}
		if(!$recursive){
			foreach($list as $path => $item){
				$chunks = preg_split("/\s+/", $item);
				if(!isset($chunks[8]) || strlen($chunks[8]) === 0 || $chunks[8] == '.' || $chunks[8] == '..') continue;
				$path = "$directory/{$chunks[8]}";
				if(isset($chunks[9])){
					$nb_chunks = count($chunks);
					for($i = 9; $i < $nb_chunks; $i++){
						$path .= ' '.$chunks[$i];
					}
				}
				if(substr($path, 0, 2) == './'){
					$path = substr($path, 2);
				}
				$items[$this->raw_to_type($item)."#$path"] = $item;
			}
			return $items;
		}
		$path = '';
		foreach($list as $item){
			$len = strlen($item);
			if(!$len || ($item[$len - 1] == '.' && $item[$len - 2] == ' ' || $item[$len - 1] == '.' && $item[$len - 2] == '.' && $item[$len - 3] == ' ')) continue;
			$chunks = preg_split("/\s+/", $item);
			if(!isset($chunks[8]) || strlen($chunks[8]) === 0 || $chunks[8] == '.' || $chunks[8] == '..') continue;
			$path = "$directory/{$chunks[8]}";
			if(isset($chunks[9])){
				$nb_chunks = count($chunks);
				for($i = 9; $i < $nb_chunks; $i++){
					$path .= ' '.$chunks[$i];
				}
			}
			if(substr($path, 0, 2) == './'){
				$path = substr($path, 2);
			}
			$items[$this->raw_to_type($item).'#'.$path] = $item;
			if($item[0] == 'd'){
				$sublist = $this->rawlist($path, true);
				foreach($sublist as $subpath => $subitem){
					$items[$subpath] = $subitem;
				}
			}
		}
		return $items;
	}

	/**
	 * Parse raw list.
	 *
	 * @see FtpClient::rawlist()
	 * @see FtpClient::scan_dir()
	 * @see FtpClient::dir_size()
	 * @param array $rawlist
	 * @return array
	 */
	public function parse_raw_list(array $rawlist) : array {
		$items = [];
		$path = '';
		foreach($rawlist as $key => $child){
			$chunks = preg_split("/\s+/", $child, 9);
			if(isset($chunks[8]) && ($chunks[8] == '.' || $chunks[8] == '..')) continue;
			if(count($chunks) === 1){
				$len = strlen($chunks[0]);
				if($len && $chunks[0][$len - 1] == ':'){
					$path = substr($chunks[0], 0, -1);
				}
				continue;
			}
			$name_slices = array_slice($chunks, 8, 1);
			$item = [
				'permissions' => $chunks[0],
				'number' => $chunks[1],
				'owner' => $chunks[2],
				'group' => $chunks[3],
				'size' => $chunks[4],
				'month' => $chunks[5],
				'day' => $chunks[6],
				'time' => $chunks[7],
				'name' => implode(' ', $name_slices),
				'type' => $this->raw_to_type($chunks[0]),
			];
			if($item['type'] == 'link' && isset($chunks[10])){
				$item['target'] = $chunks[10];
			}
			if(is_int($key) || false === strpos($key, $item['name'])){
				array_splice($chunks, 0, 8);
				$key = $item['type'].'#'.($path ? $path.'/' : '').implode(' ', $chunks);
				if($item['type'] == 'link'){
					$exp = explode(' ->', $key);
					$key = rtrim($exp[0]);
				}
				$items[$key] = $item;
			} else {
				$items[$key] = $item;
			}
		}
		return $items;
	}

	/**
	 * Convert raw info (drwx---r-x ...) to type (file, directory, link, unknown).
	 * Only the first char is used for resolving.
	 *
	 * @param string $permission Example : drwx---r-x
	 *
	 * @return string The file type (file, directory, link, unknown)
	 * @throws FtpException
	 */
	public function raw_to_type(string $permission) : string {
		if(empty($permission[0])) return 'unknown';
		switch($permission[0]){
			case '-': return 'file';
			case 'd': return 'directory';
			case 'l': return 'link';
			default: return 'unknown';
		}
	}

	/**
	 * Set the wrapper which forward the PHP FTP functions to use in FtpClient instance.
	 *
	 * @param FtpWrapper $wrapper
	 * @return FtpClient
	 */
	protected function set_wrapper(FtpWrapper $wrapper) : static {
		$this->ftp = $wrapper;
		return $this;
	}

}

?>
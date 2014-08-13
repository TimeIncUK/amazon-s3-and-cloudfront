<?php

class Keystone_WpCli_Thumbnails {

	/**
	 * The S3 Remote URL of the image
	 * 
	 * @var string
	 */
	protected $_url;
	
	/**
	 * The local requested file
	 * 
	 * @var string
	 */
	protected $_file;
	
	/**
	 * Initialize the class that needs to handle the callback
	 */
	public static function init() {
		new static();
	}

	/**
	 * Given a path to a file, it returns the path to the parent directory
	 * 
	 * @param string $file The path to the file
	 * @return string The parent directory path
	 */
	public static function get_parent_dir($file) {
		$path = explode(DIRECTORY_SEPARATOR, $file);
		array_pop($path);

		return implode(DIRECTORY_SEPARATOR, $path);
	}

	public function __construct() {
		add_filter("get_attached_file", array($this, "get_image"));
	}

	/**
	 * Given a local file path, that for instance shouldn't exists on the local server, since all images are uploaded to s3, downloads temporarily the image file and 
	 * allows the thumbnail generator to make a thumbnail for it
	 * 
	 * @param string $file the path to the requested file
	 * @return string the path to the requested file
	 */
	public function get_image($file) {
		$this->_file = $file;

		$has_errors = false;
		
		if ($has_errors == false && !$this->is_requesting_local_file()) {
			$has_errors = true;
		}
		if ($has_errors == false && !$this->parent_dir_exists()) {
			$has_errors = true;
		}
		if($has_errors == false || ($this->_url = $this->get_url() && empty(trim($this->_url)))) {
			$has_errors = true;
		}
		
		if($has_errors == false) {
			$download = $this->download();
			if(is_wp_error($download)) {
				WP_CLI::warning($download);
				return $this->_file;
			}
			rename($download, $this->_file);
		}
		
		return $this->_file;
	}

	/**
	 * Checks that the requested path starts with a forward slash
	 * 
	 * @return bool wether it's a local file or a remote file
	 */
	public function is_requesting_local_file() {
		return substr(trim($this->_file), 0, 1) != "/";
	}

	/**
	 * Make sure that the parent file directory exists, if not, creates it
	 * 
	 * @return bool wether the dir exists or has been created
	 */
	public function parent_dir_exists() {
		$exists = true;
		$dir = self::get_parent_dir($this->_file);

		if (!file_exists($dir) || !is_dir($dir)) {
			$exists = mkdir($dir, 0777, true);
		}
		return $exists;
	}
	
	/**
	 * Returns the URL starting from the local path requested
	 * 
	 * @return string the s3 URL for the requested image
	 */
	public function get_url() {
		return sprintf("http://inspire-ipcmedia-com.s3-eu-west-1.amazonaws.com/inspirewp/live%s", substr($this->_file, stripos($this->_file, "/wp-content/")));
	}
	
	/**
	 * Downloads a file from the s3 instance using the WP built in functions
	 * 
	 * @return WP_Error|string or an error or the path to the temporary file location
	 */
	public function download() {
		return download_url($this->_url);
	}
}


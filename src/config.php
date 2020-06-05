<?php

	/**
	* Config File
	*/

	$authorities = array(
		'default' => array(
			  'db_type' => 'mysql'
			, 'username'=> 'tnrs'
			, 'pass' => 'tnrs_read'
			, 'db_name'=> 'tnrs4'
			, 'name' => 'default database'
			, 'id' => 1
			, 'host' => 'localhost'
			, 'cache_flag' => 0
		)
	);

	// CONSTANTS
	define('DB_NAME', 'default');
	//define('DEFAULT_CLASSIFICATION', 'tropicos'); 
	define('TAXAMATCH_URL','vm142-61.iplantcollaborative.org/taxamatch-webservice-read-only/api/taxamatch.php');
	define('CACHE_PATH', '../cache/');

$xml_str = <<<EOT
<xml>
</xml>
EOT;

	define('XML_STRING', $xml_str);

	define('NAME_PARSER', 'gni'); // determines whether 3rd party chopping or taxamatch chopping is to be employed to chop the search text : values : "gni" | "taxamatch"
	define('CHOP_OVERLOAD', false); // whether chopping method is to be overloaded with some other method : values : true | false
	
	define('PROFILE', false);
	define('DEBUG', false);
	
	
	function total_time_elapsed()
	{
		static $time;
		if(!isset($time)) $time = microtime(true);
		return (string) round(microtime(true)-$time, 6);
	}

	function time_elapsed()
    {
		static $time;
		
		if(!isset($time)) $time = 0;
		$elapsed = (string) round(microtime(true)-$time, 6);
		$time = microtime(true);
		return $elapsed;
	}
	
	function profile($str)
	{
		if(!defined('PROFILE') || !PROFILE) return;
		echo time_elapsed().":$str;";
	}
	
	function debug($str)
	{
		if(!defined('DEBUG') || !DEBUG) return;
		echo total_time_elapsed(). ":$str;";
	}

?>

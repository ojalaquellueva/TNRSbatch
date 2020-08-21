<?php

	/**
	* Config File
	*/

	// ABSOLUTE path to directory where DB config file is kept
	// Keep OUTSIDE this repo!
	$DB_CONFIG_PATH="/home/boyle/bien/tnrs/config/";
	$DB_CONFIG_FILE="db_config.php";	// Name of DB config file
	
	// Load DB host, name, user and pwd from config file
	$config_file = $DB_CONFIG_PATH . $DB_CONFIG_FILE;
	require $config_file;
	
	// Rename to prevent potential name collisions
	$TNRS_DB = $DB;
	$TNRS_HOST = $HOST;
	$TNRS_USER = $USER;
	$TNRS_PWD = $PWD;

	$authorities = array(
		'default' => array(
			  'db_type' => 'mysql'
			, 'username'=> $TNRS_USER
			, 'pass' => $TNRS_PWD
			, 'db_name'=> $TNRS_DB
			, 'name' => 'default database'
			, 'id' => 1
			, 'host' => $TNRS_HOST
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

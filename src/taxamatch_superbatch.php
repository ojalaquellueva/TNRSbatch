#! /bin/env php
<?php
	ini_set("memory_limit","1000M");

	require_once('config.php');
	require_once('classes/class.mysqli_database.php');
	require_once('classes/class.misc.php');
	require_once('classes/class.taxamatch.php');
	require_once('classes/class.tnrs_aggregator.php');

	///////////////////////////////////////////////////////////////
	// Command line options (*-optional, see code for defaults):
	//  s  Taxonomic sources: tropicos,tpl,ildis,gcc,usda,ncbi
	//  m  *Search mode: m (best match only)
	//  p  *Parse-only: p (parse only)
	//  c  *Count ???
	//  l  *Family classification: tropicos,ncbi
	//  f  Path and name of input file
	//  o  *Path and name of output file
	//  d  *Delimiter of output file: c (csv), t (tab)
	///////////////////////////////////////////////////////////////
	
	// Get options, set defaults for optional parameters
	$options = getopt("s:m:p:c:l:f:o:d:");
	$source=$options["s"];
	$file=$options["f"];
	$classification=isset($options["l"]) ? $options["l"] : "";
	$search_mode=isset($options["m"]) ? $options["m"] : "";
	$parse_only=isset($options["p"]) ? $options["p"] : "";
	$count=isset($options["c"]) ? $options["c"] : "0";
	$outfile=isset($options["o"]) ? $options["o"] : "";
	$delim=isset($options["d"]) ? $options["d"] : "t";
	$cache=false;

	// Get taxonomic sourcdes
	$source = explode(",", $source);
	$db = select_source( $source, $classification);
	
	// Set the output file delimiter
	if ($delim == 't') {
		$delim = "\t";
	} elseif ($delim == 'c') {
		$delim = ',';
	} else {
		die("Not a valid delimiter, must be c or t");
	}
	
	$header=TnrsAggregator::$field;
	if (isset($count)) {
		array_unshift($header,"ID");
	}

	$outfh = fopen($outfile, 'w') or die("can't open outputfile");
	fputcsv($outfh, $header, $delim);
	
	$handle = fopen($file, "r");
		
	while (($field = fgetcsv($handle, 1000, ",")) !== FALSE) {
			if (!isset($field[0]) || $field[0] == '') {
				continue;
			}

		$ta=new TnrsAggregator($db);
        $tm = new Taxamatch($db);
        $tm->set('debug_flag','');
        $tm->set('output_type','');
        $tm->set('cache_flag',false);
        $tm->set('cache_path',CACHE_PATH);
        $tm->set('name_parser',NAME_PARSER);
        $tm->set('chop_overload',CHOP_OVERLOAD);
        $tm->set('parse_only', $parse_only);	

		$name=escapeshellarg($field[0]);		
	
		if ( $tm->process( $name, $search_mode, $cache ) && ! $parse_only) {
			$tm->generateResponse($cache);
		}
		
		$ta->aggregate($tm);	

		$result=$ta->getData();
		
		foreach ($result as $re) {
			if (isset($count)) {
				array_unshift($re,$count);
			}
			fputcsv($outfh, $re, $delim);
		}
		unset($result);

		$count++;	
	}
	fclose($outfh);
?>


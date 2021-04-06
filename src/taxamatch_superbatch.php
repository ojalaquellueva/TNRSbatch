#! /usr/bin/php
<?php

	ini_set("memory_limit","1000M");
	
	// Prevent escapeshellarg() from stripping accented characters
	setlocale(LC_CTYPE, "en_US.UTF-8");

	require_once('config.php');
	require_once('classes/class.mysqli_database.php');
	require_once('classes/class.misc.php');
	require_once('classes/class.taxamatch.php');
	require_once('classes/class.tnrs_aggregator.php');
	
	// Array for converting accented chars to plain ascii
	$normalizeChars = array(
		'Š'=>'S', 'š'=>'s', 'Ð'=>'Dj','Ž'=>'Z', 'ž'=>'z', 'À'=>'A', 'Á'=>'A', 
		'Â'=>'A', 'Ã'=>'A', 'Ä'=>'A', 'Å'=>'A', 'Æ'=>'A', 'Ç'=>'C', 'È'=>'E', 
		'É'=>'E', 'Ê'=>'E', 'Ë'=>'E', 'Ì'=>'I', 'Í'=>'I', 'Î'=>'I', 'Ï'=>'I', 
		'Ñ'=>'N', 'Ń'=>'N', 'Ò'=>'O', 'Ó'=>'O', 'Ô'=>'O', 'Õ'=>'O', 'Ö'=>'O', 
		'Ø'=>'O', 'Ù'=>'U', 'Ú'=>'U', 'Û'=>'U', 'Ü'=>'U', 'Ý'=>'Y', 'Þ'=>'B', 
		'ß'=>'Ss','à'=>'a', 'á'=>'a', 'â'=>'a', 'ã'=>'a', 'ä'=>'a',	'å'=>'a', 
		'æ'=>'a', 'ç'=>'c', 'è'=>'e', 'é'=>'e', 'ê'=>'e', 'ë'=>'e', 'ì'=>'i', 
		'í'=>'i', 'î'=>'i', 'ï'=>'i', 'ð'=>'o', 'ñ'=>'n', 'ń'=>'n', 'ò'=>'o', 
		'ó'=>'o', 'ô'=>'o', 'õ'=>'o', 'ö'=>'o', 'ø'=>'o', 'ù'=>'u', 'ú'=>'u', 
		'û'=>'u', 'ü'=>'u', 'ý'=>'y', 'ý'=>'y', 'þ'=>'b', 'ÿ'=>'y', 'ƒ'=>'f',
		'ă'=>'a', 'î'=>'i', 'â'=>'a', 'ș'=>'s', 'ț'=>'t', 'Ă'=>'A', 'Î'=>'I', 
		'Â'=>'A', 'Ș'=>'S', 'Ț'=>'T'
	);


	///////////////////////////////////////////////////////////////
	// Command line options (*Default):
	//  s  Taxonomic sources: tropicos*,tpl*,ildis*,gcc*,usda*,ncbi 
	//		(one or more, separated by columns)
	//	r  Search mode: normal* | rapid | no_shaping
	// 		(what about "extended" mode? See class.taxamatch.php.
	//  m  Matches to return: a (all) | b* (best match only)
	//  p  Parse-only: p (parse only)
	//  c  *Count: Prepend integer ID to each line
	//  l  *Family classification: tropicos* | ncbi
	//  f  Path and name of input file
	//  o  *Path and name of output file
	//  d  *Delimiter of output file: c (csv) | t (tab)
	// Option notes:
	//		* 				Default value
	///////////////////////////////////////////////////////////////
	
	// Get options, set defaults for optional parameters
	//$options = getopt("s:m:p:c:l:f:o:d:");
	$options = getopt("s:f:l:r:m:p:c:o:d:");

	$source=$options["s"];
	$file=$options["f"];
	$classification=isset($options["l"]) ? $options["l"] : "";
	$search_mode=isset($options["r"]) ? $options["r"] : "";
	$matches=isset($options["m"]) ? $options["m"] : "";
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
	# Hack to fix parse-only header
	if ( $parse_only=="p" ) {
		$header=array('Name_submitted', 'Family', 'Genus', 'Specific_epithet', 'Infraspecific_rank', 'Infraspecific_epithet', 'Infraspecific_rank_2', 'Infraspecific_epithet_2', 'Author', 'Annotations', 'Unmatched_terms');
	}

	# Prepend ID field to each line if requested
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

		$ta = new TnrsAggregator($db);
        $tm = new Taxamatch($db);
        $tm->set('debug_flag','');
        $tm->set('output_type','');
        $tm->set('cache_flag',false);
        $tm->set('cache_path',CACHE_PATH);
        $tm->set('name_parser',NAME_PARSER);
        $tm->set('chop_overload',CHOP_OVERLOAD);
        $tm->set('parse_only', $parse_only);	

		$name=escapeshellarg($field[0]);		
		
		// Remove/fix embedded commas & apostrophes 
		// Sequence is critical!
		// Handling here until I can find and fix root issue in core 
		// service. These fixes mean that name_submitted will no longer 
		// join back to original data. Must use user id to guarantee 
		// successful back-join of all names
		$name = str_replace(',', ' ', $name);
		$name = str_replace("\\'", "", $name);
		$name = str_replace("\'", "", $name);
		$name = str_replace("''", "'", $name);

		# Replace accented characters with plain ascii equivalent
		$name = strtr($name, $normalizeChars);
		
		if ( $tm->process( $name, $search_mode, $cache ) && ! $parse_only) {
			$tm->generateResponse($cache);
		}
		
		$ta->aggregate($tm);	

		$result=$ta->getData();

		if ( $matches!="a" && $parse_only!="p") {	
		// Keep best match only
			$sort = array();			
			foreach($result as $k=>$v) {
				$sort['Overall_score_order'][$k] = $v['Overall_score_order'];
				$sort['Highertaxa_score_order'][$k] = $v['Highertaxa_score_order'];
			}
			array_multisort(
				array_column($result, 'Overall_score_order'), SORT_ASC,
				array_column($result, 'Highertaxa_score_order'), SORT_ASC,
				$result);
			$result = array_slice($result, 0, 1);
		}
		
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


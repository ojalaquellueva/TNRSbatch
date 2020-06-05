#! /usr/bin/php
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
	//  m  *Search mode: normal | rapid | no_shaping
	// 		(what about "extended" mode? See class.taxamatch.php.
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



		
		// Testing only
		// Set format
		$mask_single = "%-30s \n";
		$mask = "%-10s %-30s %-15s %-25s %-20s %-25s %-5s %-5s %-10s \n";
		
		// Print header
		echo "UNSORTED:\n";
		echo sprintf($mask, "resultID", "Name_submitted", "Overall_score", "Name_matched", "Taxonomic_status", "Accepted_name", "Overall_score_order", "Highertaxa_score_order", "Source");
		//foreach ($result as $idx => $obs) {
		foreach ($result as $idx => $name) {
			//foreach ($name as $idx => $obs) {
				// Output row
				echo sprintf($mask, $idx, 
					$name['Name_submitted'],
					$name['Overall_score'],
					$name['Name_matched'],
					$name['Taxonomic_status'],
					$name['Accepted_name'],
					$name['Overall_score_order'],
					$name['Highertaxa_score_order'],
					$name['Source']
				);
				//echo sprintf($mask, $obs->Name_submitted, $obs->Overall_score, $obs->Name_matched, $obs->Taxonomic_status, $obs->Accepted_name, $obs->Overall_score_order, $obs->Highertaxa_score_order, $obs->Source);
				//}
		}
		
		// Sort the array
		# get a list of sort columns and their data to pass to array_multisort
		$sort = array();
		foreach($result as $k=>$v) {
			$sort['Overall_score_order'][$k] = $v['Overall_score_order'];
			$sort['Highertaxa_score_order'][$k] = $v['Highertaxa_score_order'];
		}
		array_multisort(
			array_column($result, 'Overall_score_order'), SORT_ASC,
            array_column($result, 'Highertaxa_score_order'), SORT_ASC,
            $result);
		

		echo "SORTED:\n";
		echo sprintf($mask, "resultID", "Name_submitted", "Overall_score", "Name_matched", "Taxonomic_status", "Accepted_name", "Overall_score_order", "Highertaxa_score_order", "Source");
		foreach ($result as $idx => $name) {
				// Output row
				echo sprintf($mask, $idx, 
					$name['Name_submitted'],
					$name['Overall_score'],
					$name['Name_matched'],
					$name['Taxonomic_status'],
					$name['Accepted_name'],
					$name['Overall_score_order'],
					$name['Highertaxa_score_order'],
					$name['Source']
				);
		}
		echo "BEST:\n";
		//echo sprintf($mask_single, "Name_submitted");
		echo sprintf($mask, "resultID", "Name_submitted", "Overall_score", "Name_matched", "Taxonomic_status", "Accepted_name", "Overall_score_order", "Highertaxa_score_order", "Source");
		//$resultb = $result[0];
		$result = array_slice($result, 0, 1);
		print_r($result);
// 		echo sprintf($mask,  0, 
// 			$resultb['Name_submitted'],
// 			$resultb['Overall_score'],
// 			$resultb['Name_matched'],
// 			$resultb['Taxonomic_status'],
// 			$resultb['Accepted_name'],
// 			$resultb['Overall_score_order'],
// 			$resultb['Highertaxa_score_order'],
// 			$resultb['Source']
// 		);
		echo "\r\n";
		



		
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


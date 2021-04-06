#! /usr/bin/perl
# Previously: bin/env perl
#controller.pl: Controller for the iPlant TNRS batch service.
#Author: Naim Matasci <nmatasci@iplantcollaborative.org>
#
# The contents of this file are subject to the terms listed in the LICENSE file you received with this code.
# Copyright (c) 2012, The Arizona Board of Regents on behalf of
# The University of Arizona
#
###############################################################################

use strict;
use POSIX;
use Getopt::Long;

my $binpath = $0;
$binpath =~ s/\/?\w+\.?\w*$//;
if ( !$binpath ) {
	$binpath = '.';
}
my $BINARY          = "$binpath/taxamatch_superbatch.php";
my $CONSOLIDATE_SCR = "$binpath/consolidator.pl";

# Master directory where all content saved
my $tmpfoldermaster = "/tmp/tnrs/";

my $infile  = '';    #Input file
my $nbatch  = '';    #Number of batches
my @sources = '';    #Sources, comma separate
my $classification;  #Classification
my $matches;  	 	 #Matches to return: all | best* - optional 
my $mode;  		 	 #Processing mode
my $mf_opt  = '';    #makeflow options - optional
my $outfile = '';    #Optput file - optional
my $d = '';          #Output file delimiter - optional

GetOptions(
	'in=s'      => \$infile,
	'nbatch=i'  => \$nbatch,
	'sources=s' => \@sources,
	'class=s'   => \$classification,
	'matches=s' => \$matches,
	'mode=s'    => \$mode,
	'opt:s'     => \$mf_opt,
	'out:s'     => \$outfile,
	'd:s'	    => \$d,
);

@sources = split( /,/, join( ',', @sources ) );
my $sources = join ',', @sources;

#The temporary folder needs to be in the /tmp directory 
# (see the function _clean)
# mkdir "/tmp/tnrs/" unless -d "/tmp/tnrs/"
my $tmpfolder =
  $tmpfoldermaster . time() . int( rand(10000) );    #Create a temporary folder

while ( -e $tmpfolder ) {    #If a folder with that name already exists
	$tmpfolder = $tmpfoldermaster . time() . int( rand(10000) );    #Try another name
}

#If no output file name given
if ( !$outfile ) {
	$outfile = $infile;
	$outfile =~ s/(?:\.\w+)?$/_scrubbed.csv/
	  ;    #use the input file name w/o extension and append _scrubbed.csv
}

#If no output file delimiter given use tab as default
if ( !$d ) {
	$d = 't';
}

# Set processing mode
if ( !$mode ) {
	$mode = '';		# no parameter: default $mode='resolve'
} else {
	if ( $mode eq 'parse' ) {
		$mode = '-p p';
	} else {
		$mode = '';	# default: $mode='resolve'
	}
} 

# Set match mode
if ( !$matches ) {
	$matches = '-m b';		# no parameter: default $matches='best'
} else {
	if ( $matches eq 'all' ) {
		$matches = '-m a';
	} else {
		$matches = '-m b';	# default:  $matches='best'
	}
} 

# Reset other options if parse-only
if ( $mode eq '-p p' ) {
	$matches = '';
}

#Check for valid delimiter
if ( ( $d ne 't' ) && ( $d ne 'c' ) ) {
	print("Invalid output file delimiter d; must be 't' or 'c'\n"); exit 1;
}

# Check if file ends in newline char; add one if not
my $endsnl = `tail -n 1 "$infile" | wc --lines`;

if ( $endsnl == 0 ) { 
	system("echo '' >> $infile");
}

#Let the magic begin
process( $infile, $nbatch, $tmpfolder, $outfile );

sub process {
	my ( $infile, $nbatch, $tmpfolder, $outfile ) = @_;

	#Get the number of records in the input file
	my $nlines = `wc -l < $infile 2>/dev/null`
	  or die("Cannot find $infile: $!\n");

	if ( $nlines == 0 ) { die("The input file $infile is empty.\n") }

#Calculate the expected size of the batches, given their number and the number of records
	my $exp_g_size = ceil( $nlines / $nbatch );

	#Used to map the original name identifiers to the results.
	my %map;

	#Used to map the original IDs, if present
	my %pids;

	#Used to store names that are already valid. Not used
	my @valids;

	#Indexer for the batch id
	my $batch_id = 0;

	#Indexer for the name id within a batch
	my $id = 0;

	#Line tracker
	my $tot = 0;

	#The list of names forming a batch
	my @batch;

	open( my $INL, "<$infile" ) or die "Cannot open input file $infile: $!\n";

	while (<$INL>) {

		$tot++;
		chomp;

		my $name = $_;

		#A name that is present more than once in the list, but with different primary id, will be processed only once
		#All the associated primary ids will be returned. 
		my $pid;    #Primary id: the original id, if present
		if ( $name =~ m/\|/ ) {
			( $pid, $name ) = ( split /\|/, $name );
			$name =~ s/^\s+//;	# trim leading whitespace from name
			if ( exists $pids{$name} ) {
				my @k = @{ $pids{$name} };
				unshift @k, $pid;
				$pids{$name} = \@k;
			}
			else {
				$pids{$name} = [$pid];
			}
		}

		if ( exists $map{$name} && $tot <= $nlines ) { #We have already seen that name
			next;
		}

		#If we know that a name is already an accepted name, we only need to retrieve it, not match it
		#		if (is_accepted($_)){ 
		#			push @valids,$_;
		#			next;
		#		}
		
		push @batch, $name;
		
		#Every name is assigned a unique internal id, combining its batch id and position within the batch
		$map{$name} = "$batch_id.$id";
		
		$id++;
		
		#We write a file every time we reach the predetermined batchsize or if there aren't any more names.
		if ( @batch >= $exp_g_size || $tot == $nlines ) {
			_write_out( $batch_id, \@batch, $tmpfolder );

			#			_write_screen($batch_id,\@batch);
			@batch = ();
			$batch_id++;
			$id = 0;
		}

	}
	close $INL;
	
	_write_map( \%map, "$tmpfolder/map.tab" ); #Mapping between the name and the internal id

	if (%pids) {
		_write_map( \%pids, "$tmpfolder/pids.tab", 1 ); #Mapping between the name and the original ids
	}

	_generate_mfconfig( $batch_id, $tmpfolder, $outfile ); #Writes the makeflow control file
	
	print "tmpfolder='$tmpfolder'\n";
	my $makeflow_cmd="makeflow $mf_opt $tmpfolder/tnrs.flow";
	print "makeflow_cmd='$makeflow_cmd'\n";
	
	system("makeflow $mf_opt $tmpfolder/tnrs.flow"); #Run makeflow
	
	#_clean($tmpfolder); #Remove all temporary data

}

#Writes a mapping to a tab separated file
sub _write_map {
	my ( $map, $fn, $invert ) = @_;
	
	open my $MAP, ">$fn" or die "Cannot write map file $fn: $!\n";
	while ( my ( $name, $id ) = each %{$map} ) {
		if ($invert) { #In case the name and ids are swapped (depends which one is unique)
			my $t = $id;
			$id   = $name;
			$name = $t;
		}
		if ( ref($name) eq 'ARRAY' ) { 
			$name = join ',', @{$name};
		}
		print $MAP "$id\t$name\n";
	}
	close $MAP;
}

#Writes the makeflow control file
sub _generate_mfconfig {
	my ( $batch_id, $tmpfolder, $outfile ) = @_;
	
	my $filelist; #list of output files that will be produced
	
	my $cmd = "TNRSBIN=$BINARY\n";
	
	#A 2 lines instruction is written for every input file, 
	for ( my $i = 0 ; $i < $batch_id ; $i++ ) {
		my $operation =
		  "$tmpfolder/out_$i.txt: $tmpfolder/names/in_$i.txt \$TNRSBIN\n"; #Line 1: output and input files
		$operation .=
"\t\$TNRSBIN $mode $matches -f $tmpfolder/names/in_$i.txt -s $sources -l $classification -o $tmpfolder/out_$i.txt -d $d\n\n"; #Line 2: command
		$cmd = $cmd . $operation;
		$filelist .= "$tmpfolder/out_$i.txt ";
	}
	
	#Call to the consolidation script
	#$cmd .= "$tmpfolder/output.csv: $CONSOLIDATE_SCR $tmpfolder $filelist\nLOCAL $CONSOLIDATE_SCR $tmpfolder\n\n";
	$cmd .= "$tmpfolder/output.csv: $CONSOLIDATE_SCR $tmpfolder $filelist\n $CONSOLIDATE_SCR $tmpfolder $d\n\n";

	#Copy the consolidated output to the final destination
	#$cmd .= "$outfile: $tmpfolder/output.csv\nLOCAL cp $tmpfolder/output.csv $outfile\n\n";
	$cmd .= "$outfile: $tmpfolder/output.csv\n cp $tmpfolder/output.csv $outfile\n\n";

	#Write the file to the temporary folder
	open my $FF, ">$tmpfolder/tnrs.flow"
	  or die("Cannot write makeflow file $tmpfolder/tnrs.flow: $!\n");
	print $FF $cmd;
	close $FF;
}

#Write a batch of names to a files in the temporary folder
sub _write_out {
	my $batch_id  = shift;
	my $batch     = shift;
	my $tmpfolder = shift;

	if ( !-e $tmpfolder ) {
		mkdir $tmpfolder
		  or die("Cannot create temporary folder $tmpfolder: $!\n");
		mkdir "$tmpfolder/names"
		  or die("Cannot create temporary folder $tmpfolder/names: $!\n");
	}
	
	$tmpfolder = "$tmpfolder/names"; #Batch files are stored in the subfolder names
	open( my $OF, ">$tmpfolder/in_$batch_id.txt" )
	  or die("Cannot write output file $tmpfolder/in_$batch_id.txt: $!\n");
	print $OF join( "\n", @{$batch} );
	close $OF;
}

#In case no files need to be written (Unused)
sub _write_screen {
	my $batch_id = shift;
	my @batch    = @{ shift() };
	for ( my $i = 0 ; $i < @batch ; $i++ ) {
		print "$batch_id.$i\t$batch[$i]\n";
	}

}

#Remove temporary files
#The tempfolder needs to be in the /tmp directory
sub _clean {
	my $td = shift;
	$td =~ s/^\/tmp//; 	#This is a failsafe to avoid accidentally deleting other relevant files.
	my $dummy = system("rm -rf /tmp$td");
}

#Dummy function, in case accepted names are to be treated differently
sub is_accepted {
	return 0;

}

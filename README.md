# Batch service for the iPlant TNRS

### Contents

I. Purpose
II. Background
III. Dependencies  
IV. Usage  
V. Notes  

### I. Purpose

TNRSbatch accepts one or more taxonomic names as input, matching each name against a master list of published taxonomic names, and updating any synonym names to the current accepted name. 

Prior to matching, names strings are parsed to the taxon from the authority, and the different name components (representing different levels of the taxonomic hierarchy) are separated. For example, the name "Poa annua var. supina" is parsed as genus="Poa", specific epithet="annua", infraspecific taxon='supina' and infraspecific rank = 'variety'. Parsing allows a name to be partially matched to a higher taxon with the lower taxon cannot be resolved. Matching is performed using a fuzzy-matching algorithm which improves performance by searching within the taxonomic hierarchy, and efficiently handles spelling and formulation errors specific to taxonomic names. 

Input is a plain text file of one or more taxonomic names, one name per line. Currently, names MUST be preceded by a unique integer identifier. This identifier and name MUST be separate by a pipe ('|') delimiter. Location of this file can be specified as a command-line parameter. See example file in directory example_data/.

Output is a comma- or tab-delimited file, similar in format to a download from the TNRS web user interface, using options "All matches" and "Detailed". Location of this file can be specified as a command-line parameter.

### II. Background

TNRSbatch is a command line adaptation of the iPlant TNRS version 4.0 (http://tnrs.iplantcollaborative.org/), herein referred to as "online TNRS" (Repository: https://github.com/iPlantCollaborativeOpenSource/TNRS). TNRSbatch builds on the core TNRS services (GNI name parser and Taxamatch) and adds a wrapper that controls batch processing and multi-threading. Options originally set via the web interface are accepted as command line options. All key functionality available via the web interface is replicated by TNRSbatch. A MySQL TNRS database is also required (see Requirements, below).

This version of TNRSbatch is a fork of the original TNRSbatch developed by Naim Matasci and others (https://github.com/nmatasci/TNRSbatch). The main difference of this fork from the original is the addition of command line parameters that more fully replicate the functionality of the online TNRS. 

### III. Dependencies

#### 1. TNRS database

TNRSbatch requires a connection to a fully-populated MySQL TNRS database. The TNRS database is constructed using the PHP code in directory tnrs3_db_scripts or the online TNRS repository (https://github.com/iPlantCollaborativeOpenSource/TNRS/tree/master/tnrs3_db_scripts). 

### IV. Usage

```
./controller.pl -in <input_filename_and_path> -sources <tnrs_source_list> -out "data/tnrsbatch_scrubbed.csv" -out <output_filename_and_path> -class "tropicos" -nbatch <batches> -opt <makeflow_options> -d <output_file_delimiter>

```
#### Options (*=default):

Option | Meaning
--- | ---
in	| Input file name. Include path if in different directory.  
out	| Output file name. Include path if in different directory.  
sources	| Taxonomic sources. See Notes.  	
class	| Source of family classification [tropicos*,ncbi]. See Notes.  
nbatch	| Number of batches to split the file into
opt	| Makeflow options [not sure what these are]
d |  Delimiter to use for output file [comma*,tab]
 

#### Example replicating default online TNRS settings:  

```
./controller.pl -in "../example_data/testfile"  -out "../example_data/testfile_scrubbed.csv" -sources "tropicos,ildis,gcc,tpl,usda,ncbi" -class "tropicos" -nbatch 10 -d t 
```

### V. Notes

1. Taxonomic sources (command line option "sources") are the short codes for the taxonomic databases, as loaded to the TNRS database, that will be consulted to resolve the name. These codes are drawn directly from the TNRS database, as per column "sourceName" of table source.

2. Family classification source (command line option "class"). The short codes of the taxonomic database used to apply the family classification to each name. These codes are drawn directly from column "sourceName" in table source of the TNRS database. The source must also have a complete family classification in table higherClassification. This relationship is identified by the join source.sourceID=higherClassification.classificationSourceID.

3. Family may be pre-pended to the scientific name (e.g., "Poaceae Poa annua"). This will constrain the genus and species matches that family only. Prevents spurious fuzzy matches to similarly-spelled taxa in other families.

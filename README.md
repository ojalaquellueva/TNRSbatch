# Taxonomic Name Resolution Service (TNRS) Batch Application

### Contents

I. Purpose  
II. Background  
III. Dependencies  
IV. Usage  
V. Notes  
VI. References  

### I. Purpose

TNRSbatch is an application for correcting and standardizing taxonomic names. TNRSbatch accepts one or more taxonomic names as input, matching each name against a master list of published taxonomic names and updating synonyms to the current accepted name. 

TNRSbatch resolves names in three main steps: parsing, matching and updating. In the matching step, names strings are parsed to separate the taxon from the authority, using the Ruby gem version ("biodiversity") of the GNParser (Mozzherin 2008). Name components representing different levels of the taxonomic hierarchy are also separated, as well as annotations such as "cf.", "aff.", "sp. nov.",  etc. For example, the name "Poa annua var. supina" is parsed as genus="Poa", specific epithet="annua", infraspecific taxon='supina' and infraspecific rank = 'variety'. Parsing allows a name to be matched to a higher taxon when the lower taxon cannot be resolved. 

The matching step uses both exact and fuzzy matching to find the best match of the parsed name to published taxonomic names in the reference database. The fuzzy-matching algorithm (Taxamatch; see https://journals.plos.org/plosone/article?id=10.1371/journal.pone.0107510)  improves performance by searching within the taxonomic hierarchy, and efficiently handles spelling and formulation errors specific to taxonomic names.

In the update step, matched name which are synonyms are updated to the current accepted ("correct") name, according to the taxonomic sources consulted in the reference database.

The input for TNRSbatch is a plain text file of one or more taxonomic names, one name per line, with or without authorities. If desired, the family may be prepended to the taxon name, separate by a single whitespace (e.g., "Poaceae Poa supina"). Including the family allows disambiguation of honomyms or similar names in different families. The file must have one name per line, and each name MUST be preceded by a unique integer identifier, separated from the name by a pipe ('|') delimiter. See example file in directory example_data/.

TNRSbatch output is a comma- or tab-delimited file, similar in format and content to a download from the TNRS web user interface using options "All matches" and "Detailed" (http://tnrs.iplantcollaborative.org/). 

### II. Background

TNRSbatch is a command line fork of the Taxonomic Name Resolution Service (herein referred to as "online TNRS"; see Boyle et al. 2013). TNRSbatch includes all key features and options of the online TNRS, with added support for parallel processing. TNRSbatch builds on the core TNRS components (TNRS database, GNparser and Taxamatch), plus key algorithms previously embedded in the Java user interface of the online TNRS. Perl controller scripts add multi-threading capability using Makeflow. Options originally selected via the web interface are set as command line options. All key functionality available in the web interface is replicated by the combination of TNRSbatch, GNParser and TNRS database (see Dependencies, below).

This version of TNRSbatch is a fork of the original TNRSbatch developed by Naim Matasci and others (https://github.com/nmatasci/TNRSbatch). This fork updates code and adds command line parameters that more fully replicate the functionality of the online TNRS. 

### III. Dependencies

#### 1. TNRS database

TNRSbatch requires a connection to a fully-populated MySQL TNRS database. The TNRS database is constructed using the PHP code in directory tnrs3_db_scripts or the online TNRS repository (https://github.com/iPlantCollaborativeOpenSource/TNRS/tree/master/tnrs3_db_scripts). 

#### 2. GN parser (biodiversity)

* TNRSbatch requires the GN name parser running as a socket server. Name parser repository:

https://github.com/GlobalNamesArchitecture/biodiversity

* Install the parser (assumes ruby installed on local system):

```
sudo gem install biodiversity
```

* Start the service:

``` 
nohup parserver &
<ctrl>+C
```

### IV. OS & software requirements

* Ubuntu 16+ (not test on earlier versions or other \*nix OSs)  
* Perl (tested on 5.26.1)  
* PHP (tested on 7.2.19)  
* MySQL (tested on 5.7.26)  
* Makeflow (tested on 4.0.0-RELEASE (released 02/06/2018))  
* Ruby (tested on 2.5.1p57)  

### IV. Usage

#### 1. Run the multi-threaded batch application  

"controller.pl" is the main application. Invokes "taxamatch_superbatch.php" & parallelizes the operation using makeflow.

**Syntax**  

```
./controller.pl -in <input_filename_and_path> -sources <tnrs_source_list> -out "data/tnrsbatch_scrubbed.csv" -out <output_filename_and_path> -class "tropicos" -nbatch <batches> -opt <makeflow_options> -d <output_file_delimiter>
```
**Options**  
(*=default)  

Option | Meaning
--- | ---
in	| Input file name. Include path if in different directory.  
out	| Output file name. Include path if in different directory.  
sources	| Taxonomic sources. See Notes.  	
class	| Source of family classification [tropicos*,ncbi]. See Notes.  
nbatch	| Number of batches to split the file into
opt	| Makeflow options (see https://ccl.cse.nd.edu/software/manuals/man/makeflow.html for details)
d |  Delimiter to use for output file [comma*,tab]
 

**Example replicating default online TNRS settings**  

```
./controller.pl -in "../data/testfile"  -out "../data/testfile_scrubbed.csv" -sources "tropicos,ildis,gcc,tpl,usda,ncbi" -class tropicos -nbatch 10 -d t 
```

#### 2. Run the core batch application as a standalone  

"taxamatch_superbatch.php" is the core application invoked by controller.pl. Most users won't need this except for testing changes to core service code.

**Syntax**

```
php taxamatch_superbatch.php -s <sources> -f <input_file> -o <output_file> [-l <classification>] [-m] [-p] [-d]
```

**Example**

```
php taxamatch_superbatch.php -s "tropicos,ildis,gcc,tpl,usda,ncbi" -f "../data/testfile.small" -o "../data/testfile.small_scrubbed.csv" 
```


#### Core application options (*=default):

Option |	Required?	|	Meaning
--- | :---: | ---
f	| Y | Input file name. Include path if in different directory.  
o	| Y | Output file name. Include path if in different directory.  
s	| Y | Taxonomic sources. One or more, comma-delimitted. See Notes.  	
l	| N | Source of family classification [tropicos*,ncbi]. See Notes.  
m	| N | Best match only (returns all matches if omitted)
p	| N | Parse only (resolves to matched and accepted names if omitted)
d	| N | Delimiter to use for output file [c* (comma),t (tab)]


### V. Notes

1. Taxonomic sources (command line option "sources") are short codes for the taxonomic sources in the TNRS database that will be consulted to resolve the name. These codes are drawn directly from the TNRS database, as per column "sourceName" of table "source". Current values: 'tropicos, tpl, gcc, ildis, usda, ncbi. Sources code parameters are submitted as a comma delimited list surround by quotes: e.g., 'tropicos,tpl,usda'. See online TNRS website for more information on each source (http://tnrs.iplantcollaborative.org/sources.html).

2. Family classification source (command line option "class"). The short codes of the taxonomic database used to apply the family classification to each name. These codes are drawn directly from column "sourceName" in table source of the TNRS database. The source must also have a complete family classification in table higherClassification. This relationship is identified by the join source.sourceID=higherClassification.classificationSourceID. See http://tnrs.iplantcollaborative.org/instructions.html#classification for details.

3. Family may be pre-pended to the scientific name (e.g., "Poaceae Poa annua"). This will constrain the genus and species matches that family only. Including family prevents spurious matches to homonyms or similarly-spelled taxa in different families.

### VI. References  

﻿Boyle, B., N. Hopkins, Z. Lu, J. A. Raygoza Garay, D. Mozzherin, T. Rees, N. Matasci, M. L. Narro, W. H. Piel, S. J. Mckay, S. Lowry, C. Freeland, R. K. Peet, and B. J. Enquist. 2013. The taxonomic name resolution service: An online tool for automated standardization of plant names. BMC Bioinformatics 14(1):16.

Mozzherin, D. Y. 2008. GlobalNamesArchitecture/biodiversity: Scientific Name Parser. https://
github.com/GlobalNamesArchitecture/biodiversity. Accessed 15 Sep 2017

Rees, T. 2014. Taxamatch, an Algorithm for Near ('Fuzzy’) Matching of Scientific Names in Taxonomic Databases. PloS one 9(9): e107510.

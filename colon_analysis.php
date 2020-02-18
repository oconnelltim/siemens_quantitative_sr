<!DOCTYPE HTML>
<html>
<head>
    <link rel="stylesheet" type="text/css" href="quantitative.css">
</head>
<body>

<?php

    // colon_analysis.php - a script to extract CT colonography analysis parameters
    //   from Siemens Syngo.via DICOM structured reports
    // Copyright (C) 2016 Tim O'Connell (tim.oconnell at ubc dot ca)

    // This program is free software: you can redistribute it and/or modify
    // it under the terms of the GNU General Public License as published by
    // the Free Software Foundation, either version 3 of the License, or
    // (at your option) any later version.
    
    // This program is distributed in the hope that it will be useful,
    // but WITHOUT ANY WARRANTY; without even the implied warranty of
    // MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    // GNU General Public License for more details.
    
    // You should have received a copy of the GNU General Public License
    // along with this program.  If not, see <http://www.gnu.org/licenses/>.

//***************************
// Declare some variables
//***************************
require 'settings.php';

// Need to make these safer
$studyuid = $_GET['studyUID'];
$seriesuid = $_GET['seriesUID'];
$objectuid = $_GET['objectUID'];

//echo "Accession: $accession<br>\n"; //DEBUG
$dsrdump = "";
$dsrarray = array();
$extractarray = array();

//***************************
//Main
//***************************
movescu();
dump_file();
parse_dump();
print_data();
//echo "<pre>\n";
//echo "\n\n";
//echo "Raw Data:\n";
//$escapeddsrdump = htmlspecialchars($dsrdump); //DEBUG
//print_r($escapeddsrdump);
//print_r($extractarray);
//echo "</pre>\n";
//***************************
// Functions
//***************************

function movescu() {
	global $studyuid, $seriesuid, $objectuid, $myaetitle, $pacsaetitle, $pacstcpport, $pacsipaddress;
    $command = "movescu -v -aet $myaetitle -aec $pacsaetitle -aem $myaetitle -S -k 0008,0052=\"IMAGE\" -k 0020,000d=\"$studyuid\" -k 0020,000e=\"$seriesuid\" -k 0008,0018=\"$objectuid\"  $pacsipaddress $pacstcpport 2>&1";
    //echo "\n\n";//DEBUG
    //echo "COMMAND: \n" ; //DEBUG
    //echo $command; //DEBUG
    $movescu = shell_exec($command);
    //echo "\n\n";//DEBUG
    //echo $movescu; //DEBUG
}

function dump_file() {
	global $studyuid, $objectuid, $dsrdump;
    $infile = "/usr/local/dcmtk/file_store/$studyuid/SRc.$objectuid";
	$command2 = "dsrdump -Ph $infile 2>&1";
	//echo "Command2: $command2\n"; //DEBUG
	$dsrdump = shell_exec($command2);
	//$escapeddsrdump = htmlspecialchars($dsrdump); //DEBUG
    //echo "\n\ndsrdump:\n$escapeddsrdump\n"; //DEBUG
}


function parse_dump() {
    global $dsrdump, $extractarray;
    $count = 0;
    $extractarray = array();
    $dsrarray = preg_split("/[\n|\r]/", $dsrdump);
    //print_r($dsrarray); //DEBUG
    // First, clean up all the measurements, etc.
	foreach ($dsrarray as $key => $value) {
        if ((preg_match("/Distance to rectum\"\)\=\(R\-41D2D\,SRT/", $value)) > 0) {
            unset($dsrarray[$key]);
        }
        if ((preg_match("/Diameter/", $value)) > 0) {
            if ((preg_match("/Maximum/", $dsrarray[$key + 1])) > 0) {
                $dsrarray[$key] = str_replace("Diameter", "Diameter (max)", $dsrarray[$key]); 
            }
            elseif ((preg_match("/Minimum/", $dsrarray[$key + 1])) > 0) {
                $dsrarray[$key] = str_replace("Diameter", "Diameter (min)", $dsrarray[$key]); 
            }
        }
        $dsrarray[$key] = str_replace("Precision", "Analysis Precision", $dsrarray[$key]);
        $dsrarray[$key] = str_replace("Hounsfield unit", " HU", $dsrarray[$key]);
        $dsrarray[$key] = str_replace("cubic millimeter", " mm^3", $dsrarray[$key]);
        $dsrarray[$key] = str_replace("millimeter", " mm", $dsrarray[$key]);
        $dsrarray[$key] = str_replace("no units", " ", $dsrarray[$key]);
    }
    // Now, store everything into our array
	foreach ($dsrarray as $key => $value) {
        if ((preg_match("/Lesion finding\"\)\=SEPARATE/", $value)) > 0) {
            $count++;
        }
        elseif ((preg_match("/.+\"(.+?)\".+\"(.+?)\".+\"(.+?)\"/", $value)) > 0) {
            preg_match("/.+\"(.+?)\".+\"(.+?)\".+\"(.+?)\"/", $value, $extract);
            $extract[2] = round($extract[2], 2);
            $extractarray[$count][$extract[1]] = $extract[2] . $extract[3];
        }
        elseif ((preg_match("/.+\"(.+?)\".+\"(.+?)\"/", $value)) > 0) {
            preg_match("/.+\"(.+?)\".+\"(.+?)\"/", $value, $extract);
            $extractarray[$count][$extract[1]] = $extract[2];
        }
        // Get rid of stuff we don't need
        unset($extractarray[$count]["Finding"]);
        unset($extractarray[$count]["Derivation"]);
        unset($extractarray[$count]["Lesion identifier"]);
//        unset($extractarray[$count]["Finding Site"]);
    }
    unset($extractarray[0]); //Get rid of stuff before 'Findings'
    //print_r($extractarray); //DEBUG
}

function print_data() {
    global $extractarray;
    array_multisort($extractarray);

    echo "QUANTITATIVE ANALYSIS FINDINGS<br>\n";
    echo "------------------------------<br>\n";
    foreach ($extractarray as $key => $value) {
        ksort($value);
        $polyp_num = $key+1;
        echo "Colonic Polyp #$polyp_num<br>\n";
        foreach($value as $iKey => $iValue) {
            echo "$iKey: $iValue<br>\n";
        }
        echo "<br>\n";
    }
}

?>

</body>
</html>

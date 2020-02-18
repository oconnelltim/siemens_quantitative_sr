<!DOCTYPE HTML>
<html>
<head>
    <link rel="stylesheet" type="text/css" href="quantitative.css">
</head>
<body>

<?php

    // cardiac_cascore.php - a script to extract Coronary Artery Calcium Score CT analysis parameters
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
//echo "\n\n";//DEBUG
movescu();
dump_file();
parse_dump();
print_data();
//echo "<pre>\n";
//echo "\n\nRaw Data:\n";
//$escapeddsrdump = htmlspecialchars($dsrdump); //DEBUG
//echo "\n\ndsrdump:\n$escapeddsrdump\n"; //DEBUG
//echo "\n\n";
//print_r($extractarray); //DEBUG
//echo "</pre>\n";

//***************************
// Functions
//***************************

function movescu() {
    global $studyuid, $seriesuid, $objectuid, $myaetitle, $pacsaetitle, $pacstcpport, $pacsipaddress;
    $command = "movescu -v -aet $myaetitle -aec $pacsaetitle -aem $myaetitle -S -k 0008,0052=\"IMAGE\" -k 0020,000d=\"$studyuid\" -k 0020,000e=\"$seriesuid\" -k 0008,0018=\"$objectuid\"  $pacsipaddress $pacstcpport 2>&1";
    //echo "COMMAND: \n" ; //DEBUG
    //echo $command; //DEBUG
    $movescu = shell_exec($command);
    //echo "\n\n";//DEBUG
    //echo $movescu; //DEBUG
}

function dump_file() {
	global $studyuid, $seriesuid, $objectuid, $dsrdump;
    $infile = "/usr/local/dcmtk/file_store/$studyuid/SRc.$objectuid";
	$command = "dsrdump -Ph -Ei -Er -Ec -Ee $infile 2>&1";
	//echo "Command: $command\n"; //DEBUG
	$dsrdump = shell_exec($command);
}


function parse_dump() {
    global $dsrdump, $extractarray;
    $count = 0;
    $site = "";
    $extractarray = array();
    $dsrarray = preg_split("/[\n|\r]/", $dsrdump);
    //print_r($dsrarray); //DEBUG
    // First, clean up all the measurements, etc.
	foreach ($dsrarray as $key => $value) {
        $dsrarray[$key] = str_replace("Precision", "Analysis Precision", $dsrarray[$key]);
        $dsrarray[$key] = str_replace("Hounsfield unit", " HU", $dsrarray[$key]);
        $dsrarray[$key] = str_replace("cubic millimeter", " mm^3", $dsrarray[$key]);
        $dsrarray[$key] = str_replace("millimeter", " mm", $dsrarray[$key]);
        $dsrarray[$key] = str_replace("Ratio", "Density Ratio (Low:High kV)", $dsrarray[$key]);
        $dsrarray[$key] = str_replace("no units", " ", $dsrarray[$key]);
    }
    // Now, store everything into our array
	foreach ($dsrarray as $key => $value) {
        if ((preg_match("/.+Lesion Identifier\"\)\=\"/", $value)) > 0) {
            preg_match("/.+\"(.+?)\".+\"(.+?)\"/", $value, $extract);
            $site = $extract[2];
        }
        elseif ((preg_match("/.+\"(.+?)\".+\"(.+?)\".+\"(.+?)\"/", $value)) > 0) {
            preg_match("/.+\"(.+?)\".+\"(.+?)\".+\"(.+?)\"/", $value, $extract);
            $extract[2] = round($extract[2], 2);
            $extractarray[$site][$extract[1]] = $extract[2] . " " . $extract[3];
        }
        elseif ((preg_match("/.+\"(.+?)\".+\"(.+?)\"/", $value)) > 0) {
            preg_match("/.+\"(.+?)\".+\"(.+?)\"/", $value, $extract);
            $extractarray[$site][$extract[1]] = $extract[2];
        }
        // Get rid of stuff we don't need
        unset($extractarray[$site]["Finding Source"]);
        //unset($extractarray[$site]["Lesion Identifier"]);
        unset($extractarray[$site]["Measurement Method"]);
        unset($extractarray[$site]["Index"]);
    }
    unset($extractarray[0]); //Get rid of stuff before 'Findings'
}

function print_data() {
    global $extractarray;
    array_multisort($extractarray);

    echo "QUANTITATIVE ANALYSIS FINDINGS<br>\n";
    echo "-----------------------------------<br>\n";
    echo "Site: Left Main Coronary Artery<br>\n";
    foreach ($extractarray['LM'] as $key => $value) {
        echo "$key: $value<br>\n";
    }
    echo "<br>\n";
    echo "Site Left Anterior Descending Coronary Artery<br>\n";
    foreach ($extractarray['LAD'] as $key => $value) {
        echo "$key: $value<br>\n";
    }
    echo "<br>\n";
    echo "Site: Circumflex Coronary Artery<br>\n";
    foreach ($extractarray['CX'] as $key => $value) {
        echo "$key: $value<br>\n";
    }
    echo "<br>\n";
    echo "Site: Right Coronary Artery<br>\n";
    foreach ($extractarray['RCA'] as $key => $value) {
        echo "$key: $value<br>\n";
    }
    echo "<br>\n";
    if (isset($extractarray['U1'])) {
        echo "Site: User-specified Site<br>\n";
        foreach ($extractarray['U1'] as $key => $value) {
            echo "$key: $value<br>\n";
        }
    }
    echo "<br>\n";
    if (isset($extractarray['U2'])) {
        echo "Site: User-specified Site<br>\n";
        foreach ($extractarray['U2'] as $key => $value) {
            echo "$key: $value<br>\n";
        }
    }
    echo "-----------------------------------<br>\n";
    echo "Total Coronary Artery Calcium Score<br>\n";
    foreach ($extractarray['Total'] as $key => $value) {
        echo "$key: $value<br>\n";
    }
    echo "-----------------------------------<br>\n";
}

?>

</body>
</html>

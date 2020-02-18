<!DOCTYPE HTML>
<html>
<head>
    <link rel="stylesheet" type="text/css" href="quantitative.css">
</head>
<body>

<?php

    // dose_analysis.php - a script to extract CT dose parameters
    //   from Siemens Syngo.via DICOM RDSRs
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
$output = array();
$array = array();
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
    //echo "\n\n";//DEBUG
    //echo "COMMAND: \n" ; //DEBUG
    //echo $command; //DEBUG
    $movescu = shell_exec($command);
    //echo "\n\n";//DEBUG
    //echo $movescu; //DEBUG
}

function dump_file() {
	global $studyuid, $objectuid, $dsrdump;

	$infile = "/usr/local/dcmtk/file_store/$studyuid/SRd.$objectuid";
	$command2 = "dsrdump -Ph -Ei -Er -Ec -Ee $infile 2>&1";
	//echo "Command2: $command2\n"; //DEBUG
	$dsrdump = shell_exec($command2);
}


function parse_dump() {
    global $dsrdump, $extractarray;
    $count = 0;
    $extractarray = array();
    $dsrarray = preg_split("/[\n|\r]/", $dsrdump);
    //print_r($dsrarray); //DEBUG
	foreach ($dsrarray as $key => $value) {
        $dsrarray[$key] = str_replace("Topogram", "Topogram (scan planning x-ray)", $dsrarray[$key]);
    }
	foreach ($dsrarray as $key => $value) {
        if ((preg_match("/CONTAINER\:\(\,\,\"CT Acquisition\"\)\=SEPARATE\>/", $value)) > 0) {
            $count++;
        }
        elseif ((preg_match("/.+\"(.+?)\".+\"(.+?)\".+\"(.+?)\"/", $value)) > 0) {
            preg_match("/.+\"(.+?)\".+\"(.+?)\".+\"(.+?)\"/", $value, $extract);
            $extract[2] = round($extract[2], 2);
            $extractarray[$count][$extract[1]] = $extract[2] . " " . $extract[3];
        }
        elseif ((preg_match("/.+\"(.+?)\".+\"(.+?)\"/", $value)) > 0) {
            preg_match("/.+\"(.+?)\".+\"(.+?)\"/", $value, $extract);
            $extractarray[$count][$extract[1]] = $extract[2];
        }
    }
}

function print_data() {
    global $extractarray;
    array_multisort($extractarray);
    $eDose = 0;
    $tempdose = "";
    echo "RADIATION DOSE REPORT<br>\n";
    echo "----------------------------------------------------------------<br>\n";
    echo "CT Dose Length Product Total: " . $extractarray[0]['CT Dose Length Product Total'] . "<br>\n<br>\n";
    unset($extractarray[0]);
    foreach ($extractarray as $key => $value) {
        ksort($value);
        echo "Irradiation Event # " . $key . "<br>\n";
        echo "Acquisition Name: " . $extractarray[$key]['Acquisition Protocol'] . "<br>\n";
        echo "Bodypart: " . $extractarray[$key]['Target Region'] . "<br>\n";
        if ($extractarray[$key]['Target Region'] == "Head") {
            $tempdose = round((0.0023 * $extractarray[$key]['DLP']), 2);
            echo "Effective Dose: $tempdose mSv<br>\n";
            $eDose = $eDose + $tempdose;
            unset($tempdose);
        }
        if ($extractarray[$key]['Target Region'] == "Heart") {
            $tempdose = round((0.014 * $extractarray[$key]['DLP']), 2);
            echo "Effective Dose: $tempdose mSv<br>\n";
            $eDose = $eDose + $tempdose;
            unset($tempdose);
        }
        if ($extractarray[$key]['Target Region'] == "Chest") {
            $tempdose = round((0.014 * $extractarray[$key]['DLP']), 2);
            echo "Effective Dose: $tempdose mSv<br>\n";
            $eDose = $eDose + $tempdose;
            unset($tempdose);
        }
        if ($extractarray[$key]['Target Region'] == "Abdomen") {
            $tempdose = round((0.015 * $extractarray[$key]['DLP']), 2);
            echo "Effective Dose: $tempdose mSv<br>\n";
            $eDose = $eDose + $tempdose;
            unset($tempdose);
        }
        if ($extractarray[$key]['Target Region'] == "Neck") {
            $tempdose = round((0.0059 * $extractarray[$key]['DLP']), 2);
            echo "Effective Dose: $tempdose mSv<br>\n";
            $eDose = $eDose + $tempdose;
            unset($tempdose);
        }
        if ($extractarray[$key]['Target Region'] == "Extremity") {
            // THIS K-FACTOR IS MADE UP. Find a real one to use. 
            $tempdose = round((0.001 * $extractarray[$key]['DLP']), 2);
            echo "Effective Dose: $tempdose mSv<br>\n";
            $eDose = $eDose + $tempdose;
            unset($tempdose);
        }
        echo "Dose-length Product: " . $extractarray[$key]['DLP'] . "<br>\n";
        echo "Mean CTDIvol: " . $extractarray[$key]['Mean CTDIvol'] . "<br>\n";
        echo "<br>\n";
    }
        if ($eDose > 0) {
            echo "Total estimated effective dose: $eDose mSv<br>\n";
        }
        echo "----------------------------------------------------------------<br>\n";

}

?>
<br>
<button class="btn" data-clipboard-action="copy" data-clipboard-target="div">Copy to clipboard</button>
<script src="./clipboard.js"></script>

<script>
    var clipboard = new Clipboard('.btn');

    clipboard.on('success', function(e) {
        console.log(e);
    });

    clipboard.on('error', function(e) {
        console.log(e);
    });
</script>

</body>
</html>

<!DOCTYPE HTML>
<html>
<head>
    <link rel="stylesheet" type="text/css" href="quantitative.css">
</head>
<body>

<?php

    // renal_analysis.php - a script to extract oncology analysis parameters
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
//echo "\n\n";
//print_r($extractarray);
//echo "</pre>\n";
//***************************
// Functions
//***************************

function movescu() {
    global $studyuid, $seriesuid, $objectuid, $myaetitle, $pacsaetitle, $pacstcpport, $pacsipaddress;
    $command = "movescu -v -aet $myaetitle -aec $pacsaetitle -aem $myaetitle -S -k 0008,0052=\"IMAGE\" -k 0020,000d=\"$studyuid\" -k 0020,000e=\"$seriesuid\" -k 0008,0018=\"$objectuid\"  $pacsipaddress $pacstcpport 2>&1";
    $movescu = shell_exec($command);
}

function dump_file() {
    global $studyuid, $objectuid, $dsrdump;
    $infile = "/usr/local/dcmtk/file_store/$studyuid/SRc.$objectuid";
    $command2 = "dsrdump +Pl -Ph +Pu $infile 2>&1";
    $dsrdump = shell_exec($command2);
}


function parse_dump() {
    global $dsrdump, $extractarray;
    $count = 0;
    $extractarray = array();
    $dsrarray = preg_split("/[\n|\r]/", $dsrdump);
    // First, clean up all the measurements, etc.
    //echo "<pre>\n"; //DEBUG
    //print_r($dsrarray); //DEBUG
    foreach ($dsrarray as $key => $value) {
        if ((preg_match("/Diameter/", $value)) > 0) {
            if ((preg_match("/Maximum/", $dsrarray[$key + 1])) > 0) {
                $dsrarray[$key] = str_replace("Diameter", "Diameter (max)", $dsrarray[$key]);
            }
            elseif ((preg_match("/Minimum/", $dsrarray[$key + 1])) > 0) {
                $dsrarray[$key] = str_replace("Diameter", "Diameter (min)", $dsrarray[$key]);
            }
        }
        if ((preg_match("/TEXT\:\(\,\,\"Summary\"\)/", $value)) > 0) {
            preg_match("/.+\"(.+?)\".+\"(.+?)\"/", $value, $extract);
            $extractarray["Summary"] = $extract[2];
            unset($dsrarray[$key]);
        }
        //$dsrarray[$key] = str_replace("Precision", "Analysis Precision", $dsrarray[$key]);
        $dsrarray[$key] = str_replace("Hounsfield unit", " HU", $dsrarray[$key]);
        $dsrarray[$key] = str_replace("cubic millimeter", " mm^3", $dsrarray[$key]);
        //$dsrarray[$key] = str_replace("Ratio", "Density Ratio (Low:High kV)", $dsrarray[$key]);
        $dsrarray[$key] = str_replace("millimeter", " mm", $dsrarray[$key]);
        $dsrarray[$key] = str_replace("no units", " ", $dsrarray[$key]);
        $dsrarray[$key] = str_replace("\"Volume", "\"Volume (est.) ", $dsrarray[$key]);
    }
    // Now, store everything into our array
        foreach ($dsrarray as $key => $value) {
        if ((preg_match("/\"Finding\"\)\=\(D7\-11061\,SNM\,\"Kidney Stone\"\)/", $value)) > 0) {
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
        unset($extractarray[$count]["Density"]);
        unset($extractarray[$count]["Derivation"]);
        unset($extractarray[$count]["Lesion Identifier"]);
        unset($extractarray[$count]["Best illustration of finding"]);
        //unset($extractarray[$count]["Finding Site"]);
        unset($extractarray[$count]["Type of Dual Energy generated Volume"]);
    }
    unset($extractarray[0]); //Get rid of stuff before 'Findings'
    //echo "<pre>\n"; //DEBUG
    //print_r($extractarray);  //DEBUG
    //echo "<</pre>\n";  //DEBUG


        //Stone Composition
        foreach ($extractarray as $key => $value) {
        //echo "RATIO # $key: " . $extractarray[$key]['Ratio'] . "\n"; //DEBUG
            if ($extractarray[$key]['Ratio'] <= 1.14) {
            $extractarray[$key]['Calculus Type'] = "Uric Acid Calculus";
            unset($extractarray[$key]['Ratio']); 
            }
            elseif ($extractarray[$key]['Ratio'] > 1.14 && $extractarray[$key]['Ratio'] <= 1.28 ) {
            $extractarray[$key]['Calculus Type'] = "Cystine Calculus";
            unset($extractarray[$key]['Ratio']);
            }
            elseif ($extractarray[$key]['Ratio'] > 1.28 && $extractarray[$key]['Ratio'] <= 1.385 ) {
            $extractarray[$key]['Calculus Type'] = "Calcium Oxalate";
            unset($extractarray[$key]['Ratio']);
            }
            else {
            $extractarray[$key]['Calculus Type'] = "Mixed Calcified Calculus";
            unset($extractarray[$key]['Ratio']);
            }
         }

        //Precision Rules
        foreach ($extractarray as $key => $value) {
            if ($extractarray[$key]['Precision'] == 'high' ) {
            $extractarray[$key]['Stone Characterization Confidence'] = "High ";
            unset($extractarray[$key]['Precision']);
            }
            elseif ($extractarray[$key]['Precision'] == 'medium' ) {
            $extractarray[$key]['Stone Characterization Confidence '] = "Medium ";
            unset($extractarray[$key]['Precision']);
            }
            elseif ($extractarray[$key]['Precision'] == 'low' ) {
            $extractarray[$key]['Stone Characterization Confidence '] = "Low - due to size of calculus";
            unset($extractarray[$key]['Precision']);
            }
         }


}

function print_data() {
    global $extractarray;
    //array_multisort($extractarray);

    echo "<div>\n";
    echo "QUANTITATIVE ANALYSIS<br>\n";
    echo "------------------------------------------------------<br>\n";
    if (array_key_exists('Summary', $extractarray)) {
        echo "Summary: " . $extractarray['Summary'] . "<br>\n<br>\n";
        unset($extractarray['Summary']);
    }
    foreach ($extractarray as $key => $value) {
        ksort($value);
        //$calc_num = $key+1;
        echo "Urinary tract calculus #$key<br>\n";
        foreach($value as $iKey => $iValue) {
            echo "$iKey: $iValue<br>\n";
        }
        echo "<br>\n";
    }
    echo "------------------------------------------------------<br>\n";
    echo "</div>\n";
    echo "Reference: Acharya S, Goyal A, Bhalla AS, Sharma R, Seth A, Gupta AK. In vivo characterization of urinary calculi on dual-energy CT: going a step ahead with sub-differentiation of calcium stones. Acta Radiologica. SAGE Publications. 2015;56:881-889.";
    echo "<br>\n";

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

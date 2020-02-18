<?php

    // sr_finder.php - a script that finds any DICOM SR files associated with the
    //   same study as the submitted accession number for extraction of quantitative data
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

//*************************************************************************************
// Declare our variables and other logic
//*************************************************************************************
require 'settings.php';
$array = array();
$output = array();

if (isset($_GET['accession'])) {
    $accession = $_GET['accession'];
}
else {
    die("No accession passed to script!\n");
}

putenv("DCMDICTPATH=/usr/share/dcmtk/private.dic:/usr/share/dcmtk/dicom.dic:");

//*************************************************************************************
// main()
//*************************************************************************************

findscu();
sort_output();
sort_clean();
print_table();
//print_debug(); //DEBUG
close_page();

//*************************************************************************************
// The functions
//*************************************************************************************
function findscu() {
    global $myaetitle, $pacsaetitle, $pacstcpport, $pacsipaddress, $accession, $output;

    $command = "findscu -aet $myaetitle -aec $pacsaetitle -S -k 0008,0052=\"IMAGE\" -k 0008,0023 -k 0008,0033 -k 0008,0020 -k 0010,0010 -k 0010,0020 -k 0010,0030 -k 0010,0040 -k 0020,000d -k 0020,0010 -k 0008,0050=\"$accession\" -k 0008,0030 -k 0008,1010 -k 0008,0080 -k 0008,0060=\"SR\" -k 0008,0090 -k 0008,103e -k 0020,000e -k 0020,0011 -k 0020,1206 -k 0020,1208 -k 0008,0018 -k 0008,1030 -k 0008,0016 $pacsipaddress $pacstcpport 2>&1";
    $dcmdump = shell_exec($command);
    //echo "<pre>$command</pre>\n"; //DEBUG
    $output = preg_split("/[\n|\r]/", $dcmdump);
}

function sort_output() {
	global $output, $array;
	// Turn the array into a well-sorted perl-esque hash (here, an array of arrays)
	foreach ($output as $key => $value) {
		if ((preg_match ("/^W\:\sFind\sResponse\:/", $value)) > 0) {
			preg_match ("/(\d+)/", $value, $response_array);
		}
		elseif ((preg_match ("/^W\:\s\(.+\).+\[.+\]/", $value)) > 0) {
			preg_match ("/\((.*?)\).+\[(.*?)\]/", $value, $extract);
			$array[$response_array[1]][$extract[1]] = $extract[2];
		}
		elseif ((preg_match ("/^W\:\s\(.+\).+\(.+\)/", $value)) > 0) {
			preg_match ("/\((.*?)\).+\((.*?)\)/", $value, $extract);
			$array[$response_array[1]][$extract[1]] = $extract[2];
		}
		elseif ((preg_match ("/^W\:\s\(0008,0016\).+/", $value)) > 0) {
			preg_match ("/\((.*?)\)\sUI\s(=\w+)\s+\#.+/", $value, $extract);
			$array[$response_array[1]][$extract[1]] = $extract[2];
		}
	}
}

function sort_clean() {
	global $array;
	// Now let's sort the array based on the time the study was performed
	// the strcmp() function below will sort the times in order as
	// $a comes before $b inside strcmp()
	function cmp($a, $b) {
		return strcmp($a["0008,0033"], $b["0008,0033"]);
	}
	usort($array, "cmp");

	// next we can clean up the dates, times, and names to be human-readable:
	foreach($array as $key => $value)  {
		foreach ($value as $iKey => $iValue) {
			// This is to put dashes into the exam dates
			if ((preg_match ("/0008\,0023/", $iKey)) > 0) {
				$iValue = substr_replace($iValue, '-', 4, 0);
				$iValue = substr_replace($iValue, '-', 7, 0);
				$array[$key][$iKey] = $iValue;
			}
			// This is to put colons into the times
			if ((preg_match ("/0008\,0033/", $iKey)) > 0) {
				$iValue = substr_replace($iValue, ':', 2, 0);
				$iValue = substr_replace($iValue, ' ', 5);
				$array[$key][$iKey] = $iValue;
			}
			// This is to clean up the name
			if ((preg_match ("/0010\,0010/", $iKey)) > 0) {
				$iValue = preg_replace("/\^/",",",$iValue,1);
				$iValue = preg_replace("/\^/"," ",$iValue,1);
                $iValue = str_replace("^", " ", $iValue);
				$array[$key][$iKey] = $iValue;
		    }
            // Clean up the SOP Class
            if ((preg_match ("/0008\,0016/", $iKey)) > 0) {
                $iValue = ltrim($iValue, "=");
                // This puts spaces in front of capital letters
                $iValue = preg_replace('/(?<!\ )[A-Z]/', ' $0', $iValue);
                $iValue = str_replace("S R", "SR", $iValue);
                $iValue = str_replace("X Ray", "X-Ray", $iValue);
				$array[$key][$iKey] = $iValue;
            }
		}
	}
}

function print_table()  {
	global $array;
    echo "<!DOCTYPE HTML>\n";
	echo "<html>\n";
    echo "<head>\n";
    echo "\t<link rel=\"stylesheet\" type=\"text/css\" href=\"quantitative.css\">\n";
    echo "</head>\n";

	echo "<body>\n";
    echo "<h2>Select a Structured Report for Analysis</h2>\n";
	echo "\t<table>\n";
	echo "\t\t<tr><th>Series Name</th><th>Accession</th><th>Creation<br>Date</th><th>Creation<br>Time</th><th>Station</th><th>Study Type</th><th>Patient Name</th><th>MRN</th><th>SOP Class</th><th>View As<br>SR</th></tr>\n";
	foreach($array as $key => $value)  {
        echo "\t\t<tr>";
        echo "<td>";
        if ($array[$key]['0008,103e'] == "Dose Report ") {
            echo "<a href=\"dose_analysis.php?studyUID=" . $array[$key]['0020,000d'] . "&seriesUID=" . $array[$key]['0020,000e'] . "&objectUID=" . $array[$key]['0008,0018'] . "\">" . $array[$key]['0008,103e'] . "</a>";
        }
        elseif ($array[$key]['0008,103e'] == "Evidence Documents CT Cardiac Function") {
            echo "<a href=\"cardiac_analysis.php?studyUID=" . $array[$key]['0020,000d'] . "&seriesUID=" . $array[$key]['0020,000e'] . "&objectUID=" . $array[$key]['0008,0018'] . "\">" . $array[$key]['0008,103e'] . "</a>";
        }
        elseif ($array[$key]['0008,103e'] == "Evidence Documents CT CaScoring ") {
            echo "<a href=\"cardiac_cascore.php?studyUID=" . $array[$key]['0020,000d'] . "&seriesUID=" . $array[$key]['0020,000e'] . "&objectUID=" . $array[$key]['0008,0018'] . "\">" . $array[$key]['0008,103e'] . "</a>";
        }
        elseif ($array[$key]['0008,103e'] == "Evidence Documents CT Colon Reading ") {
            echo "<a href=\"colon_analysis.php?studyUID=" . $array[$key]['0020,000d'] . "&seriesUID=" . $array[$key]['0020,000e'] . "&objectUID=" . $array[$key]['0008,0018'] . "\">" . $array[$key]['0008,103e'] . "</a>";
        }
        elseif ($array[$key]['0008,103e'] == "Evidence Documents CT Dual Energy ") {
            echo "<a href=\"renal_analysis.php?studyUID=" . $array[$key]['0020,000d'] . "&seriesUID=" . $array[$key]['0020,000e'] . "&objectUID=" . $array[$key]['0008,0018'] . "\">" . $array[$key]['0008,103e'] . "</a>";
        }
        elseif ($array[$key]['0008,103e'] == "Evidence Documents MM Oncology Reading") {
            echo "<a href=\"oncology_analysis.php?studyUID=" . $array[$key]['0020,000d'] . "&seriesUID=" . $array[$key]['0020,000e'] . "&objectUID=" . $array[$key]['0008,0018'] . "\">" . $array[$key]['0008,103e'] . "</a>";
        }
        else {
            echo "<a href=\"sr_convert.php?studyUID=" . $array[$key]['0020,000d'] . "&seriesUID=" . $array[$key]['0020,000e'] . "&objectUID=" . $array[$key]['0008,0018'] . "\">" . $array[$key]['0008,103e'] . "</a>";
        }
        echo "</td>";
        echo "<td>" . $array[$key]['0008,0050'] . "</td>";
        echo "<td nowrap>" . $array[$key]['0008,0023'] . "</td>";
        echo "<td align=center>" . $array[$key]['0008,0033'] . "</td>";
        echo "<td>" . $array[$key]['0008,1010'] . "</td>";
        echo "<td>" . $array[$key]['0008,1030'] . "</td>";
        echo "<td>" . $array[$key]['0010,0010'] . "</td>";
        echo "<td>" . $array[$key]['0010,0020'] . "</td>";
        echo "<td>" . $array[$key]['0008,0016'] . "</td>";
        echo "<td align=center><a href=\"sr_convert.php?studyUID=" . $array[$key]['0020,000d'] . "&seriesUID=" . $array[$key]['0020,000e'] . "&objectUID=" . $array[$key]['0008,0018'] . "\">View</a>";
        echo "</tr>\n";
    }
	echo "\t</table>\n";
}

function print_debug() {
    global $array, $output;
    echo "<pre>\n";
    echo "Raw Data:\n";
    print_r($array);
    echo "\n\n";
    print_r($output);
    echo "</pre>\n";
}

function close_page() {
	echo "</body>\n";
	echo "</html>\n";
}
?>

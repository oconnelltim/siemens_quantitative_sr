<?php

    // sr_convert.php - a script that is called by the `sr_finder.php` script
    //   to retrieve and convert DICOM SR files to html format using the OFFIS dcmtk
	//   dsr2html utility
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

$studyuid = $_GET['studyUID'];
$seriesuid = $_GET['seriesUID'];
$objectuid = $_GET['objectUID'];
$infile = "/usr/local/dcmtk/file_store/" . $studyuid . "/SR*." . $objectuid;
$indir = "/usr/local/dcmtk/file_store/" . $studyuid . "/";

$command = "movescu -aet $myaetitle -aec $pacsaetitle -aem $myaetitle -S -k 0008,0052=\"IMAGE\" -k 0020,000d=\"$studyuid\" -k 0020,000e=\"$seriesuid\" -k 0008,0018=\"$objectuid\" $pacsipaddress $pacstcpport 2>&1";
$movescu = shell_exec($command);

$dump_command = "dsr2html -Ee -Er -Ec +Rd +Sr report.css " . $infile;
$sr_html = shell_exec($dump_command);

print $sr_html;
?>




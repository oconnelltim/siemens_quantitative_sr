# siemens_quantitative_sr
## Introduction
NB: there is an important warning at the end of this file - you have to read this whole document prior to using these scripts. 

This is a collection of PHP scripts intended for use in a radiology department, by radiologists who are using Siemens Syngo.via as a post-processing platform.  These scripts can be called in-context from a PACS client, and will retrieve any DICOM structured reports (SR) related to the patient you're viewing, and parse out the quantitative data from the SR and pop open a webpage where you can copy/paste the quantitative data into your final report.

## Prerequisites
In order to use these scripts, some additional pieces of software will be required, and either you or someone who can help you is going to have to know something about PACS systems, computers, and software.  The software prerequisites are:

1. A server that you can run this on.  This server is going to be performing DICOM queries against your PACS server, so you'll need someone with admin access to the PACS server you want to access (e.g. a PACS admin) to set up an AE title for your server in PACS and allow access to it to both perform C-FIND and C-MOVE actions. 
2. A working Apache (or other webserver) and PHP installation on your server.  These scripts are written in PHP and need to be served to the end user by some kind of a webserver that can work with PHP.
3. A working installation of dcmtk, the DICOM toolkit from OFFIS (https://dicom.offis.de/dcmtk.php.en). On the server, PHP needs to be able to execute two of the dcmtk programs, `movescu` and `dsrdump` from the command line, so you can either have them accessable via the path or put them in the same directory as the scripts.  These scripts were originally set up on a server that was running the dcmtk `storescp` utility as a daemon and it would store any received studies in the `/usr/local/dcmtk/file_store/` directory. 
4. clipboard.js (optional) - the scripts include the ability to one-click copy the extracted data to the clipboard via a javascript script called `clipboard.js` which you can download here: https://clipboardjs.com. The scripts just expect it to be in the same directory as the scripts themeselves. 
5. Someone who can help you integrate calling these scripts into your PACS client.  

## Installation
Explaining how to set up an AE title on your PACS server, install apache, PHP, dcmtk, and clipboard.js, and set up scripts in your PACS client to call these scripts are beyond the scope of this document.  You may need to get help from multiple people with this. 

But after you're done all that, installation involves creating a directory on your webserver, and putting these scripts in there.

Once you've done that, you'll need to edit the `settings.php` file - it is where you put in the `AETITLE` of your server, as well as the `AETITLE`, `IP Address`, and `TCP Port` of the PACS server you're getting the files from. 

In the scripts, edit the line that starts with `putenv("DCMDICTPATH...` to have the appropriate path for whatever the appropriate value is for the dcmtk installation on your webserver.

One thing you're going to need to customize with the scripts is where the retrieved SR files get stored.  You can just use `movescu` as it's written in the scripts which will retrieve and try to write studies into the same directory as the scripts (although permissions may be difficult for this), but it is a better solution to properly set up `storescu` from dcmtk as a daemon on your webserver which will be listening for incoming files and store them in a directory.  All of the scripts use a parameter of `$infile = "/usr/local/dcmtk/file_store/$studyuid/...";` for where they should expect the files to be after they're retrieved - you're going to have to edit this line to be wherever the file can be found after the PACS server sends it to your server.

Finally, copy the `report.css` file from dcmtk that contains the pretty formatting for the `dsr2html` utility into the same directory that contains these scripts on your webserver. 

## Warning / Note
A very serious note of caution - these scripts were written to work on Agfa Impax 6.5 as the PACS server and with a version of Syngo.via from 2016 or thereabouts (VB40?).  If you're not using Impax 6.5 (and even if you are), your PACS server will likely require different options as part of the `movescu` function in order to safely retrieve files.  As well, you may certainly need to tweak the regular expressions in the `parse_dump` functions in order to properly extract the data from the SRs if Siemens has changed their format any, or if OFFIS has changed how `dsrdump` outputs its data. 

Why is this a warning?  Using DICOM tools to retrieve data from PACS servers can be dangerous if you don't know what you're doing.  If you submit an unrestrained (or other inappropriate) query, and your PACS administrator hasn't put controls in place to limit the amount of data returned in a query, you could crash your PACS server, which could cause very serious issues for your radiology department and its patients.

You have to test these scripts out on a test server (or in a safe, supervised environment) prior to deploying to production. If you don't understand PACS, DICOM, and PHP code, then you shouldn't be trying to use these.  Use these files at your own risk. 

## How these scripts are supposed to work
Ideally, if you can integrate these with your PACS client, you can just have a button in your PACS client (e.g. it might say 'quantitative' on it), and when you click it, it opens a web browser to the `sr_finder.php` URL (wherever you've put it), and passes the accession number of the study that you're looking at to the script via an `HTML GET` variable in the URL.  e.g. if the webserver you're running the scripts on has an IP address like 10.0.0.10, and you put the scripts in a directory on your webserver called `quantitative_sr`, then the URL would be http://10.0.0.10/quantitative_sr/sr_finder.php?accession=xxxxxxxx , where `xxxxxxx` is the accession number of the study you're looking at.

The `sr_finder.php` script will then show you a list of any DICOM SRs that Syngo.via has produced (or any RDSR or any other SR with an accession # from the study you're looking at), and if you want to then view the quantiative data from that SR in a nicely-formatted way, you can click on the associated button, and it will retrieve that SR and nicely format the data for copying/pasting into your final report. 

If the SR is not a Syngo.via quantitative-data SR, or a DICOM RDSR (radiation dose SR), then the `sr_finder.php` page gives you the option to just view the SR as a nicely-formatted html page using the `sr_convert.php` script which uses the dcmtk `dsr2html` utility. 

## Supported Syngo.via applications
The scripts will extract data from the following applications / analysis pathways:
* Siemens RDSRs (maybe other vendor RDSRs)
* Dual-energy Renal Stone Analysis
* Coronary Artery Calcium Scoring
* Coronary Artery CT Functional Analysis
* CT Colonography Analysis
* MM Oncology Analysis

Please note that many of these scripts were still in development when development on this project stopped, and as such they are buggy and/or incomplete.  The most fully developed scripts were the `dose_analysis.php` and the `renal_analysis.php` scripts. 

## Using these scripts without Syngo.via
Yes! You can use this software even if you don't have syngo.via.  The scripts *should* find any SR files related to the accession you pass them, and at least give you the option to extract data from RDSR files, or at least view the SR in a pretty-formatted html page.

## Acknowledgement
A big thanks goes out to Dr. Mohammed Mohammed, whose enthusiasm and help was greatly appreciated in making these scripts.  

## License and Warranty
The scripts are all licensed under the GPL v3, which is included in this repo as `LICENSE.md`.  These programs are distributed in the hope that they will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more details.

<?php
/***************************************************************
  Getfile 1.4
  Copyright (c) 2007-10 Gowon Patterson - All Rights Reserved
  this script is licensed under the Open Software License 3.0
  http://www.opensource.org/licenses/osl-3.0.php
***************************************************************/

// Download directory where downloads are located. MUST end with a trailing slash ( "/" )
define('BASE_DIR','/www/user/downloads/');

define('ENCRYPT_FILENAME',TRUE); // use encrypted filenames?  true/false

define('HOTLINK_PROTECTION',TRUE); // enable hotlinking?  true/false

define('HOTLINK_PAGE_URL','http://www.mysite.com'); // Hotlink URL

//Allowed domains separated by commas (HTTP_HOST allowed by default), DO NOT include "http://", 
//Asterisk (*) is a wildcard (to easily include all names in a given set)
$allowed_domains="*mysite.com, www.myaffiliate.net";

define('LOG_DOWNLOADS',TRUE); // log downloads?  true/false

define('LOG_FILE','downloads.txt'); // log file name
define('LOG_LIST_NUM',200); // number of items shown in log view

define('USERNAME','admin'); //Set password used to view log
define('PASSWORD','pass'); //Set password used to view log

define('LOG_CSS','
body { background-color: #fff; color: #000; font-family: "Trebuchet MS", sans-serif; }
table { width: 100%; color: #212424; margin: 0 0 1em 0; font: 80%/150% "Lucida Grande", "Lucida Sans Unicode", "Lucida Sans", Lucida, Helvetica, sans-serif; }
table, tr, th, td { margin: 0; padding: 0; border-spacing: 0; border-collapse: collapse; }
thead { background-color: #000; }
thead tr th { padding: 1em 0; text-align: center; color: #FAF7D4; border-bottom: 3px solid #999; }
tbody tr td { background-color: #eee; }
tbody tr.odd td { background-color: #ddd; }
tbody tr th, tbody tr td { padding: 0.1em 0.4em; border: 1px solid #999; }
tbody tr th { padding-right: 1em; text-align: right;  font-weight: normal; background-color: #aaa; text-transform: uppercase; }
tbody tr th:hover { background-color: #ddd; }
tbody tr:hover td { background: #ccc; color: #000; }
table a { color: #854400; text-decoration: none; }
table a:visited { text-decoration: line-through; }
table a:hover { text-decoration: underline; }
th.asc{ background-image: url(../images/asc.gif); }
th.des{ background-image: url(../images/des.gif); }
');

// Allowed extensions list: 'extension' => 'mime type'
$allowed_ext = array (
  // archives
  'zip' => 'application/zip',

  // documents
  'pdf' => 'application/pdf',
  'doc' => 'application/msword',
  'xls' => 'application/vnd.ms-excel',
  'ppt' => 'application/vnd.ms-powerpoint',
  
  // executables
  'exe' => 'application/octet-stream',

  // images
  'gif' => 'image/gif',
  'png' => 'image/png',
  'jpg' => 'image/jpeg',
  'jpeg' => 'image/jpeg',

  // audio
  'mp3' => 'audio/mpeg',
  'wav' => 'audio/x-wav',

  // video
  'mpeg' => 'video/mpeg',
  'mpg' => 'video/mpeg',
  'mpe' => 'video/mpeg',
  'mov' => 'video/quicktime',
  'avi' => 'video/x-msvideo'
);

///////////////////////////////////
// DO NOT EDIT BEYOND THIS POINT //
///////////////////////////////////

#checks the referer of the script
function getReferer() { preg_match('@^(?:http://)?([^/]+)@i',$_SERVER['HTTP_REFERER'], $match); return $match[1]; }

#Takes all data in flat-file, turns into multidimensional array
function read_flatfile($filename,$delimiter="\t") {
$fd=fopen($filename,'r'); if (!$fd) return FALSE;
while (!feof($fd)) {
$line=fgets($fd); $values=explode($delimiter, $line); $linearray=array();
foreach ($values as $Value) { $linearray[] = $Value; }
$data[]=$linearray;
}

fclose($fd); return $data;
}

#checks if referer domain is okay
function hotlink_check() {
global $allowed_domains; $allowed_domains.=','.$_SERVER['HTTP_HOST'];
$domains=explode(',',str_replace(' ','',$allowed_domains));
$referer=getReferer(); $site=array();
foreach ($domains as $value) { $site[]='^'.str_replace('*','([0-9a-zA-Z]|\-|\_)+',str_replace('.','\.',$value)).'$'; }
foreach ($site as $pattern) { if(eregi($pattern,$referer)) $MATCH=TRUE; if($MATCH==TRUE) break; }
if($MATCH==TRUE) return TRUE; else return FALSE;
}

// Check if the file exists, Check in subfolders too
function find_file ($dirname, $fname, &$file_path) {
$dir = opendir($dirname) or die("Cannot open directory: $dirname");
 while (false !== ($file = readdir($dir))) {
 //echo $file.'|'.md5($file);
  if (empty($file_path) && $file != '.' && $file != '..') {

   if (is_dir($dirname.'/'.$file)) { find_file($dirname.'/'.$file, $fname, $file_path); } else {
    if (ENCRPYT_FILENAME && md5($file) == $fname ) { $file_path = $dirname.'/'.$file; return; }
    elseif (file_exists($dirname.'/'.$fname)) { $file_path = $dirname.'/'.$fname; return; } 
   }
  }
 }
} // find_file

define('HOTLINK_PASS',hotlink_check());
if(HOTLINK_PROTECTION&&!HOTLINK_PASS&&$_SERVER['QUERY_STRING']!='admin') { header('HTTP/1.1 403 Forbidden'); header('Location: '.HOTLINK_PAGE_URL); die(); }

$DL=explode("/",$_SERVER['QUERY_STRING']);
set_time_limit(0); // max script execution time (0 = no limit)

if ($DL[0]!='admin') {
$fname=str_replace("%20"," ",basename($DL[0])); // Remove any path info

$file_path = ''; find_file(BASE_DIR, $fname, $file_path); // get full file path (including subfolders)
if (!isset($DL[0]) || empty($DL[0]) || !is_file($file_path)) die("\nFile could not be found. Make sure you specified the correct file name.");
$fsize = filesize($file_path); // get file size in bytes 
$fname = basename($file_path);
$fext = strtolower(substr(strrchr($fname,"."),1)); // get file extension

// check if allowed extension
if (!array_key_exists($fext, $allowed_ext)) die("$fext This file type is not allowed.");

// get mime type
if ($allowed_ext[$fext] == '') {
  $mtype = ''; // mime type is not set, get from server settings
  if (function_exists('mime_content_type')) $mtype = mime_content_type($file_path);
  else if (function_exists('finfo_file')) {
    $finfo = finfo_open(FILEINFO_MIME); // return mime type
    $mtype = finfo_file($finfo, $file_path);
    finfo_close($finfo);  
  }
  if ($mtype=='') $mtype="application/octet-stream";

} else $mtype = $allowed_ext[$fext]; // get mime type defined by admin
$asfname=(!isset($DL[1])||empty($DL[1])) ? $fname:$DL[1]; //Save as if used

// set headers
header("Pragma: public");
header("Expires: 0");
header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
header("Cache-Control: public");
header("Content-Description: File Transfer");
header("Content-Type: $mtype");
header("Content-Disposition: attachment; filename=\"$asfname\"");
header("Content-Transfer-Encoding: binary");
header("Content-Length: ".$fsize);
ob_end_flush();
@readfile($file_path);

if (!LOG_DOWNLOADS) die(); $f=@fopen(LOG_FILE, 'a+'); // log downloads
if ($f) { @fputs($f, date("Y-m-d\t H:i:s")."\t".$_SERVER['REMOTE_ADDR']."\t".$_SERVER['HTTP_REFERER']."\t".$fname."\r\n"); @fclose($f); }
} else { //Logview

$user = $_SERVER['PHP_AUTH_USER'];
$pass = $_SERVER['PHP_AUTH_PW'];
$validated = (USERNAME == $user) && (PASSWORD == $pass);

if (!$validated) {
  header('WWW-Authenticate: Basic realm="Getfile 1.4"');
  header('HTTP/1.0 401 Unauthorized');
  die ("You do not have access to this area.");
}



if (isset($_POST['ExportLog'])) {
// Export Log
header("Pragma: public");
header("Expires: 0");
header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
header("Cache-Control: public");
header("Content-Description: File Transfer");
header("Content-Type: text/plain");
header("Content-Disposition: attachment; filename=\"log_".date("Y_m_d").".txt\"");
header("Content-Transfer-Encoding: binary");
header("Content-Length: ".filesize(LOG_FILE));
ob_end_flush();
@readfile(LOG_FILE);
die();

}

elseif (isset($_POST['ClearLog'])) @unlink(LOG_FILE);

$data=@read_flatfile(LOG_FILE); $date=$data[0][0];
$count=count($data) - 1; unset($data[$count]);
echo "<html>\n<head>\n<title>Getfile 1.4</title>\n<style>".LOG_CSS."</style>\n<script>\n";

echo <<< END
function sortableTable(tableIDx,intDef,sortProps){

  var tableID = tableIDx;
  var intCol = 0;
  var intDir = -1;
  var strMethod;
  var arrHead = null;
  var arrMethods = sortProps.split(",");

  this.init = function(){
    arrHead = document.getElementById(tableID).getElementsByTagName('thead')[0].getElementsByTagName('th');
    for(var i=0;i<arrHead.length;i++){
	  arrHead[i].onclick = new Function(tableIDx + ".sortTable(" + i + ",'" + arrMethods[i] + "');");
    }
    this.sortTable(intDef,arrMethods[intDef]);
  }

  this.sortTable = function(intColx,strMethodx){ 

    intCol = intColx;
	strMethod = strMethodx;

	var arrRows = document.getElementById(tableID).getElementsByTagName('tbody')[0].getElementsByTagName('tr');

    intDir = (arrHead[intCol].className=="asc")?-1:1;
    arrHead[intCol].className = (arrHead[intCol].className=="asc")?"des":"asc";
	for(var i=0;i<arrHead.length;i++){
      if(i!=intCol){arrHead[i].className="";}
	}
	  
	var arrRowsSort = new Array(); 
	for(var i=0;i<arrRows.length;i++){ 
      arrRowsSort[i]=arrRows[i].cloneNode(true); 
    }
    arrRowsSort.sort(sort2dFnc);
	      
	for(var i=0;i<arrRows.length;i++){   
	  arrRows[i].parentNode.replaceChild(arrRowsSort[i],arrRows[i]);
      arrRows[i].className = (i%2==0)?"":"alt";
	} 

  } 

  function sort2dFnc(a,b){
    var col = intCol;
    var dir = intDir;
    var aCell = a.getElementsByTagName("td")[col].innerHTML;
    var bCell = b.getElementsByTagName("td")[col].innerHTML;
	   
    switch (strMethod){
    case "int":
      aCell = parseInt(aCell);
      bCell = parseInt(bCell);			 
	  break;
	case "float":
      aCell = parseFloat(aCell);
      bCell = parseFloat(bCell);			 		   
	  break;
	case "date":
      aCell = new Date(aCell);
      bCell = new Date(bCell);
	  break;	   
	}
    return (aCell>bCell)?dir:(aCell<bCell)?-dir:0;
  }
}
      var t1 = new sortableTable("t1",0,"int,date,float,float,str,str");
        window.onload = function(){
        t1.init();
      }
END;

echo "\n</script>\n</head><body>\n\n<p>Log Start Date: $date<br />Total Downloads: $count<br />Download Path: ".BASE_DIR."<br />";
echo 'Encypted Filenames: <strong>'.((ENCRYPT_FILENAME) ? "ENABLED":"DISABLED").'</strong><br />';
echo 'Hotlink Protection: <strong>'.((HOTLINK_PROTECTION) ? "ENABLED":"DISABLED").'</strong><br />';
echo 'Log Downloads: <strong>'.((LOG_DOWNLOADS) ? "ENABLED":"DISABLED").'</strong><br /></p>';

echo '<form action="?admin" method="POST"><p>';
echo '<input type="input" name="filename" value="Filename to encrypt (ex. `docs.txt`)" onfocus="this.value='';" class="noprint" />&nbsp;<input type="submit" name="EncryptName" value="Encrpyt Filename" class="noprint" />';
if (isset($_POST['EncryptName'])) echo '&nbsp;Code for <strong>&quot;'.$_POST['filename'].'&quot;</strong>: <strong>'.md5($_POST['filename']).'</strong>';
if (is_file(LOG_FILE)) echo '<br /><input type="submit" name="ExportLog" value="Export Log" class="noprint" /><input type="submit" name="ClearLog" value="Clear Log" class="noprint" />';
echo '</p></form>';

echo "\n\n<table id=\"t1\"><thead><tr><th>##</th><th>Date</th><th>Time</th><th>IP Address</th><th>Referer</th><th>File Downloaded</th></tr></thead>\n\n<tbody>";
for ($i=($count - 1); isset($data[$i]); $i--) {
if ($data[$i][0]!='') {
if ($i==($count - 1 - LOG_LIST_NUM)) break;
echo "<tr><td>".($i+1)."</td>";
for ($g=0; isset($data[$i][$g]); $g++) { echo "<td>"; if ($g==3) echo "<a href=\"".$data[$i][$g]."\">".$data[$i][$g]."</a>"; else echo $data[$i][$g]; echo "</td>"; }
echo "</tr>\n\n";
}

}

echo "</tbody></table>\n\n</body>\n</html>";
} //End Logview

?>

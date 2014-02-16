<?php
/**
 * Getter version 0.1
 * Version: 0.1
 * Last Updated: Feb 16, 2014
 * Copyright: 2014 Gowon Patterson, Gowon Designs
 * License: GNU General Public License v2 <http://www.gnu.org/licenses/gpl-2.0.html>
 * @package Getter
 */
namespace Getter;

/**
 * Configuration
 * Class containing all of the editable parameters of the Getter program
 * @package Getter\Configuration
 */
class Configuration {
    const BASE_DIRECTORY = '/www/user/downloads/';
    const HOTLINK_PROTECTION = true;
    const HOTLINK_REDIRECT_URL = null; // if set to null, will simply generate 403 Forbidden Error.
    const LOG_DOWNLOADS = true;
    const LOG_FILENAME = '.getter';
    const PANEL_ON = true;
    const PANEL_TOKEN = 'admin';
    const PANEL_USERNAME = 'admin';
    const PANEL_PASSWORD = 'root';
    const PANEL_ITEMS_MAX_NUM = 200;
    const PANEL_CSS = <<< CSS
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
CSS;

    // Common mime types to properly deliver files
    public static $MIME_TYPES = array (
        'zip' => 'application/zip',
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'xls' => 'application/vnd.ms-excel',
        'ppt' => 'application/vnd.ms-powerpoint',
        'exe' => 'application/octet-stream',
        'gif' => 'image/gif',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'mp3' => 'audio/mpeg',
        'wav' => 'audio/x-wav',
        'mpeg' => 'video/mpeg',
        'mpg' => 'video/mpeg',
        'mpe' => 'video/mpeg',
        'mov' => 'video/quicktime',
        'avi' => 'video/x-msvideo'
    );

    public static $ALLOWED_DOMAINS = array(
        '*mysite.com',
        'www.myaffiliate.net'
    );
}

/**
 * Base
 * Main class containing Getter URI logic and methods
 * @package Getter\Base
 */
class Base {
    /**
     * Chunk size in bytes. Default 0.5mb = 52633 = 1028 * 512
     */
    const CHUNK_SIZE = 52633;

    /**
     * URL Query String
     * @var string $uri
     */
    private static $uri;

    /**
     * Initiate Getter program
     */
    public static function Start() {
        error_reporting(0);
        apache_setenv('no-gzip', 1);
        ini_set('zlib.output_compression', 'Off');

        //must sanitize uri before processing it
        self::$uri = preg_replace('/[^a-zA-Z0-9.%//]/', '', $_SERVER['QUERY_STRING']);


        if (Configuration::PANEL_ON && self::$uri == Configuration::PANEL_TOKEN) {
            self::Panel();
            exit;
        }

        Configuration::$ALLOWED_DOMAINS[] = $_SERVER['HTTP_HOST'];
        $referrer = preg_match('@^(?:http://)?([^/]+)@i',$_SERVER['HTTP_REFERER'], $match)[1];
        $isValidReferrer = false;

        foreach (Configuration::$ALLOWED_DOMAINS as $domain) {
            $pattern = '^' . str_replace('*', '([0-9a-zA-Z]|\-|\_)+', str_replace('.','\.',$domain)) . '$';
            if (preg_match($pattern, $referrer)) {
                $isValidReferrer = true;
                break;
            }
        }

        // If Referrer isn't on domain whitelist, reject
        if(Configuration::HOTLINK_PROTECTION && !$isValidReferrer) {
            header('HTTP/1.1 403 Forbidden');
            if (Configuration::HOTLINK_REDIRECT_URL !== null) {
                header('Location: ' . Configuration::HOTLINK_REDIRECT_URL);
            }
            exit;
        }

        self::Download();
    }

    /**
     * Serve file to client
     */
    private static function Download() {
        $uri = explode('/', self::$uri);
        $path = null;

        if (empty($uri)) {
            header("HTTP/1.0 400 Bad Request");
            exit;
        }

        $filename = str_replace('%20', ' ', basename($uri[0]));
        self::GetFilePath(Configuration::BASE_DIRECTORY, $filename, $path);

        if (!is_file($path)) {
            header('HTTP/1.1 404 Not Found');
            exit;
        }

        $size = filesize($path);
        $filename = basename($path);
        $extension = strtolower(substr(strrchr($filename, "."), 1));
        $mimeType = isset(Configuration::$MIME_TYPES[$extension]) ? Configuration::$MIME_TYPES[$extension] : 'application/octet-stream';

        try {
            if (Configuration::LOG_DOWNLOADS) {
                $f = fopen(Configuration::LOG_FILENAME, 'a+');
                fputs($f,sprintf("%s,%s,%s,%s,\r\n",
                    date("Y-m-d,H:i:s"),
                    $_SERVER['REMOTE_ADDR'],
                    $_SERVER['HTTP_REFERER'],
                    $filename));
                fclose($f);
            }
        } catch (\Exception $e) {
            //Send error to std error log
            error_log('Getter was unable to access log file: ' . Configuration::LOG_FILENAME, 0);
        }

        $file = fopen($path,"rb");
        if ($file === false) {
            header("HTTP/1.0 500 Internal Server Error");
            exit;
        }

        // set the headers, prevent caching
        header("Pragma: public");
        header("Expires: -1");
        header("Cache-Control: public, must-revalidate, post-check=0, pre-check=0");
        header('Content-Disposition: attachment; filename="' . (empty($uri[1]) ? $filename : $uri[1]) . '"');
        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . $size);
        header('Accept-Ranges: bytes');


        $range = null;
        $seekStart = 0;
        //check if http_range is sent by browser (or download manager)
        if(isset($_SERVER['HTTP_RANGE']))
        {
            list($sizeUnit, $range_orig) = explode('=', $_SERVER['HTTP_RANGE'], 2);
            if ($sizeUnit == 'bytes')
            {
                //multiple ranges could be specified at the same time, but for simplicity only serve the first range
                //http://tools.ietf.org/id/draft-ietf-http-range-retrieval-00.txt
                list($range, $extra_ranges) = explode(',', $range_orig, 2);
            }
            else {
                header('HTTP/1.1 416 Requested Range Not Satisfiable');
                exit;
            }

            //figure out download piece from range (if set)
            list($seekStart, $seekEnd) = explode('-', $range, 2);

            //set start and end based on range (if set), else set defaults
            //also check for invalid ranges.
            $seekEnd   = (empty($seekEnd)) ? ($size - 1) : min(abs(intval($seekEnd)), ($size - 1));
            $seekStart = (empty($seekStart) || $seekEnd < abs(intval($seekStart))) ? 0 : max(abs(intval($seekStart)), 0);

            //Only send partial content header if downloading a piece of the file (IE workaround)
            if ($seekStart > 0 || $seekEnd < ($size - 1))
            {
                header('HTTP/1.1 206 Partial Content');
                header('Content-Range: bytes ' . $seekStart . '-' . $seekEnd . '/' . $size);
                header('Content-Length: ' . ($seekEnd - $seekStart + 1));
            }
        }

        set_time_limit(0); // Disable time limit while serving files
        fseek($file, $seekStart);
        while (!feof($file)) {
            echo fread($file, self::CHUNK_SIZE);
            ob_flush();
            flush();
            // Stop sending if the connection has been aborted or timed out
            if (connection_status() !== CONNECTION_NORMAL) {
                break;
            }
        }

        fclose($file);
        exit;

        /*
        ob_end_clean(); //ob_end_flush();
        readfile($path);
        //*/
    }

    /**
     * Load Web Panel to manage download lot
     */
    private static function Panel() {
        if (Configuration::PANEL_USERNAME != $_SERVER['PHP_AUTH_USER'] || Configuration::PANEL_PASSWORD != $_SERVER['PHP_AUTH_PW']) {
            header('WWW-Authenticate: Basic realm="Getter Control Panel"');
            header('HTTP/1.0 401 Unauthorized');
            exit;
        }

        // Export Log
        if (isset($_POST['ExportLog'])) {
            header('Pragma: public');
            header('Expires: 0');
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            header('Cache-Control: public');
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="getter-log-' . date('YmdHis') . '.csv');
            header('Content-Length: ' . filesize(Configuration::LOG_FILENAME));
            //ob_end_flush();
            readfile(Configuration::LOG_FILENAME);
            exit;
        }

        if (isset($_POST['ClearLog'])) {
            unlink(Configuration::LOG_FILENAME);
        }

        $html = <<< HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Getter Control Panel</title>
    <script type="text/javascript">
    function SortableTable(tableIDx,intDef,sortProps){

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
        };

        this.sortTable = function(intColx,strMethodx){
            intCol = intColx;
            strMethod = strMethodx;

            var arrRows = document.getElementById(tableID).getElementsByTagName('tbody')[0].getElementsByTagName('tr');

            intDir = (arrHead[intCol].className=="asc")?-1:1;
            arrHead[intCol].className = (arrHead[intCol].className=="asc")?"des":"asc";
            for(var i=0;i<arrHead.length;i++){
              if(i!=intCol){arrHead[i].className="";}
            }

            var arrRowsSort = [];
            for(var i=0;i<arrRows.length;i++){
              arrRowsSort[i]=arrRows[i].cloneNode(true);
            }
            arrRowsSort.sort(sort2dFnc);

            for(var i=0;i<arrRows.length;i++){
              arrRows[i].parentNode.replaceChild(arrRowsSort[i],arrRows[i]);
              arrRows[i].className = (i%2==0)?"":"alt";
            }
        };

        function sort2dFnc(a,b){
            var aCell = a.getElementsByTagName("td")[intCol].innerHTML;
            var bCell = b.getElementsByTagName("td")[intCol].innerHTML;

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
            return (aCell>bCell)?intDir:(aCell<bCell)?-intDir:0;
        }
    }
    var t1 = new SortableTable("log-table",0,"int,date,float,float,str,str");
    window.onload = function(){
        t1.init();
    }
    </script>
HTML;

        $html .= "\t<style>\n" . Configuration::PANEL_CSS . "\n\t</style>";
        $html .= '</head>
<body>
    <h1>Getter Control Panel</h1>
    <form action="?' . Configuration::PANEL_TOKEN . '" method="post">
        <p>
            Download Path: <strong>' . Configuration::BASE_DIRECTORY . '</strong><br />
            Hotlink Protection: <strong>' . (Configuration::HOTLINK_PROTECTION ? 'ENABLED': 'DISABLED') . '</strong><br />
            Log Downloads: <strong>' . (Configuration::LOG_DOWNLOADS ? 'ENABLED': 'DISABLED') . '</strong><br />

            <label for="filename">Encryption Tool: </label><input type="input" name="filename" id="filename" placeholder="Type in filename to encrypt (ex. \'docs.txt\')" /><input type="submit" name="EncryptName" value="Generate Hash"/>';

        if (isset($_POST['EncryptName'])) $html .= '<br />Code for <strong>&quot;'.$_POST['filename'].'&quot;</strong>: <strong>'.md5($_POST['filename']).'</strong>';

        if (is_file(Configuration::LOG_FILENAME)) {
            $data = self::ParseCsv(Configuration::LOG_FILENAME);
            $date = $data[0][0];
            $count = count($data);
            //$count = count($data) - 1;
            //unset($data[$count]);

            $html .= '<br /><input type="submit" name="ExportLog" value="Export Log" /><input type="submit" name="ClearLog" value="Clear Log" />
            </p></form>

            <h2>Download Log</h2>
            Log Start Date: <strong>' . $date . '</strong><br />
            Total Downloads: <strong>' . $count . '</strong><br />
            <table id="log-table"><thead><tr><th>##</th><th>Date</th><th>Time</th><th>IP Address</th><th>Referrer</th><th>File Downloaded</th></tr></thead>\n\n<tbody>';

            for ($i = ($count - 1); isset($data[$i]); $i--) {
                if ($data[$i][0] != '') {
                    if ($i == ($count - 1 - Configuration::PANEL_ITEMS_MAX_NUM)) break;
                    $html .= "<tr><td>" . ($i+1) . "</td>";
                    for ($j=0; isset($data[$i][$j]); $j++) {
                        $html .= "<td>";
                        $html .= ($j == 3) ? "<a href=\"" . $data[$i][$j] . "\">" . $data[$i][$j] . "</a>": $data[$i][$j];
                        $html .= "</td>";
                    }
                    $html .= "</tr>\n\n";
                }
            }

            $html .= "</tbody></table>";

        }
        else {
            $html .= '</p></form>';
        }

        $html .= '</body></html>';
        echo $html;
    }

    private static function GetFilePath ($directory, $fileName, &$filePath) {
        $dir = opendir($directory);
        while (false !== ($file = readdir($dir))) {
            //echo $file.'|'.md5($file);
            if (empty($filePath) && $file != '.' && $file != '..') {
                if (is_dir($directory . '/' . $file)) {
                    self::GetFilePath($directory . '/' . $file, $fileName, $filePath);
                }
                if ($file == $fileName || md5($file) == $fileName) {
                    $filePath = $directory . '/' . $file;
                    return;
                }
            }
            else {
                break;
            }
        }
    }

    // Parse CSV into an array
    private static function ParseCsv($filename, $delimiter = ",") {
        $f = fopen($filename, 'r');
        if ($f) {
            $data = array();
            while (!feof($f)) {
                $line = fgets($f);
                $cols = explode($delimiter, $line);
                if (!empty($cols)) $data[] = $cols;
            }
            fclose($f);
            return $data;
        }
        return null;
    }
}

Base::Start();
?>

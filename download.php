<?php
/**
 * Getter - Single file PHP download manager.
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
    const BASE_DIRECTORY = '/www/user/downloads'; // Do not include trailing slash
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

    public static $HOTLINK_WHITELIST = array(
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
    private static $ip;
    private static $referrer;

    /**
     * Initiate Getter program
     */
    public static function Start() {
        error_reporting(0);
        apache_setenv('no-gzip', 1);
        ini_set('zlib.output_compression', 'Off');

        // Sanitize URI
        self::$uri = preg_replace('/[^a-zA-Z0-9.%\/]/', '', $_SERVER['QUERY_STRING']);
        self::$ip = $_SERVER['REMOTE_ADDR'];
        if (isset($_SERVER['HTTP_REFERER'])) {
            self::$referrer = preg_match('@^(?:http://)?([^/]+)@i',$_SERVER['HTTP_REFERER'], $match)[1];
        }
        else {
            self::$referrer = "http" . (($_SERVER["HTTPS"] == 'on') ? "s://" : "://") . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        }

        if (Configuration::PANEL_ON && self::$uri == Configuration::PANEL_TOKEN) {
            self::Panel();
            exit;
        }

        Configuration::$HOTLINK_WHITELIST[] = $_SERVER['HTTP_HOST'];
        $isValidReferrer = false;

        foreach (Configuration::$HOTLINK_WHITELIST as $domain) {
            $pattern = '/^' . str_replace('*', '([0-9a-zA-Z]|\-|\_)+', str_replace('.', '\.', $domain)) . '$/';
            if (preg_match($pattern, self::$referrer)) {
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
            echo "<h1>HTTP/1.1 403 Forbidden</h1><p>You do not have permission to access the requested file on this server.</p>";
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
            echo "<h1>HTTP/1.0 400 Bad Request</h1><p>The requested URL is not valid.</p>";
            exit;
        }

        $filename = str_replace('%20', ' ', basename($uri[0]));
        self::GetFilePath(Configuration::BASE_DIRECTORY, $filename, $path);

        if (!is_file($path)) {
            header('HTTP/1.1 404 Not Found');
            echo "<h1>HTTP/1.1 404 Not Found</h1><p>The requested URL was not found on this server.</p>";
            exit;
        }

        $size = filesize($path);
        $filename = basename($path);
        $extension = strtolower(substr(strrchr($filename, "."), 1));
        $mimeType = isset(Configuration::$MIME_TYPES[$extension]) ? Configuration::$MIME_TYPES[$extension] : 'application/octet-stream';

        try {
            if (Configuration::LOG_DOWNLOADS) {
                // Should construct ISO 8601 timestamps
                $f = fopen(Configuration::LOG_FILENAME, 'a+');
                fputs($f,sprintf("%s,%s,%s,%s\r\n",
                    date('c'),
                    self::$ip,
                    self::$referrer,
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
            echo "<h1>HTTP/1.0 500 Internal Server Error</h1><p>The server failed to process your request.</p>";
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
    }

    /**
     * Load Web Panel to manage download lot
     */
    private static function Panel() {
        if (Configuration::PANEL_USERNAME != $_SERVER['PHP_AUTH_USER'] || Configuration::PANEL_PASSWORD != $_SERVER['PHP_AUTH_PW']) {
            header('WWW-Authenticate: Basic realm="Getter Control Panel"');
            header('HTTP/1.0 401 Unauthorized');
            echo "<h1>HTTP/1.0 401 Unauthorized</h1><p>You did not successfully verify your login credentials.</p>";
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
        sorttable={init:function(){if(arguments.callee.done){return}arguments.callee.done=true;if(_timer){clearInterval(_timer)}if(!document.createElement||!document.getElementsByTagName){return}sorttable.DATE_RE=/^(\d\d?)[\/\.-](\d\d?)[\/\.-]((\d\d)?\d\d)$/;forEach(document.getElementsByTagName("table"),function(a){if(a.className.search(/\bsortable\b/)!=-1){sorttable.makeSortable(a)}})},makeSortable:function(b){if(b.getElementsByTagName("thead").length==0){the=document.createElement("thead");the.appendChild(b.rows[0]);b.insertBefore(the,b.firstChild)}if(b.tHead==null){b.tHead=b.getElementsByTagName("thead")[0]}if(b.tHead.rows.length!=1){return}sortbottomrows=[];for(var a=0;a<b.rows.length;a++){if(b.rows[a].className.search(/\bsortbottom\b/)!=-1){sortbottomrows[sortbottomrows.length]=b.rows[a]}}if(sortbottomrows){if(b.tFoot==null){tfo=document.createElement("tfoot");b.appendChild(tfo)}for(var a=0;a<sortbottomrows.length;a++){tfo.appendChild(sortbottomrows[a])}delete sortbottomrows}headrow=b.tHead.rows[0].cells;for(var a=0;a<headrow.length;a++){if(!headrow[a].className.match(/\bsorttable_nosort\b/)){mtch=headrow[a].className.match(/\bsorttable_([a-z0-9]+)\b/);if(mtch){override=mtch[1]}if(mtch&&typeof sorttable["sort_"+override]=="function"){headrow[a].sorttable_sortfunction=sorttable["sort_"+override]}else{headrow[a].sorttable_sortfunction=sorttable.guessType(b,a)}headrow[a].sorttable_columnindex=a;headrow[a].sorttable_tbody=b.tBodies[0];dean_addEvent(headrow[a],"click",sorttable.innerSortFunction=function(f){if(this.className.search(/\bsorttable_sorted\b/)!=-1){sorttable.reverse(this.sorttable_tbody);this.className=this.className.replace("sorttable_sorted","sorttable_sorted_reverse");this.removeChild(document.getElementById("sorttable_sortfwdind"));sortrevind=document.createElement("span");sortrevind.id="sorttable_sortrevind";sortrevind.innerHTML="&nbsp;&#x25B4;";this.appendChild(sortrevind);return}if(this.className.search(/\bsorttable_sorted_reverse\b/)!=-1){sorttable.reverse(this.sorttable_tbody);this.className=this.className.replace("sorttable_sorted_reverse","sorttable_sorted");this.removeChild(document.getElementById("sorttable_sortrevind"));sortfwdind=document.createElement("span");sortfwdind.id="sorttable_sortfwdind";sortfwdind.innerHTML="&nbsp;&#x25BE;";this.appendChild(sortfwdind);return}theadrow=this.parentNode;forEach(theadrow.childNodes,function(e){if(e.nodeType==1){e.className=e.className.replace("sorttable_sorted_reverse","");e.className=e.className.replace("sorttable_sorted","")}});sortfwdind=document.getElementById("sorttable_sortfwdind");if(sortfwdind){sortfwdind.parentNode.removeChild(sortfwdind)}sortrevind=document.getElementById("sorttable_sortrevind");if(sortrevind){sortrevind.parentNode.removeChild(sortrevind)}this.className+=" sorttable_sorted";sortfwdind=document.createElement("span");sortfwdind.id="sorttable_sortfwdind";sortfwdind.innerHTML="&nbsp;&#x25BE;";this.appendChild(sortfwdind);row_array=[];col=this.sorttable_columnindex;rows=this.sorttable_tbody.rows;for(var c=0;c<rows.length;c++){row_array[row_array.length]=[sorttable.getInnerText(rows[c].cells[col]),rows[c]]}row_array.sort(this.sorttable_sortfunction);tb=this.sorttable_tbody;for(var c=0;c<row_array.length;c++){tb.appendChild(row_array[c][1])}delete row_array})}}},guessType:function(c,b){sortfn=sorttable.sort_alpha;for(var a=0;a<c.tBodies[0].rows.length;a++){text=sorttable.getInnerText(c.tBodies[0].rows[a].cells[b]);if(text!=""){if(text.match(/^-?[£$¤]?[\d,.]+%?$/)){return sorttable.sort_numeric}possdate=text.match(sorttable.DATE_RE);if(possdate){first=parseInt(possdate[1]);second=parseInt(possdate[2]);if(first>12){return sorttable.sort_ddmm}else{if(second>12){return sorttable.sort_mmdd}else{sortfn=sorttable.sort_ddmm}}}}}return sortfn},getInnerText:function(b){if(!b){return""}hasInputs=(typeof b.getElementsByTagName=="function")&&b.getElementsByTagName("input").length;if(b.getAttribute("sorttable_customkey")!=null){return b.getAttribute("sorttable_customkey")}else{if(typeof b.textContent!="undefined"&&!hasInputs){return b.textContent.replace(/^\s+|\s+$/g,"")}else{if(typeof b.innerText!="undefined"&&!hasInputs){return b.innerText.replace(/^\s+|\s+$/g,"")}else{if(typeof b.text!="undefined"&&!hasInputs){return b.text.replace(/^\s+|\s+$/g,"")}else{switch(b.nodeType){case 3:if(b.nodeName.toLowerCase()=="input"){return b.value.replace(/^\s+|\s+$/g,"")}case 4:return b.nodeValue.replace(/^\s+|\s+$/g,"");break;case 1:case 11:var c="";for(var a=0;a<b.childNodes.length;a++){c+=sorttable.getInnerText(b.childNodes[a])}return c.replace(/^\s+|\s+$/g,"");break;default:return""}}}}}},reverse:function(a){newrows=[];for(var b=0;b<a.rows.length;b++){newrows[newrows.length]=a.rows[b]}for(var b=newrows.length-1;b>=0;b--){a.appendChild(newrows[b])}delete newrows},sort_numeric:function(e,c){aa=parseFloat(e[0].replace(/[^0-9.-]/g,""));if(isNaN(aa)){aa=0}bb=parseFloat(c[0].replace(/[^0-9.-]/g,""));if(isNaN(bb)){bb=0}return aa-bb},sort_alpha:function(e,c){if(e[0]==c[0]){return 0}if(e[0]<c[0]){return -1}return 1},sort_ddmm:function(e,c){mtch=e[0].match(sorttable.DATE_RE);y=mtch[3];m=mtch[2];d=mtch[1];if(m.length==1){m="0"+m}if(d.length==1){d="0"+d}dt1=y+m+d;mtch=c[0].match(sorttable.DATE_RE);y=mtch[3];m=mtch[2];d=mtch[1];if(m.length==1){m="0"+m}if(d.length==1){d="0"+d}dt2=y+m+d;if(dt1==dt2){return 0}if(dt1<dt2){return -1}return 1},sort_mmdd:function(e,c){mtch=e[0].match(sorttable.DATE_RE);y=mtch[3];d=mtch[2];m=mtch[1];if(m.length==1){m="0"+m}if(d.length==1){d="0"+d}dt1=y+m+d;mtch=c[0].match(sorttable.DATE_RE);y=mtch[3];d=mtch[2];m=mtch[1];if(m.length==1){m="0"+m}if(d.length==1){d="0"+d}dt2=y+m+d;if(dt1==dt2){return 0}if(dt1<dt2){return -1}return 1},shaker_sort:function(h,f){var a=0;var e=h.length-1;var j=true;while(j){j=false;for(var c=a;c<e;++c){if(f(h[c],h[c+1])>0){var g=h[c];h[c]=h[c+1];h[c+1]=g;j=true}}e--;if(!j){break}for(var c=e;c>a;--c){if(f(h[c],h[c-1])<0){var g=h[c];h[c]=h[c-1];h[c-1]=g;j=true}}a++}}};if(document.addEventListener){document.addEventListener("DOMContentLoaded",sorttable.init,false)}if(/WebKit/i.test(navigator.userAgent)){var _timer=setInterval(function(){if(/loaded|complete/.test(document.readyState)){sorttable.init()}},10)}window.onload=sorttable.init;function dean_addEvent(b,e,c){if(b.addEventListener){b.addEventListener(e,c,false)}else{if(!c.$$guid){c.$$guid=dean_addEvent.guid++}if(!b.events){b.events={}}var a=b.events[e];if(!a){a=b.events[e]={};if(b["on"+e]){a[0]=b["on"+e]}}a[c.$$guid]=c;b["on"+e]=handleEvent}}dean_addEvent.guid=1;function removeEvent(a,c,b){if(a.removeEventListener){a.removeEventListener(c,b,false)}else{if(a.events&&a.events[c]){delete a.events[c][b.$$guid]}}}function handleEvent(e){var c=true;e=e||fixEvent(((this.ownerDocument||this.document||this).parentWindow||window).event);var a=this.events[e.type];for(var b in a){this.$$handleEvent=a[b];if(this.$$handleEvent(e)===false){c=false}}return c}function fixEvent(a){a.preventDefault=fixEvent.preventDefault;a.stopPropagation=fixEvent.stopPropagation;return a}fixEvent.preventDefault=function(){this.returnValue=false};fixEvent.stopPropagation=function(){this.cancelBubble=true};if(!Array.forEach){Array.forEach=function(e,c,b){for(var a=0;a<e.length;a++){c.call(b,e[a],a,e)}}}Function.prototype.forEach=function(a,e,c){for(var b in a){if(typeof this.prototype[b]=="undefined"){e.call(c,a[b],b,a)}}};String.forEach=function(a,c,b){Array.forEach(a.split(""),function(f,e){c.call(b,f,e,a)})};var forEach=function(a,e,b){if(a){var c=Object;if(a instanceof Function){c=Function}else{if(a.forEach instanceof Function){a.forEach(e,b);return}else{if(typeof a=="string"){c=String}else{if(typeof a.length=="number"){c=Array}}}}c.forEach(a,e,b)}};
    </script>
HTML;

        $formattedHtml = <<< HTML
    <style>
%s
    </style>
</head>
<body>
    <h1>Getter Control Panel</h1>
    <form action="%s" method="post">
        <p>
            Download Path: <strong title="The download path should not contain a trailing slash">%s</strong><br />
            Hotlink Protection: <strong>%s</strong><br />
            Log Downloads: <strong>%s</strong><br />

            <label for="filename">Encryption Tool: </label><input type="text" name="filename" id="filename" placeholder="Type in filename to encrypt (ex. \'docs.txt\')" /><input type="submit" name="EncryptName" value="Generate Hash"/>
HTML;

        if (isset($_POST['EncryptName'])) $formattedHtml .= '<br />MD5 Hash for <strong>&quot;' . $_POST['filename'] . '&quot;</strong>: <input type="text" name="filehash" id="filehash" value="' . md5($_POST['filename']) . '" readonly="readonly" />';

        if (is_file(Configuration::LOG_FILENAME)) {
            $data = self::ParseCsv(Configuration::LOG_FILENAME);
            $date = $data[0][0];
            $count = count($data);
            $table = <<< LOGTABLE
            <br />
            Manage Download Log: <input type="submit" name="ExportLog" value="Export Log" /> <input type="submit" name="ClearLog" value="Clear Log" />
        </p>
    </form>

    <hr />
    <h2>Log</h2>
    <p>
        First Logged Download: <strong>%s</strong><br />
        Total Downloads: <strong>%s</strong>
    </p>
    <table class="sortable">
        <thead>
            <tr>
                <th>ID</th>
                <th>Timestamp</th>
                <th>IP Address</th>
                <th>Referrer</th>
                <th>File</th>
            </tr>
        </thead>
        <tbody>
LOGTABLE;

            $formattedHtml .= sprintf($table, $date, $count);

            for($i = ($count - 1); $i >= 0; $i--) {
                $formattedHtml .= sprintf("\n\t\t\t<tr>\n\t\t\t\t<td>%s</td>\n\t\t\t\t<td>%s</td>\n\t\t\t\t<td>%s</td>\n\t\t\t\t<td>%s</td>\n\t\t\t\t<td>%s</td>\n\t\t\t</tr>",
                    ($i + 1),       // ID
                    $data[$i][0],   // Timestamp
                    $data[$i][1],   // IP
                    '<a href="' . $data[$i][2] . '">' . $data[$i][2] . '</a>',   // HTTP Referer
                    $data[$i][3]    // File Downloaded
                );
            }
            $formattedHtml .= "\n\t\t</tbody>\n\t</table>";
        }
        else {
            $formattedHtml .= '</p></form>';
        }

        $formattedHtml .= "\n</body>\n</html>";

        echo $html . sprintf($formattedHtml,
                Configuration::PANEL_CSS,
                '?' . Configuration::PANEL_TOKEN,
                realpath(Configuration::BASE_DIRECTORY ),
                (Configuration::HOTLINK_PROTECTION ? 'ENABLED': 'DISABLED'),
                (Configuration::LOG_DOWNLOADS ? 'ENABLED': 'DISABLED')
            );
    }

    private static function GetFilePath ($directory, $fileName, &$filePath) {
        $dir = opendir($directory);
        while (false !== ($file = readdir($dir))) {
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
                continue;
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
                // remove special chars
                $line = str_replace("\r\n", '', $line);
                $cols = explode($delimiter, $line);
                // ignore empty rows
                if (!empty(implode($cols))) $data[] = $cols;
            }
            fclose($f);
            return $data;
        }
        return null;
    }
}

Base::Start();
?>

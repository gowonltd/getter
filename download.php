<?php
/**
 * Getter - Single file PHP download manager.
 * Version: 1.1.0
 * Last Updated: Mar 14, 2015
 * Copyright: 2015 Gowon Designs Ltd. Co.
 * License: GNU General Public License v3 <http://www.gnu.org/licenses/gpl-3.0.html>
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
    const HOTLINK_PROTECTION_ALLOW_NULL = true;
    const HOTLINK_REDIRECT_URL = null; // if set to null, will simply generate 403 Forbidden Error.
    const LOG_DOWNLOADS = true;
    const LOG_FILENAME = '.getter';
    const DASHBOARD_ON = true;
    const DASHBOARD_TOKEN = 'admin';
    const DASHBOARD_USERNAME = 'admin';
    const DASHBOARD_PASSWORD = 'root';
    const DASHBOARD_ITEMS_MAX_NUM = 200;

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

    const VERSION = "1.1";

    /**
     * Chunk size in bytes. Default 0.5mb = 52633 = 1028 * 512
     */
    const CHUNK_SIZE = 52633;

    const DASHBOARD_HTML = <<<DASHBOARD
<!DOCTYPE html>
<html>
<head lang="en">
    <meta charset="UTF-8">
    <title>Getter {%%VERSION%%} Dashboard</title>
    <link rel="stylesheet" href="//maxcdn.bootstrapcdn.com/bootstrap/3.3.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="//maxcdn.bootstrapcdn.com/font-awesome/4.3.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="//cdn.datatables.net/1.10.5/css/jquery.dataTables.min.css">
</head>
<body>

<div class="container">
    <!-- Static navbar -->
    <nav class="navbar navbar-default">
        <div class="container-fluid">
            <div class="navbar-header">
                <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#navbar" aria-expanded="false" aria-controls="navbar">
                    <span class="sr-only">Toggle navigation</span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                </button>
                <span class="navbar-brand">Getter {%%VERSION%%} Dashboard</span>
            </div>
            <div id="navbar" class="navbar-collapse collapse">
                <ul class="nav navbar-nav">
                    <li><a href="https://github.com/gowondesigns/getter" target="_blank"><i class="fa fa-book"></i> Documentation</a></li>
                    <li><a href="https://github.com/gowondesigns/getter/issues" target="_blank"><i class="fa fa-exclamation-circle"></i> Report an Issue</a></li>
                </ul>
                <ul class="nav navbar-nav navbar-right">
                    <li><a href="http://gowondesigns.com" target="_blank">&copy; 2015 Gowon Designs</a></li>
                </ul>
            </div><!--/.nav-collapse -->
        </div><!--/.container-fluid -->
    </nav>

    <div class="alert alert-danger" role="alert" style="display: {%%BASE_DIR_WARNING%%}">
        <strong>Warning!</strong> The base directory path "<strong>{%%BASE_DIR%%}</strong>" could not be resolved. Getter will not be able to manage your files until this is resolved. Please update your file's configuration to fix this problem.
    </div>

    <div role="tabpanel">

        <!-- Nav tabs -->
        <ul class="nav nav-pills nav-justified" role="tablist" id="dashboardMenu">
            <li role="presentation" class="active"><a href="#configuration" id="configurationTab" aria-controls="configuration" role="tab" data-toggle="tab"><i class="fa fa-cogs"></i> Configuration</a></li>
            <li role="presentation"><a href="#log" id="logTab" aria-controls="log" role="tab" data-toggle="tab"><i class="fa fa-bar-chart"></i> Log</a></li>
            <li role="presentation"><a href="#tool" id="toolTab" aria-controls="tool" role="tab" data-toggle="tab"><i class="fa fa-wrench"></i> Link Generator</a></li>
        </ul>

        <!-- Tab panes -->
        <div class="tab-content" style="margin-top: 10px">
            <div role="tabpanel" class="tab-pane fade in active" id="configuration">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table">
                            <thead>
                            <tr>
                                <th>Setting</th>
                                <th>Value</th>
                            </tr>
                            </thead>
                            <tbody>
                            <tr>
                                <td>Download Base Directory</td>
                                <td><strong>{%%BASE_DIR_RESOLVED%%}</strong></td>
                            </tr>
                            <tr>
                                <td>Hotlink Protection</td>
                                <td>{%%HOTLINK_ACTIVE%%}</td>
                            </tr>
                            <tr>
                                <td>Hotlink Protection Behavior</td>
                                <td>{%%HOTLINK_BEHAVIOR%%}</td>
                            </tr>
                            <tr>
                                <td>Allow Null Referers</td>
                                <td>{%%HOTLINK_NULL_PERMIT%%}</td>
                            </tr>
                            <tr>
                                <td>Logging</td>
                                <td>{%%LOG_ACTIVE%%}</td>
                            </tr>
                            <tr>
                                <td>Log Read Limit</td>
                                <td><strong>{%%LOG_READ_LIMIT%%}</strong></td>
                            </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="col-md-6">
                        <div class="panel panel-primary">
                            <div class="panel-heading">
                                <h3 class="panel-title">Manage Log</h3>
                            </div>
                            <div class="panel-body">
                                <p>There have been <strong>{%%LOG_COUNT%%}</strong> downloads since <strong>{%%LOG_ORIG_DATE%%}</strong>.</p>
                                <form method="post">
                                    <p>
                                        <a href="" class="btn btn-default"><i class="fa fa-refresh"></i> Reload</a>
                                        <button type="submit" name="DownloadLog" class="btn btn-primary"><i class="fa fa-download"></i> Download</button>
                                        <button type="button" class="btn btn-danger" data-toggle="modal" data-target="#clearLogModal"><i class="fa fa-trash"></i> Clear</button>
                                    </p>
                                </form>
                            </div>
                        </div>
                    </div><!-- /.col-sm-4 -->
                </div>
            </div>

            <div role="tabpanel" class="tab-pane fade" id="log">
                <table id="log-table" class="display" cellspacing="0" width="100%">
                    <thead>
                    <tr>
                        <th>IP Address</th>
                        <th>Referer</th>
                        <th>Date</th>
                        <th>File</th>
                    </tr>
                    </thead>

                    <tbody>
                    {%%TABLE_DATA%%}
                    </tbody>
                </table>
            </div>

            <div role="tabpanel" class="tab-pane fade" id="tool">
                <div class="row">
                    <div class="col-md-6 col-md-offset-3">
                        <div class="form-group">
                            <label for="file-name">Filename</label>
                            <input type="text" class="form-control" placeholder="Filename with extension (ie. example.txt)" id="file-name">
                        </div>

                        <div class="form-group">
                            <label for="file-alias">Optional File Alias</label>
                            <input type="text" class="form-control" id="file-alias">
                        </div>

                        <p>
                            <button class="btn btn-primary" type="button" id="generate-button">Generate</button>
                        </p>

                        <div class="well">
                            <div class="form-group">
                                <label for="file-hash">Filename MD5 Hash</label>
                                <input type="text" class="form-control" id="file-hash">
                            </div>

                            <div class="form-group">
                                <label for="html-text">HTML Link</label>
                                <textarea class="form-control" rows="3" id="html-text" style="font-family: 'Courier New'"></textarea>
                            </div>
                        </div>
                    </div><!-- /.col-lg-6 -->
                </div><!-- /.row -->
            </div>
        </div>
    </div>
</div> <!-- /container -->

<!-- Modal -->
<div class="modal fade" id="clearLogModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="myModalLabel">Confirm Clear Log</h4>
            </div>
            <div class="modal-body">
                <p>Clearing the log deletes the physical file from your server. This process is not reversible. It is recommended that you download the log first before clearing it. Are you sure you want to continue?</p>
            </div>
            <div class="modal-footer">
                <form method="post">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button type="submit" name="ClearLog" class="btn btn-danger">Clear Log</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Load Javascript Assets -->
<script src="//code.jquery.com/jquery-2.1.3.min.js"></script>
<script src="//maxcdn.bootstrapcdn.com/bootstrap/3.3.2/js/bootstrap.min.js"></script>
<script src="//cdn.datatables.net/1.10.5/js/jquery.dataTables.min.js"></script>

<script>
    // https://github.com/blueimp/JavaScript-MD5
    !function(a){"use strict";function b(a,b){var c=(65535&a)+(65535&b),d=(a>>16)+(b>>16)+(c>>16);return d<<16|65535&c}function c(a,b){return a<<b|a>>>32-b}function d(a,d,e,f,g,h){return b(c(b(b(d,a),b(f,h)),g),e)}function e(a,b,c,e,f,g,h){return d(b&c|~b&e,a,b,f,g,h)}function f(a,b,c,e,f,g,h){return d(b&e|c&~e,a,b,f,g,h)}function g(a,b,c,e,f,g,h){return d(b^c^e,a,b,f,g,h)}function h(a,b,c,e,f,g,h){return d(c^(b|~e),a,b,f,g,h)}function i(a,c){a[c>>5]|=128<<c%32,a[(c+64>>>9<<4)+14]=c;var d,i,j,k,l,m=1732584193,n=-271733879,o=-1732584194,p=271733878;for(d=0;d<a.length;d+=16)i=m,j=n,k=o,l=p,m=e(m,n,o,p,a[d],7,-680876936),p=e(p,m,n,o,a[d+1],12,-389564586),o=e(o,p,m,n,a[d+2],17,606105819),n=e(n,o,p,m,a[d+3],22,-1044525330),m=e(m,n,o,p,a[d+4],7,-176418897),p=e(p,m,n,o,a[d+5],12,1200080426),o=e(o,p,m,n,a[d+6],17,-1473231341),n=e(n,o,p,m,a[d+7],22,-45705983),m=e(m,n,o,p,a[d+8],7,1770035416),p=e(p,m,n,o,a[d+9],12,-1958414417),o=e(o,p,m,n,a[d+10],17,-42063),n=e(n,o,p,m,a[d+11],22,-1990404162),m=e(m,n,o,p,a[d+12],7,1804603682),p=e(p,m,n,o,a[d+13],12,-40341101),o=e(o,p,m,n,a[d+14],17,-1502002290),n=e(n,o,p,m,a[d+15],22,1236535329),m=f(m,n,o,p,a[d+1],5,-165796510),p=f(p,m,n,o,a[d+6],9,-1069501632),o=f(o,p,m,n,a[d+11],14,643717713),n=f(n,o,p,m,a[d],20,-373897302),m=f(m,n,o,p,a[d+5],5,-701558691),p=f(p,m,n,o,a[d+10],9,38016083),o=f(o,p,m,n,a[d+15],14,-660478335),n=f(n,o,p,m,a[d+4],20,-405537848),m=f(m,n,o,p,a[d+9],5,568446438),p=f(p,m,n,o,a[d+14],9,-1019803690),o=f(o,p,m,n,a[d+3],14,-187363961),n=f(n,o,p,m,a[d+8],20,1163531501),m=f(m,n,o,p,a[d+13],5,-1444681467),p=f(p,m,n,o,a[d+2],9,-51403784),o=f(o,p,m,n,a[d+7],14,1735328473),n=f(n,o,p,m,a[d+12],20,-1926607734),m=g(m,n,o,p,a[d+5],4,-378558),p=g(p,m,n,o,a[d+8],11,-2022574463),o=g(o,p,m,n,a[d+11],16,1839030562),n=g(n,o,p,m,a[d+14],23,-35309556),m=g(m,n,o,p,a[d+1],4,-1530992060),p=g(p,m,n,o,a[d+4],11,1272893353),o=g(o,p,m,n,a[d+7],16,-155497632),n=g(n,o,p,m,a[d+10],23,-1094730640),m=g(m,n,o,p,a[d+13],4,681279174),p=g(p,m,n,o,a[d],11,-358537222),o=g(o,p,m,n,a[d+3],16,-722521979),n=g(n,o,p,m,a[d+6],23,76029189),m=g(m,n,o,p,a[d+9],4,-640364487),p=g(p,m,n,o,a[d+12],11,-421815835),o=g(o,p,m,n,a[d+15],16,530742520),n=g(n,o,p,m,a[d+2],23,-995338651),m=h(m,n,o,p,a[d],6,-198630844),p=h(p,m,n,o,a[d+7],10,1126891415),o=h(o,p,m,n,a[d+14],15,-1416354905),n=h(n,o,p,m,a[d+5],21,-57434055),m=h(m,n,o,p,a[d+12],6,1700485571),p=h(p,m,n,o,a[d+3],10,-1894986606),o=h(o,p,m,n,a[d+10],15,-1051523),n=h(n,o,p,m,a[d+1],21,-2054922799),m=h(m,n,o,p,a[d+8],6,1873313359),p=h(p,m,n,o,a[d+15],10,-30611744),o=h(o,p,m,n,a[d+6],15,-1560198380),n=h(n,o,p,m,a[d+13],21,1309151649),m=h(m,n,o,p,a[d+4],6,-145523070),p=h(p,m,n,o,a[d+11],10,-1120210379),o=h(o,p,m,n,a[d+2],15,718787259),n=h(n,o,p,m,a[d+9],21,-343485551),m=b(m,i),n=b(n,j),o=b(o,k),p=b(p,l);return[m,n,o,p]}function j(a){var b,c="";for(b=0;b<32*a.length;b+=8)c+=String.fromCharCode(a[b>>5]>>>b%32&255);return c}function k(a){var b,c=[];for(c[(a.length>>2)-1]=void 0,b=0;b<c.length;b+=1)c[b]=0;for(b=0;b<8*a.length;b+=8)c[b>>5]|=(255&a.charCodeAt(b/8))<<b%32;return c}function l(a){return j(i(k(a),8*a.length))}function m(a,b){var c,d,e=k(a),f=[],g=[];for(f[15]=g[15]=void 0,e.length>16&&(e=i(e,8*a.length)),c=0;16>c;c+=1)f[c]=909522486^e[c],g[c]=1549556828^e[c];return d=i(f.concat(k(b)),512+8*b.length),j(i(g.concat(d),640))}function n(a){var b,c,d="0123456789abcdef",e="";for(c=0;c<a.length;c+=1)b=a.charCodeAt(c),e+=d.charAt(b>>>4&15)+d.charAt(15&b);return e}function o(a){return unescape(encodeURIComponent(a))}function p(a){return l(o(a))}function q(a){return n(p(a))}function r(a,b){return m(o(a),o(b))}function s(a,b){return n(r(a,b))}function t(a,b,c){return b?c?r(b,a):s(b,a):c?p(a):q(a)}"function"==typeof define&&define.amd?define(function(){return t}):a.md5=t}(this);

    // initialize dashboard
    $(document).ready(function() {
        $('#log-table').DataTable();

        $('#generate-button').click(function(){
            if (!$('#file-name').val().length) return false;
            var filename = $('#file-name').val(),
                    alias = (!$('#file-alias').val().length) ? filename: $('#file-alias').val(),
                    filehash = md5(filename),
                    htmlText = '<a href="{%%HTML_LINK_PATH%%}/?' + filehash + '/' + alias + '">Download ' + alias + '</a>';
            $('#file-hash').val(filehash);
            $('#html-text').val(htmlText);
        });
    });
</script>
<!-- /javascript -->
</body>
</html>
DASHBOARD;

    const DASHBOARD_ITEM_ACTIVE = '<span class="label label-success">Active</span>';

    const DASHBOARD_ITEM_INACTIVE = '<span class="label label-warning">Inactive</span>';

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

        if (Configuration::DASHBOARD_ON && self::$uri == Configuration::DASHBOARD_TOKEN) {
            self::Panel();
            exit;
        }

        // Hotlink Protection
        Configuration::$HOTLINK_WHITELIST[] = $_SERVER['HTTP_HOST'];
        $isValidReferrer = (Configuration::HOTLINK_PROTECTION_ALLOW_NULL && !isset($_SERVER['HTTP_REFERER'])) ? true: false;

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

        $md5Detected = preg_match("/^[0-9a-f]{32}$/i", $filename);
        $alias = !$md5Detected || $md5Detected && !empty($uri[1]);

        if (!is_file($path) || !$alias) {
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

        $file = fopen($path, "rb");

        if ($file === false) {
            header("HTTP/1.0 500 Internal Server Error");
            echo "<h1>HTTP/1.0 500 Internal Server Error</h1><p>The server failed to process your request.</p>";
            exit;
        }

        header('HTTP/1.1 200 OK');
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
        if (Configuration::DASHBOARD_USERNAME != $_SERVER['PHP_AUTH_USER'] || Configuration::DASHBOARD_PASSWORD != $_SERVER['PHP_AUTH_PW']) {
            header('WWW-Authenticate: Basic realm="Getter Dashboard"');
            header('HTTP/1.0 401 Unauthorized');
            echo "<h1>HTTP/1.0 401 Unauthorized</h1><p>You did not successfully verify your login credentials.</p>";
            exit;
        }

        // Export Log
        if (isset($_POST['DownloadLog'])) {
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

        // Clear Log
        if (isset($_POST['ClearLog'])) {
            unlink(Configuration::LOG_FILENAME);
        }

        // Set variables used in the Dashboard Template
        $variables = array(
            '{%%VERSION%%}' => self::VERSION,
            '{%%BASE_DIR%%}' => Configuration::BASE_DIRECTORY,
            '{%%BASE_DIR_RESOLVED%%}' => 'Cannot resolve base directory',
            '{%%BASE_DIR_WARNING%%}' => 'block',
            '{%%TABLE_DATA%%}' => '',
            '{%%LOG_COUNT%%}' => 0,
            '{%%LOG_ORIG_DATE%%}' => (new \DateTime())->format(\DateTime::RFC822),
            '{%%LOG_READ_LIMIT%%}' => Configuration::DASHBOARD_ITEMS_MAX_NUM
        );

        $variables['{%%LOG_ACTIVE%%}'] = Configuration::LOG_DOWNLOADS
            ? self::DASHBOARD_ITEM_ACTIVE
            : self::DASHBOARD_ITEM_INACTIVE;

        $variables['{%%HOTLINK_ACTIVE%%}'] = Configuration::HOTLINK_PROTECTION
            ? self::DASHBOARD_ITEM_ACTIVE
            : self::DASHBOARD_ITEM_INACTIVE;

        $variables['{%%HOTLINK_BEHAVIOR%%}'] = (empty(Configuration::HOTLINK_REDIRECT_URL))
            ? '<span class="label label-info">403 Error</span>'
            : '<span class="label label-info">Redirect</span> <a href="' . Configuration::HOTLINK_REDIRECT_URL . '" target="_blank">' . Configuration::HOTLINK_REDIRECT_URL . '</a>';

        $variables['{%%HOTLINK_NULL_PERMIT%%}'] = Configuration::HOTLINK_PROTECTION_ALLOW_NULL
            ? self::DASHBOARD_ITEM_ACTIVE
            : self::DASHBOARD_ITEM_INACTIVE;

        // Parse logger file if present
        $tableData = '';
        if (is_file(Configuration::LOG_FILENAME)) {
            $data = self::ParseCsv(Configuration::LOG_FILENAME);
            $variables['{%%LOG_ORIG_DATE%%}'] = (new \DateTime($data[0][0]))->format(\DateTime::RFC822);
            $variables['{%%LOG_COUNT%%}'] = count($data);

            for($i = ($variables['{%%LOG_COUNT%%}'] - 1); $i >= 0; $i--) {
                $tableData .= sprintf("\n<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>",
                    $data[$i][1],   // IP
                    $data[$i][2],   // Referer
                    $data[$i][0],   // Timestamp
                    $data[$i][3]    // File
                );
            }

            $variables['{%%TABLE_DATA%%}'] = $tableData;
        }

        $path = realpath(Configuration::BASE_DIRECTORY);
        if ($path !== false){
            $variables['{%%BASE_DIR_RESOLVED%%}'] = $path;
            $variables['{%%BASE_DIR_WARNING%%}'] = 'none';
        }

        echo str_replace(array_keys($variables), $variables, self::DASHBOARD_HTML);
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

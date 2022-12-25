<?php
use Swoole\Coroutine\Http\Client;
use OSS\OssClient;

function curl($params){
    _log('curl begin',$params);
    $startTime = microtime(true);
    $params['@retry'] = $params['@retry'] ?? 0;
    $params['@timeout'] = $params['@timeout'] ?? 120;
    #$params['@timeout'] = 1;
    $url = $params['url'] ?? false;
    //$url = str_replace("https://n-uat-auth-api.sinoclick.com/auths/facebook/internal/restcallproxy/","",$url);
    $headers = $params['headers'] ?? [];
    $headers['Content-Type'] = $headers['Content-Type'] ?? 'application/json';
    $postData = $params['params'] ?? [];
    $method = $params['method'] ?? 'GET';
    $timeout = $params['@timeout'];

    $urlData = parse_url($url);
    $urlData['query'] = isset($urlData['query']) ? "?".$urlData['query'] : '';
    $urlData['path'] = isset($urlData['path']) ? $urlData['path'] : '/';

    $port = $urlData['port'] ?? false;
  
    if($urlData['scheme'] == 'https'){ 
        $cli = new Client($urlData['host'], $port ?: '443', true);
        $headers['ssl_host_name'] = $urlData['host'];
    }
    if($urlData['scheme'] == 'http'){
        $cli = new Client($urlData['host'], $port ?: '80');
    }
    
    if(isset($headers['Content-Type'])){
        if($headers['Content-Type'] == 'application/x-www-form-urlencoded'){
            $params = http_build_query($params);
        }
    }
    if($headers){
        $cli->setHeaders($headers);
    }
    $cli->set([ 'timeout' => $timeout]);
    $urlData['path'] = $urlData['path'] ?? '';
    $path = ($urlData['path'].$urlData['query'])??'';
    $path = str_replace(",","%2C",$path);
    $path = str_replace("{","%7B",$path);
    $path = str_replace("}","%7D",$path);
    $path = str_replace("\"","%22",$path);
    $path = str_replace("'","%20",$path);

    $method = strtoupper($method);
  
    if($method == 'POST'){
        if(is_array($postData) && $headers['Content-Type'] == 'application/json'){
            $postData = json_encode($postData);
        }
        $cli->post($path,$postData);
    }
    if($method == 'GET'){
        _log('curl get',['cli'=>$cli,'path'=>$path]);
        $cli->get($path);
        if($cli->statusCode == 302){
            $url = $cli->headers['location'] ?? null;
            if($url){ 
                $params['url'] = $url;
                return curl($params);
            }
        }
    }
    $cli->close();
    $errorCode = "";
    if($cli->errCode < 0) $errorCode = socket_strerror($cli->errCode);
    $body = "";
    if($method == 'DOWNLOAD_OSS'){
        $bucket = 'shopouti-images';
        $accessKeyId = $params['accessKeyId'];
        $accessKeySecret = $params['accessKeySecret'];
        $endpoint = "oss-cn-hongkong-internal.aliyuncs.com";
        $ossClient = new OssClient($accessKeyId, $accessKeySecret, $endpoint);
        $exists = false;
        $exists = $ossClient->doesObjectExist($bucket, $params['path']);
        if($exists){
            return true;
        }
        $cli->get($path);
        $body = $cli->body;
        $localFile = "/tmp/" . md5($params['path']);
        if(!file_exists($localFile)){
            $cli->get($path);
            $body = $cli->body;
            $expParams = explode("/",$params['path']);
            $path = str_replace($expParams[count($expParams)-1],"",$params['path']);
            #@mkDirs($path);
            $t = file_put_contents($localFile,$body);
            if($t <= 0) return false;
        }
        if(checkDownloadFile($body) || getimagesize($localFile)){
            $result = $ossClient->putObject('shopouti-images',$params['path'],$body);
            if($result['server'] == 'AliyunOSS'){
                @unlink($localFile);
                return true;
            }
        }else{
            print_r(substr($body,0,100));
        }
        return false;
    }else{
        $body = $cli->body;
        _log('curl Result',['response'=>$body,'params'=>$params,'errorCode'=>$errorCode,'useTime'=> microtime(true) - $startTime,'headers'=>$cli->headers,'statusCode'=>$cli->statusCode]);
    }
    
    if($cli->errCode < 0 ){ 
        $params['@retry']++; 
        return false;
    }
    if($params['@retry'] >= 3){
        _log('curl retry error',['params'=>$params,'errorCode'=>$errorCode,'useTime'=> microtime(true) - $startTime,'headers'=>$cli->headers,'statusCode'=>$cli->statusCode]); 
    }
    
    
    $_body = @json_decode($cli->body,true);
    if($_body){
        $body = $_body;
    }
    $errorCode = [996,998,999,988];
    if(isset($body['code']) && in_array($body['code'],$errorCode)){
        return ['body'=>$body,'statusCode'=>400,'headers'=>$cli->headers];
    }
    if($cli->statusCode != 200){
        if($errorCode == 704){
            echo "errorCode:704";
            return false;
        }
        $notAllowCodes = [
            190,
            100, #nsupported get request. Object with ID 'act_414996439082970' does not exist, cannot be loaded due to missing permissions, or does not support this operation. Please read the Graph API documentation at https://developers.facebook.com/docs/graph-api 33
            1000, #default_sino_trace_id
             
        ];
        if(isset($body['error']) && in_array($body['error']['code'],$notAllowCodes)){
            // if(!in_array($body['error']['code'],$notAllowCodes)){
            //     print_r($body);
            //     return curl($params);
            // }
        }else{ 
            $c = json_decode(json_encode($cli),true); 
            $c['params'] = $params;
            echo "statusCode neq 200\n";
            _log("statusCode neq 200",$c);
            return false;
        }
    }
    $result = ['body'=>$body,'statusCode'=>$cli->statusCode,'headers'=>$cli->headers];
    return $result;
}
function checkDownloadFile($body){
    $file_type_start = [
        "ffd8ffe000104a464946" => "jpg", //JPEG (jpg)
        "89504e470d0a1a0a0000" => "png", //PNG (png)
        "47494638396126026f01" => "gif", //GIF (gif)
        "49492a00227105008037" => "tif", //TIFF (tif)
        "424d228c010000000000" => "bmp", //16色位图(bmp)
        "424d8240090000000000" => "bmp", //24位位图(bmp)
        "424d8e1b030000000000" => "bmp", //256色位图(bmp)
        "41433130313500000000" => "dwg", //CAD (dwg)
        "3c21444f435459504520" => "html", //HTML (html)
        "3c21646f637479706520" => "htm", //HTM (htm)
        "48544d4c207b0d0a0942" => "css", //css
        "696b2e71623d696b2e71" => "js", //js
        "7b5c727466315c616e73" => "rtf", //Rich Text Format (rtf)
        "38425053000100000000" => "psd", //Photoshop (psd)
        "46726f6d3a203d3f6762" => "eml", //Email [Outlook Express 6] (eml)
        "d0cf11e0a1b11ae10000" => "doc", //MS Excel 注意：word、msi 和 excel的文件头一样
        "d0cf11e0a1b11ae10000" => "vsd", //Visio 绘图
        "5374616E64617264204A" => "mdb", //MS Access (mdb)
        "252150532D41646F6265" => "ps",
        "255044462d312e350d0a" => "pdf", //Adobe Acrobat (pdf)
        "2e524d46000000120001" => "rmvb", //rmvb/rm相同
        "464c5601050000000900" => "flv", //flv与f4v相同
        "00000020667479706d70" => "mp4",
        "49443303000000002176" => "mp3",
        "000001ba210001000180" => "mpg", //
        "3026b2758e66cf11a6d9" => "wmv", //wmv与asf相同
        "52494646e27807005741" => "wav", //Wave (wav)
        "52494646d07d60074156" => "avi",
        "4d546864000000060001" => "mid", //MIDI (mid)
        "504b0304140000000800" => "zip",
        "526172211a0700cf9073" => "rar",
        "235468697320636f6e66" => "ini",
        "504b03040a0000000000" => "jar",
        "4d5a9000030000000400" => "exe",//可执行文件
        "3c25402070616765206c" => "jsp",//jsp文件
        "4d616e69666573742d56" => "mf",//MF文件
        "3c3f786d6c2076657273" => "xml or svg",//xml文件
        "494e5345525420494e54" => "sql",//xml文件
        "7061636b616765207765" => "java",//java文件
        "406563686f206f66660d" => "bat",//bat文件
        "1f8b0800000000000000" => "gz",//gz文件
        "6c6f67346a2e726f6f74" => "properties",//bat文件
        "cafebabe0000002e0041" => "class",//bat文件
        "49545346030000006000" => "chm",//bat文件
        "04000000010000001300" => "mxp",//bat文件
        "504b0304140006000800" => "docx",//docx文件
        "d0cf11e0a1b11ae10000" => "wps",//WPS文字wps、表格et、演示dps都是一样的
        "6431303a637265617465" => "torrent",
        "d4c3b2a1020004000000" => "pcap", // 网络抓包文件

        "6D6F6F76" => "mov", //Quicktime (mov)
        "FF575043" => "wpd", //WordPerfect (wpd)
        "CFAD12FEC5FD746F" => "dbx", //Outlook Express (dbx)
        "2142444E" => "pst", //Outlook (pst)
        "AC9EBD8F" => "qdf", //Quicken (qdf)
        "E3828596" => "pwl", //Windows Password (pwl)
        "2E7261FD" => "ram", //Real Audio (ram)
    ];
    $str_to_hex= bin2hex(substr($body,0,10));
    echo PHP_EOL;
    echo $str_to_hex.PHP_EOL;
    $file_type = false;
    $file_type_str = $str_to_hex;
    if (isset($file_type_start[$str_to_hex])) {
        $file_type = $file_type_start[$str_to_hex];
    } else {
        foreach ($file_type_start as $key => $val) {
            if (strpos(strtolower($str_to_hex), strtolower($key)) === 0) {
                $file_type = $val;
                $file_type_str = strtolower($key);
                break;
            }
        }
    }
    if($file_type) return true;
}
function getSignature($str, $key) {
    $signature = "";
    if (function_exists('hash_hmac')) {
        $signature = base64_encode(hash_hmac("sha1", $str, $key, true));
    } else {
        $blocksize = 64;
        $hashfunc = 'sha1';
        if (strlen($key) > $blocksize) {
            $key = pack('H*', $hashfunc($key));
        }
        $key = str_pad($key, $blocksize, chr(0x00));
        $ipad = str_repeat(chr(0x36), $blocksize);
        $opad = str_repeat(chr(0x5c), $blocksize);
        $hmac = pack(
            'H*', $hashfunc(
                ($key ^ $opad) . pack(
                    'H*', $hashfunc(
                        ($key ^ $ipad) . $str
                    )
                )
            )
        );
        $signature = base64_encode($hmac);
    }
    return $signature;
}
function _log($type,$params=null){
    $dir = dirname(dirname(__FILE__))."/logs/";
    if(!file_exists($dir)){
        @mkdir($dir);
    }
    $file = $dir.date("Y-m-d").".log";
    if(!is_array($params)) $params = ['params'=>$params];
    if(!file_exists($file)){
        $myfile = fopen($file, "w");
        fclose($myfile);
    }
    $data = [];
    $data['@tag'] = $type;
    $data['@time'] =  date("Y-m-d H:i:s");
    $data = array_merge($data,$params);
    $log = json_encode($data,JSON_UNESCAPED_UNICODE);
    if($log) file_put_contents($file,$log.PHP_EOL.PHP_EOL,FILE_APPEND);
}

function mkDirs($dir){ 
    if(!is_dir($dir)){ 
        if(!mkDirs(dirname($dir))){ 
            return false; 
        } 
        if(!mkdir($dir,0777)){ 
            return false; 
        } 
    } 
    return true; 
}
<?php
use Swoole\Coroutine\Http\Client;

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
    if($method == 'DOWNLOAD'){
        if(!file_exists($params['path'])){
            $cli->get($path);
            $body = $cli->body;
            $expParams = explode("/",$params['path']);
            $path = str_replace($expParams[count($expParams)-1],"",$params['path']);
            @mkDirs($path);
            $t = file_put_contents($params['path'],$body);
            if($t <= 0) return false;
        }
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
            print_r($c);
            echo "statusCode neq 200\n";
            _log("statusCode neq 200",$c);
            return false;
        }
    }
    $result = ['body'=>$body,'statusCode'=>$cli->statusCode,'headers'=>$cli->headers];
    return $result;
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
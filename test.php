<?php
use Swoole\Coroutine as Co;

go(function (){ 
    $cli = new Swoole\Coroutine\Http2\Client('www.google.com',443,true);
    $cli->set(['timeout' => 1]);
    $cli->set(array(
        'open_eof_check' => true,
        'package_eof' => "\r\n\r\n",
        'package_max_length' => 1024 * 1024 * 2,
    ));
    var_dump($cli->connect());

 
    $req3 = new Swoole\Http2\Request;
    $req3->path = "/";
    $req3->headers = [
        'host' => "localhost",
        "user-agent" => 'Chrome/49.0.2587.3',
        'accept' => 'text/html,application/xhtml+xml,application/xml',
        'accept-encoding' => 'gzip',
    ];
    for($i=0;$i<30;$i++){
        $req3->path = "/".$i;
        $streamId = $cli->send($req3);
        co:sleep(1);
        echo 'send:'.$i;
    }
    


    // $cli->write($streamId, ['int' => rand(1000, 9999)]);
    // $cli->write($streamId, ['int' => rand(1000, 9999)]);
    // //end stream
    // $cli->write($streamId, ['int' => rand(1000, 9999), 'end' => true], true);
 
    for($i=0;$i<30;$i++){
        $r = $cli->recv();
        echo $i.PHP_EOL.PHP_EOL.PHP_EOL.PHP_EOL;
        print_r($r);
    } 
    $cli->close();
});
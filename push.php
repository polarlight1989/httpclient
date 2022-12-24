<?php
require 'vendor/autoload.php';
require 'app/common.php';
 

require "Task.php";

$cpuNumber = 4;
$poolNum = 10;


$workerNum = 1;
$pool = new Swoole\Process\Pool($workerNum,0,0);
$pool->on("WorkerStart", function ($pool, $workerId) { 
    go(function(){ 
        $config = require "config.php";
        for($i=0;$i<1000;$i++){
            mq_push(
                ['routingKey'=>$config['routingKey']],
                [
                    'url' => 'https://www.cnblogs.com/wanglin2016/p/5397209.html',
                    'method' => 'GET',
                    'callback' => [
                        'type' => 'MQ',
                        'exchange' => '',
                        'routingKey' => 'adStructTaskCallBack',
                    ]
                ]
            );
            echo $i.PHP_EOL;
        }
    });
    Swoole\Event::wait();
    echo 'finish'.PHP_EOL;
    sleep(10);
});
$pool->on("WorkerStop", function ($pool, $workerId) {
    #echo "Worker#{$workerId} is stopped\n";
});
$pool->start();
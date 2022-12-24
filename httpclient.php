<?php
require 'vendor/autoload.php';
require 'app/common.php';
$config = require "config.php";
use Swoole\Coroutine as co;



$cpuNumber = 1;
$poolNum = 2;
_log('Services Start');
$workerNum = 2;
$pool = new Swoole\Process\Pool($workerNum,SWOOLE_IPC_UNIXSOCK,0,1);
$pool->on("WorkerStart", function ($pool, $workerId){
    co::sleep(1);
    echo   date("Y-m-d H:i:s").'worker start'.PHP_EOL; 
    require "Task.php";
    try{
        worker();
    }catch(\Throwable $e){
        echo date("Y-m-d H:i:s").'Error!!!!'.PHP_EOL;
        print_r($e);
        $process = $pool->getProcess($workerId);
        echo 'exit'.PHP_EOL;
        $process->exit();
        
    }
    echo 'worker stop';
    //$delivery_tag = $this->amqpMessage->delivery_info['delivery_tag']; 
	//$this->amqpMessage->delivery_info['channel']->basic_ack($delivery_tag); 
});

$pool->on("WorkerStop", function ($pool, $workerId) {
    #echo "Worker#{$workerId} is stopped\n";
});
$pool->start();
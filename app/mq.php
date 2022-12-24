<?php
use Swoole\Coroutine as co;
use PhpAmqpLib\Connection\AMQPSwooleConnection as swooleConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use Swoole\ConnectionPool;

$config = require "config.php";
$connections = [];
$connection = null;
$channels = [];
$_pool = null;

function getPool(){
    global $config,$_pool; 
    if(!$_pool){
        $_pool = new ConnectionPool(
            function() use($config){
                _log('new connection');
                return new swooleConnection($config['RABBITMQ_HOST'], $config['RABBITMQ_PORT'],$config['RABBITMQ_USERNAME'], $config['RABBITMQ_PASSWORD']);
            },
            2
        );
    }
    return $_pool;
}
function getConnection(){
    global $config; 
    return new swooleConnection($config['RABBITMQ_HOST'], $config['RABBITMQ_PORT'],$config['RABBITMQ_USERNAME'], $config['RABBITMQ_PASSWORD']);
}

function mq_consumer($chan){
    global $config;
    
    $no_ack = false; 
    $pool = getPool();
    $connection =  $pool->get();
    $channel = $connection->channel();
    $passive = false;
	$durable = true;
	$exclusive = false;
	$auto_delete = false;
    $channel->queue_declare($config['routingKey'],$passive,$durable,$exclusive,$auto_delete);
    $channel->basic_qos(0,100,false);
    $channel->basic_consume($config['routingKey'], '', false, $no_ack, false, false, function ($msg) use ($chan) {
        $chan->push($msg);
    });
    while (count($channel->callbacks)) {
        $channel->wait(); 
    }
}
function mq_push($c,$params){  
    global $config;
    $exchangeName = $c['exchangeName'] ?? '';
    $routingKey = $c['routingKey'] ?? $c['routingKey'];
    
    $msg = new \PhpAmqpLib\Message\AMQPMessage(json_encode($params));
    $pool = getPool();
    _log('mq push getPool'); 
    _log('mq push connection');
    $connection = $pool->get();
    try{
        $channel = $connection->channel();
        $pushResult = $channel->basic_publish($msg, $exchangeName, $routingKey);
    }catch(\Throwable $e){
        co::sleep(1);
        $connection->reconnect();
        $channel = $connection->channel();
        $pushResult = $channel->basic_publish($msg, $exchangeName, $routingKey);
    }
    
    _log('mq push result',['pushResult'=>$pushResult,'c'=>$c,'params'=>$params]);
    $channel->close();
    $pool->put($connection);
}
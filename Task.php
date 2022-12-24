<?php
use Swoole\Coroutine as co;
require "app/mq.php"; 
function worker(){ 
     
    $maxCo = 100;
    $maxCo = 100; 
    $callbackCo = new co\Channel(1);
    $errorCo = new co\Channel(1);
    $errorNotify = function(){

    };
        
    // });
    $consumerChannel = new co\Channel(2);
    go(function() use($consumerChannel,$errorCo,&$errorNotify){
        while(1){
            try{
                mq_consumer($consumerChannel); 
            }catch(\Throwable $e){ 
                echo 'mq_consumer Error'.PHP_EOL;
                $errorCo->push($e);
                co::sleep(10);
                #$errorCo->push($e);
                #throw $e;
            }
        }
            
        
        
    }); 
    $taskNumber = 10; 
    for ($i = 0; $i < $maxCo; ++$i){
        go(function () use ($consumerChannel,$callbackCo,$errorCo,&$errorNotify) { 
            try{
                for ($i = 0;    ; ++$i) { 
                    $mes = $consumerChannel->pop();
                    
                    $messageBody = @json_decode($mes->body,true);
           
                    $result = curl($messageBody); 
                    if($result === false){
                        $delivery_tag = $mes->delivery_info['delivery_tag'];
                        echo 'nack';
                        $mes->delivery_info['channel']->basic_nack($delivery_tag,false,true); 
                        continue;
                    }
                    _log('mq_back',$messageBody);  
                    $callback = $messageBody['callback'];
                    if(isset($callback['data'])){
                        $result['callBackData'] = $callback['data'];
                    }  
                    mq_push(
                        ['exchange'=>$messageBody['exchange'],'routingKey'=>$messageBody['routingKey']],
                        $result
                    ); 
                    _log('mq_push_end',$messageBody);  
                    $delivery_tag = $mes->delivery_info['delivery_tag'];
                    $mes->delivery_info['channel']->basic_ack($delivery_tag); 
                    _log('mq_ack_end',$messageBody);  
                }
            }catch(\Throwable $e){ 
                $errorCo->push($e); 
            }
            
            #$buffer->close();
        });
    } 
    $error = $errorCo->pop();
    throw $error;
    echo 'end error'.PHP_EOL;
    co::sleep(1000);
    // return;
    // $coNumber = 100;
    // for($i=0;$i<$coNumber;$i++){
    //     go(function(){
    //         mq_consumer();
    //     });
    // }
    // echo 'worker';
    // sleep(1);
}
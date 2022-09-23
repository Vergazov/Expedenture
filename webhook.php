<?php 

require_once 'lib.php';

$requesthook = file_get_contents('php://input');
$hook = json_decode($requesthook);
$objectLink = $hook->events[0]->meta->href;
$accountId = $hook->events[0]->accountId; 

//$objectLink = 'https://online.moysklad.ru/api/remap/1.2/entity/cashout/411ffd00-1baf-11ed-0a80-0e67001f2f74';
//$accountId = '35036f89-c946-11e8-9109-f8fc00007a53'; 

$app = AppInstance::loadApp($accountId);
$object = JsonApi()->getEntity($objectLink);


$objectInfo = [];
if(isset($object->description)){
    $objectInfo['comment'] = mb_strtolower($object->description);
}
if(isset($object->paymentPurpose)){
    $objectInfo['purpose'] = mb_strtolower($object->paymentPurpose);
}
if(isset($object->agent)){
    $counterpartyObject = JsonApi()->getEntity($object->agent->meta->href);
    $objectInfo['counterparty'] = $counterpartyObject->id;
}
if(isset($object->project)){
    $projectObject = JsonApi()->getEntity($object->project->meta->href);
    $objectInfo['project'] = $projectObject->id;
}

$rulesInfo = JsonApi()->parseRules($app->rules,$objectInfo);
$res_arr = JsonApi()->search_difference($rulesInfo,$objectInfo);

if($res_arr){
    $body = [
        'expenseItem'=>[
            'meta'=>[
                'href'=>'https://online.moysklad.ru/api/remap/1.2/entity/expenseitem/' . $res_arr,
                'metadataHref' => 'https://online.moysklad.ru/api/remap/1.2/entity/expenseitem/metadata',
                'type'=>'expenseitem',
                'mediaType'=>'application/json'
            ]
        ]
    ];               
    JsonApi()->change($objectLink,$body);    
}


//echo '<hr>';
//echo 'Фильтр rulesInfo: ';
//debug($rulesInfo);
//echo '<hr>';
//echo 'Вебхук objectInfo: ';
//debug($objectInfo);
////echo '<hr>';
////debug($app->rules);
//echo '<hr>';
//debug($app);



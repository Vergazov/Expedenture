<?php
// ini_set('display_errors', 'On'); // сообщения с ошибками будут показываться
//error_reporting(E_ALL); // E_ALL - отображаем ВСЕ ошибки   
require_once 'lib.php';
//var_dump($_POST);
$counterpartiesValues = json_decode($_POST['counterpartiesValues'], true);
$projectsValues = json_decode($_POST['projectsValues'], true);
$expenseitemsValues = json_decode($_POST['expenseitemsValues'], true);
$operand1Values = json_decode($_POST['operand1Values'], true);
$operand2Values = json_decode($_POST['operand2Values'], true);
$operand3Values = json_decode($_POST['operand3Values'], true);
$loadedAllData = json_decode($_POST['loadedAllData'], true);
$startPeriod = $_POST['start-period'] . ' 00:00:00.000';
$endPeriod = $_POST['end-period'] . ' 23:59:00.000';
$accountId = $_POST['accountId'];


//$accountId = '35036f89-c946-11e8-9109-f8fc00007a53';
//$startPeriod = '2021-08-22 00:00:00.000';
//$endPeriod = '2022-08-23 23:59:00.000';

$app = AppInstance::loadApp($accountId);

$el = strtotime($startPeriod);
$el2 = strtotime($endPeriod);
$maxPeriod = $el2-$el;
$rules = $app->rules;
if($_POST['start-period'] == "" or $_POST['end-period'] == ""){
    $errorMessage = 'Ошибка! Введите период!';
    require 'iframe.html';
    exit;
}
if($maxPeriod > 31622340) {
    $errorMessage = 'Ошибка, выбранный период не должен превышать 1 год';
    require 'iframe.html';
    exit;
}
if($app->rules == []){
    $errorMessage = 'Ошибка, внесите хотя бы одно правило';
    require 'iframe.html';
    exit;
}


$filter = urlencode('moment>=' . $startPeriod . ';moment<=' . $endPeriod );

$cashoutByMoment = JsonApi()->getCashoutByMoment($filter);
$paymentoutByMoment = JsonApi()->getPaymentoutByMoment($filter);
//debug($cashoutByMoment);
$allDocuments = array_merge($cashoutByMoment,$paymentoutByMoment);
//debug($allDocuments);

$filteredDocuments = JsonApi()->parseDocumentsArr($allDocuments);
//debug($filteredDocuments);

$rulesInfo = JsonApi()->parseRulesAll($app->rules);
//echo 'Фильтры';
//debug($rulesInfo);
//echo '<hr>';

$res = JsonApi()->search_differenceAll($rulesInfo, $filteredDocuments);
//debug($res);
if($res != []){

    foreach($res['hrefs'] as $item){
        $href = array_shift($res['hrefs']);
        $expenseitem = array_shift($res['expenseitems']);
        $body = [
        'expenseItem'=>[
            'meta'=>[
                'href'=>'https://online.moysklad.ru/api/remap/1.2/entity/expenseitem/' . $expenseitem,
                'metadataHref' => 'https://online.moysklad.ru/api/remap/1.2/entity/expenseitem/metadata',
                'type'=>'expenseitem',
                'mediaType'=>'application/json'
            ]
        ]
    ];               
    JsonApi()->change($href,$body);
    }
    $rules = $app->rules;
    $successMessage = 'Успешно';
    require 'iframe.html';
}









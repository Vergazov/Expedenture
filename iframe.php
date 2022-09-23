<?php
require_once 'lib.php';
ini_set('memory_limit', '1024M');

$contextName = 'IFRAME';
require_once 'user-context-loader.inc.php';

$app = AppInstance::loadApp($accountId);
$rules = $app->rules;

$isSettingsRequired = $app->status != AppInstance::ACTIVATED;

$counterparties = str_replace("'", "\'", jsonApi()->counterparties());
$counterpartiesValues = [];
array_push($counterpartiesValues, ["name" => '', "id" => '']);
foreach ($counterparties as $v) {
    array_push($counterpartiesValues, ["name" => $v->name, "id" => $v->id, "type" => $v->meta->type]);
}

$projects = jsonApi()->projects();
$projectsValues = [];
array_push($projectsValues, ["name" => '', "id" => '']);
foreach ($projects as $v) {
    array_push($projectsValues, ["name" => $v->name, "id" => $v->id]);
}

$expenseitems = jsonApi()->expenseitems();
$expenseitemsValues = [];
foreach ($expenseitems as $v) {
    if ($v->name != 'Списания') {
        array_push($expenseitemsValues, ["name" => $v->name, "id" => $v->id]);
    }
}

if ($employee->uid == 'admin@sloudel11') {
	#echo json_encode($counterparties);
}

if (!isset($app->createPaymentsExpenseitem)) {
	$app->createPaymentsExpenseitem = $expenseitemsValues[0]['id'];
	$app->persist();
}
$createPaymentsExpenseitem = $app->createPaymentsExpenseitem;

$operand1Values = array("", "И", "ИЛИ", "И НЕ");
$operand2Values = array("", "И СОДЕРЖИТ", "И НЕ СОДЕРЖИТ");
$operand3Values = array("", "И СОДЕРЖИТ", "И НЕ СОДЕРЖИТ");

if (!isset($app->uid)) {
	$app->uid = $employee->uid;
	$app->email = $employee->email;
	$app->persist();
}

$periodValues = [];
foreach ($app->period as $k => $v) {
    $active = false;
    if ($v == $app->chosenPeriod) {$active = true;}
    array_push($periodValues, ["name" => $v, "active" => $active]);
}

require 'iframe.html';
//require_once 'webhook.php';
//require_once 'start-period.php';
//debug($app);









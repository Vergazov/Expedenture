<?php

require_once 'lib.php';
ini_set('memory_limit', '1024M');

$accountId = $_POST['accountId'];
$app = AppInstance::loadApp($accountId);
$rules = $app->rules;

$isSettingsRequired = $app->status != AppInstance::ACTIVATED;

$counterparties = jsonApi()->counterparties(true);
$counterpartiesValues = [];
array_push($counterpartiesValues, ["name" => '', "id" => '']);
foreach ($counterparties as &$v) {
    array_push($counterpartiesValues, ["name" => &$v->name, "id" => &$v->id, "type" => &$v->meta->type]);
}
//echo count($counterparties);
$organizations = jsonApi()->getObjects('organization');
foreach ($organizations as &$v) {
    array_push($counterpartiesValues, ["name" => &$v->name, "id" => &$v->id, "type" => &$v->meta->type]);
}
$employees = jsonApi()->getObjects('employee');
foreach ($employees as &$v) {
    array_push($counterpartiesValues, ["name" => &$v->name, "id" => &$v->id, "type" => &$v->meta->type]);
}


$projects = jsonApi()->projects(true);
$projectsValues = [];
array_push($projectsValues, ["name" => '', "id" => '']);
foreach ($projects as &$v) {
    array_push($projectsValues, ["name" => &$v->name, "id" => &$v->id]);
}

$expenseitems = jsonApi()->expenseitems(true);
$expenseitemsValues = [];
foreach ($expenseitems as &$v) {
    if ($v->name != 'Списания') {
        array_push($expenseitemsValues, ["name" => &$v->name, "id" => &$v->id]);
    }
}

$operand1Values = array("", "И", "ИЛИ", "И НЕ");
$operand2Values = array("", "И СОДЕРЖИТ", "И НЕ СОДЕРЖИТ");
$operand3Values = array("", "И СОДЕРЖИТ", "И НЕ СОДЕРЖИТ");

$previousStart = $app->previousStart;
if (!is_string($previousStart)) {
    $previousStart = date("d.m.Y H:i:s", $previousStart);
}
$nextStart = $app->nextStart;
if (!is_string($nextStart)) {
    $nextStart = date("d.m.Y H:i:s", $nextStart);
}



if (!isset($app->uid)) {
	$app->uid = $employee->uid;
	$app->email = $employee->email;
	$app->persist();
}

$periodValues = [];
foreach ($app->period as $k => &$v) {
    $active = false;
    if ($v == $app->chosenPeriod) {$active = true;}
    array_push($periodValues, ["name" => &$v, "active" => $active]);
}
if ($employee->uid == 'admin@sloudel' and !in_array('Час (с 9:00 до 21:00)', $app->period)) {
	unset($app->period[0]);
	$periodValues = [['name' => '----', 'active' => false], ['name' => 'Час (с 9:00 до 21:00)', 'active' => true]];
	foreach ($app->period as $k => &$v) {
		array_push($periodValues, ["name" => &$v, "active" => false]);
	}
	array_unshift($app->period, '----', 'Час (с 9:00 до 21:00)');
	$app->chosenPeriod = 'Час (с 9:00 до 21:00)';
	$app->persist();
}
$loadedAllData = true;

require 'iframe.html';








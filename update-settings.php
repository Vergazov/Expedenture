<?php
//ini_set('display_errors', 'On'); // сообщения с ошибками будут показываться
//error_reporting(E_ALL); // E_ALL - отображаем ВСЕ ошибки

require_once 'lib.php';

$counterpartiesValues = json_decode($_POST['counterpartiesValues'], true);
$projectsValues = json_decode($_POST['projectsValues'], true);
$expenseitemsValues = json_decode($_POST['expenseitemsValues'], true);
$operand1Values = json_decode($_POST['operand1Values'], true);
$operand2Values = json_decode($_POST['operand2Values'], true);
$operand3Values = json_decode($_POST['operand3Values'], true);
$loadedAllData = json_decode($_POST['loadedAllData'], true);
$periodValues = json_decode($_POST['periodValues'], true);
$employee = json_decode($_POST['employee'], true);

$counterparty = $_POST['counterparty'];
$operand1 = $_POST['operand1'];
$project = $_POST['project'];
$operand2 = $_POST['operand2'];
$comment = $_POST['comment'];
$operand3 = $_POST['operand3'];
$purpose = $_POST['purpose'];
$expenseitem = $_POST['expenseitem'];
$createPaymentsEnabledSettings = $_POST['createPaymentsEnabledSettings'];
$createPaymentsEnabled = $_POST['createPaymentsEnabled'];
$createPaymentsExpenseitem = $_POST['createPaymentsExpenseitem'];

// loginfo('UPDATE-SETTINGS', "Update info message: $infoMessage, counterparty: $counterparty");

$accountId = $_POST['accountId'];

$app = AppInstance::loadApp($accountId);

$rules = $app->rules;

if ($counterparty or $project) {
	if ($counterparty != '') {
		$agent = jsonApi()->getObject('counterparty', $counterparty);
		if (!isset($agent)) {
			$agent = jsonApi()->getObject('employee', $counterparty);
		}
		if (!isset($agent)) {
			$agent = jsonApi()->getObject('organization', $counterparty);
		}
		$agent = ['id' => $agent->id, 'name' => $agent->name, 'type' => $agent->meta->type];
	}
	if ($project != '') {
		$project = jsonApi()->getObject('project', $project);
		$project = ['id' => $project->id, 'name' => $project->name];
	}
	if ($expenseitem != '') {
		$expenseitem = jsonApi()->getObject('expenseitem', $expenseitem);
		$expenseitem = ['id' => $expenseitem->id, 'name' => $expenseitem->name];
	}
    $rule = [
        "number" => sprintf("%04d", count($app->rules) + 1),
        "counterparty" => $agent,
        "operand1" => $operand1,
        "project" => $project,
        "operand2" => $operand2,
        "comment" => $comment,
        "operand3" => $operand3,
        "purpose" => $purpose,
        "expenseitem" => $expenseitem,
        "delete_button" => "Удалить"
    ];
    array_push($app->rules, $rule);

    $notify = $app->status != AppInstance::ACTIVATED;
    $app->status = AppInstance::ACTIVATED;

    vendorApi()->updateAppStatus(cfg()->appId, $accountId, $app->getStatusName());
	
    $app->persist();
	
	$rules = $app->rules;
	
	$successMessage = 'Добавлено новое правило!';
    require 'iframe.html';
} else {
	$errorMessage = 'Ошибка! Введите хотябы один параметр выборки: Контрагент или Проект!';
    require 'iframe.html';
}


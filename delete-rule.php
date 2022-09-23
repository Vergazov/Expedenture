<?php

require_once 'lib.php';

$counterpartiesValues = json_decode($_POST['counterpartiesValues'], true);
$projectsValues = json_decode($_POST['projectsValues'], true);
$expenseitemsValues = json_decode($_POST['expenseitemsValues'], true);
$operand1Values = json_decode($_POST['operand1Values'], true);
$operand2Values = json_decode($_POST['operand2Values'], true);
$operand3Values = json_decode($_POST['operand3Values'], true);
$loadedAllData = json_decode($_POST['loadedAllData'], true);

$rule_number = $_POST['rule_number'];

// loginfo('UPDATE-SETTINGS', "Update info message: $infoMessage, counterparty: $counterparty");

$accountId = $_POST['accountId'];

$app = AppInstance::loadApp($accountId);

foreach ($app->rules as $rule_key => $rule_value) {
    if (in_array($rule_number, $rule_value)) {
        unset($app->rules[$rule_key]);
        break;
    }
}
$num = 0;
foreach ($app->rules as $rule_key => $rule_value) {
    $num++;
    $app->rules[$rule_key]['number'] = sprintf("%04d", $num);
}
if (num == 0) {
    $notify = $app->status != AppInstance::ACTIVATED;
    $app->status = AppInstance::SETTINGS_REQUIRED;
    vendorApi()->updateAppStatus(cfg()->appId, $accountId, $app->getStatusName());
}

$app->persist();

$rules = $app->rules;

$successMessage = 'Удалено правило!';
require 'iframe.html';

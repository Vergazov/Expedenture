<?php

require_once 'lib.php';

$method = $_SERVER['REQUEST_METHOD'];
$path = $_SERVER['PATH_INFO'];

loginfo("MOYSKLAD => APP", "Received: method=$method, path=$path");

$pp = explode('/', $path);
$n = count($pp);
$appId = $pp[$n - 2];
$accountId = $pp[$n - 1];

loginfo("MOYSKLAD => APP", "Extracted: appId=$appId, accountId=$accountId");

$app = AppInstance::load($appId, $accountId);
$replyStatus = true;

$cashoutBody = [
    "url" => "http://51.250.104.99/php-apps/app/webhook.php",
    "action"  => "CREATE",
    "entityType"  => "cashout"];

$paymentoutBody = [
    "url" => "http://51.250.104.99/php-apps/app/webhook.php",
    "action"  => "CREATE",
    "entityType"  => "paymentout"];

switch ($method) {
    case 'PUT':
        $requestBody = file_get_contents('php://input');

        loginfo("MOYSKLAD => APP", "Request body: " . print_r($requestBody, true));

        $data = json_decode($requestBody);

        $appUid = $data->appUid;
        $accessToken = $data->access[0]->access_token;

        loginfo("MOYSKLAD => APP", "Received access_token: appUid=$appUid, access_token=$accessToken)");

        $app->accessToken = $accessToken;
        if (!$app->getStatusName()) {
            $app->status = AppInstance::SETTINGS_REQUIRED;
        }
		if (!empty($app->rules)) {
			$app->status = AppInstance::ACTIVATED;
		}
        $app->persist();
        JsonApi()->createWebhook($cashoutBody);
        JsonApi()->createWebhook($paymentoutBody);
        break;
    case 'GET':
        break;
    case 'DELETE':
        $app->delete();
        $replyStatus = false;
        break;
}

if (!$app->getStatusName()) {
    http_response_code(404);
} else if ($replyStatus) {
    header("Content-Type: application/json");
    echo '{"status": "' . $app->getStatusName() . '"}';
}



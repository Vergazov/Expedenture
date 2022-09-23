<?php //

use \Firebase\JWT\JWT;

require_once 'jwt.lib.php';

if (!isset($dirRoot)) {
    $dirRoot = '/home/admin/php-apps/app/';
}

//
//  Config
//

class AppConfig {

    var $appId = 'APP-ID';
    var $appUid = 'APP-UID';
    var $secretKey = 'SECRET-KEY';

    var $appBaseUrl = 'APP-BASE-URL';

    var $moyskladVendorApiEndpointUrl = 'https://online.moysklad.ru/api/vendor/1.0';
    var $moyskladJsonApiEndpointUrl = 'https://online.moysklad.ru/api/remap/1.2';

    public function __construct(array $cfg)
    {
        foreach ($cfg as $k => $v) {
            $this->$k = $v;
        }
    }
}

$cfg = new AppConfig(require('config.php'));

function cfg(): AppConfig {
    return $GLOBALS['cfg'];
}

//
//  Vendor API 1.0
//

class VendorApi {

    function context(string $contextKey) {
        return $this->request('POST', '/context/' . $contextKey);
    }

    function updateAppStatus(string $appId, string $accountId, string $status) {
        return $this->request('PUT',
            "/apps/$appId/$accountId/status",
            "{\"status\": \"$status\"}");
    }

    private function request(string $method, $path, $body = null) {
        return makeHttpRequest(
            $method,
            cfg()->moyskladVendorApiEndpointUrl . $path,
            buildJWT(),
            $body);
    }

}

function makeHttpRequest(string $method, string $url, string $bearerToken, $body = null) {
    loginfo("APP => MOYSKLAD", "Send: $method $url\n" . json_encode($body) . $bearerToken);
	if (stripos($url, 'status') == false) {
		$body = json_encode($body);
	}
    $opts = $body
        ? array('http' =>
            array(
                'method'  => $method,
                'header'  => array('Authorization: Bearer ' . $bearerToken, "Content-type: application/json"),
                'content' => $body,
                'ignore_errors' => true
            )
        )
        : array('http' =>
            array(
                'method'  => $method,
                'header'  => 'Authorization: Bearer ' . $bearerToken
            )
        ); 
   
    $context = stream_context_create($opts);
    $result = file_get_contents($url, false, $context);
    return json_decode($result);
}

$vendorApi = new VendorApi();

function vendorApi(): VendorApi {
    return $GLOBALS['vendorApi'];
}

function buildJWT() {
    $token = array(
        "sub" => cfg()->appUid,
        "iat" => time(),
        "exp" => time() + 300,
        "jti" => bin2hex(random_bytes(32))
    );
    return JWT::encode($token, cfg()->secretKey);
}


//
//  JSON API 1.2
//


class JsonApi {

    private $accessToken;

    function __construct(string $accessToken) {
        $this->accessToken = $accessToken;
    }

    function counterparties($all = false) {
		if ($all == false) {
			return jsonApi()->getObject(
				$entity='counterparty'
			)->rows;
		}
        return jsonApi()->getObjects(
			$entityType='counterparty'
		);
    }

    function projects($all = false) {
		if ($all == false) {
			return jsonApi()->getObject(
				$entity='project'
			)->rows;
		}
        return jsonApi()->getObjects(
			$entityType='project'
		);
    }

    function expenseitems($all = false) {
		if ($all == false) {
			return jsonApi()->getObject(
				$entity='expenseitem'
			)->rows;
		}
        return jsonApi()->getObjects(
			$entityType='expenseitem'
		);
    }    


    function getObjects($entityType = '', $filters = false, $url = false) {
        if ($filters !== false) {
            $filters = getFilters($filters);
        }
        if ($url === false) {
            $url = cfg()->moyskladJsonApiEndpointUrl . "/entity/$entityType$filters";
        }
        $response = makeHttpRequest(
            'GET',
            $url,
            $this->accessToken);
        if (property_exists($response->meta, 'nextHref')) {
            return array_merge($response->rows, jsonApi()->getObjects('', false, urldecode($response->meta->nextHref)));
        } else {
            return $response->rows;
        }
    }
    
    function getCashoutByMoment($filter){
        $response = makeHttpRequest(
            'GET',
            cfg()->moyskladJsonApiEndpointUrl . "/entity/cashout?filter=" . $filter,
            $this->accessToken);
        
        return $response->rows;
    }
    
    function getPaymentoutByMoment($filter){
         $response = makeHttpRequest(
            'GET',
            cfg()->moyskladJsonApiEndpointUrl . "/entity/paymentout?filter=" . $filter,
            $this->accessToken);
         
         return $response->rows;
    }
    
    function parseDocumentsArr($allDocuments){
        $resultDocument = [];
        $i = 0;
        foreach($allDocuments as $document){
            
            if(isset($document->meta->href)){
                $resultDocument[$i]['href'] = $document->meta->href;
            }
            if(isset($document->description)){
                $resultDocument[$i]['comment'] = mb_strtolower($document->description);
            }
            if(isset($document->paymentPurpose)){
                $resultDocument[$i]['purpose'] = mb_strtolower($document->paymentPurpose);
            }
            if(isset($document->agent)){
                $counterpartyObject = JsonApi()->getEntity($document->agent->meta->href);
                $resultDocument[$i]['counterparty'] = $counterpartyObject->id;
            }
            if(isset($document->project)){
                $projectObject = JsonApi()->getEntity($document->project->meta->href);
                $resultDocument[$i]['project'] = $projectObject->id;
            }
            
            $i++;
        }
    
    return $resultDocument;
    }
    
    
    
    function change($url,$body){
        return makeHttpRequest(
            'PUT',
            $url,
            $this->accessToken,
            $body);
    }
    
    function createWebhook($body) {
        return makeHttpRequest(
            'POST',
            cfg()->moyskladJsonApiEndpointUrl . "/entity/webhook",
            $this->accessToken,
            $body);
    }
    

    function updateOrCreateObjects($entityType, $body) {
        $proceed_body = array_slice($body, 0, 1000);
        makeHttpRequest(
            'POST',
            cfg()->moyskladJsonApiEndpointUrl . "/entity/$entityType/",
            $this->accessToken,
            $proceed_body
        );
        $body = array_slice($body, 1000);
        if (count($body) > 0) {
            return jsonApi()->updateOrCreateObjects($entityType, $body);
        }
        return 0;
    }    
     
    function parseRules($rules,$objectinfo){
        
        $result = [];   
        $rulesInfo = [];
        $i = 0;
        foreach($rules as $rule){
            
            $rulesInfo[$i]['expenseitem'] = $rule['expenseitem']['id'];
            
            if(array_key_exists('comment', $rule) and $rule['comment'] != '' ){
                if($rule['operand2'] == 'И СОДЕРЖИТ'){
                    $rulesInfo[$i]['comment'] = mb_strtolower($rule['comment']);
                }else{
                    if($rule['operand2'] == 'И НЕ СОДЕРЖИТ'){
                        if($rule['comment'] === $objectinfo['comment']){
                        continue;
                    }
                    }
                }                
            }
            if(array_key_exists('purpose', $rule) and $rule['purpose'] != '' ){
                if($rule['operand3'] == 'И СОДЕРЖИТ'){
                    $rulesInfo[$i]['purpose'] = mb_strtolower($rule['purpose']);
                }
                 if($rule['operand3'] == 'И НЕ СОДЕРЖИТ'){
                    if($rule['purpose'] === $objectinfo['purpose']){
                    continue;
                }
                }                
            }
            
            if($rulesInfo != []){
                $result = $rulesInfo[$i];    
            }
                        
            if(array_key_exists('counterparty', $rule) and $rule['counterparty'] != '' ){
                $rulesInfo[$i]['counterparty'] = $rule['counterparty']['id'];
            }
            
            if(array_key_exists('project', $rule) and $rule['project'] != '' ){
                 
                if($rule['operand1'] == ''){
                    $rulesInfo[$i]['project'] = $rule['project']['id'];    
                }
                if($rule['operand1'] == 'И'){
                    $rulesInfo[$i]['project'] = $rule['project']['id'];
                }
                if($rule['operand1'] == 'ИЛИ'){
                     
                    $rulesInfo[$i]['project'] = $rule['project']['id'];                    
                    $i++;
                    
                    if(!empty($result)){
                        foreach($result as $key => $value){
                            $rulesInfo[$i][$key] = $value;
                        }
                    }
                    $rulesInfo[$i]['project'] = $rule['project']['id'];
//                    
                    $i++;
                    
                    if(!empty($result)){
                        foreach($result as $key => $value){
                            $rulesInfo[$i][$key] = $value;
                        }
                    }
                    $rulesInfo[$i]['counterparty'] = $rule['counterparty']['id'];                    
                } 
                if($rule['operand1'] == 'И НЕ'){
                    continue;
                }                      
            }                  
            $i++;
        }
        return $rulesInfo;                 
    }

function parseRulesAll($rules){
        
        $result = [];   
        $rulesInfo = [];
        $i = 0;
        foreach($rules as $rule){
            
            $rulesInfo[$i]['expenseitem'] = $rule['expenseitem']['id'];
            
            if(array_key_exists('comment', $rule) and $rule['comment'] != '' ){
                if($rule['operand2'] == 'И СОДЕРЖИТ'){
                    $rulesInfo[$i]['comment'] = mb_strtolower($rule['comment']);
                }              
            }
            if(array_key_exists('purpose', $rule) and $rule['purpose'] != '' ){
                if($rule['operand3'] == 'И СОДЕРЖИТ'){
                    $rulesInfo[$i]['purpose'] = mb_strtolower($rule['purpose']);
                }                               
            }
            
            if($rulesInfo != []){
                $result = $rulesInfo[$i];    
            }
                        
            if(array_key_exists('counterparty', $rule) and $rule['counterparty'] != '' ){
                $rulesInfo[$i]['counterparty'] = $rule['counterparty']['id'];
            }
            
            if(array_key_exists('project', $rule) and $rule['project'] != '' ){
                 
                if($rule['operand1'] == ''){
                    $rulesInfo[$i]['project'] = $rule['project']['id'];    
                }
                if($rule['operand1'] == 'И'){
                    $rulesInfo[$i]['project'] = $rule['project']['id'];
                }
                if($rule['operand1'] == 'ИЛИ'){
                     
                    $rulesInfo[$i]['project'] = $rule['project']['id'];                    
                    $i++;
                    
                    if(!empty($result)){
                        foreach($result as $key => $value){
                            $rulesInfo[$i][$key] = $value;
                        }
                    }
                    $rulesInfo[$i]['project'] = $rule['project']['id'];
//                    
                    $i++;
                    
                    if(!empty($result)){
                        foreach($result as $key => $value){
                            $rulesInfo[$i][$key] = $value;
                        }
                    }
                    $rulesInfo[$i]['counterparty'] = $rule['counterparty']['id'];                    
                } 
                if($rule['operand1'] == 'И НЕ'){
                    continue;
                }                      
            }                  
            $i++;
        }
        return $rulesInfo;                 
    }    

    
    
    function  search_difference($rulesInfo,$objectInfo){ 
        $res = [];
        foreach($rulesInfo as $rule){
            $expenseitem = array_shift($rule);
            if(count($rule) == count($objectInfo)){                
                foreach ($rule as $item){                    
                    if(array_diff_assoc($objectInfo,$rule) == []){
                        return $expenseitem;
                    }
                }
            }
        }
    }
    
    
    function  search_differenceAll($rulesInfo, $filteredDocuments){ 
        
            foreach ($rulesInfo as $rule){
                $expenseitem = array_shift($rule);
                foreach($filteredDocuments as $document){
                    $href = array_shift($document);
                    if(array_diff_assoc($document,$rule) == []){
                        $res['hrefs'][] = $href;
                        $res['expenseitems'][] = $expenseitem;
                        array_unshift($document,$href);
                    }
                    else{
                        array_unshift($document,$href);
                    }
                    
                }
            }
            return $res;
    }
    
    

    function getObject($entity, $objectId = '') {
		if ($objectId == '') {
			$objectId = '?order=name';
		}
        return makeHttpRequest(
            'GET',
            cfg()->moyskladJsonApiEndpointUrl . "/entity/$entity/$objectId",
            $this->accessToken);
    }
    
    function getEntity($url) {
        return makeHttpRequest(
            'GET',
            $url,
            $this->accessToken);
    } 

}

function jsonApi($app=''): JsonApi {
    if ($app != '') {
		$GLOBALS['currentAppInstance'] = $app;
        $GLOBALS['jsonApi'] = new JsonApi($app->accessToken);
    } else {
        $GLOBALS['jsonApi'] = new JsonApi(AppInstance::get()->accessToken);
	}
    return $GLOBALS['jsonApi'];
}

//
//  Logging
//

function loginfo($name, $msg) {
    global $dirRoot;
    $logDir = $dirRoot . 'logs';
    @mkdir($logDir);
    file_put_contents($logDir . '/log.txt', date(DATE_W3C) . ' [' . $name . '] '. $msg . "\n", FILE_APPEND);
}

function debug($data){
    echo '<pre>';
    print_r($data);
    echo '</pre>';
}

//
//  AppInstance state
//

$currentAppInstance = null;

class AppInstance {

    const DELETED = -1;
    const UNKNOWN = 0;
    const SETTINGS_REQUIRED = 1;
    const ACTIVATED = 100;

    var $appId;
    var $accountId;
    var $rules = array();
    var $period = array("----", "День", "Неделю", "Месяц", "3 месяца", "6 месяцев", "Год");
    var $chosenPeriod = '----';
    var $previousStart = 'Программа не запускалась!';
    var $nextStart = 'Не определен!';

    var $accessToken;

    var $status = AppInstance::UNKNOWN;

    static function get() {
        $app = $GLOBALS['currentAppInstance'];
        if (!$app) {
            throw new InvalidArgumentException("There is no current app instance context");
        }
        return $app;
    }

    public function __construct($appId, $accountId)
    {
        $this->appId = $appId;
        $this->accountId = $accountId;
    }

    function getStatusName() {
        switch ($this->status) {
            case self::SETTINGS_REQUIRED:
                return 'SettingsRequired';
            case self::ACTIVATED:
                return 'Activated';
            case self::DELETED:
                return 'Deleted';
        }
        return null;
    }

    function persist() {
        @mkdir('data');
        file_put_contents($this->filename(), serialize($this));
    }

    function delete() {
        $this->status = AppInstance::DELETED;
		$this->persist();
		 @unlink($this->filename());
    }

    private function filename() {
        return self::buildFilename($this->appId, $this->accountId);
    }

    private static function buildFilename($appId, $accountId) {
        return $GLOBALS['dirRoot'] . "data/$appId.$accountId.app";
    }

    static function loadApp($accountId): AppInstance {
        return self::load(cfg()->appId, $accountId);
    }

    static function loadAppByFileDir($fileName) {
        $data = @file_get_contents($fileName);
        $app = unserialize($data);
        $GLOBALS['currentAppInstance'] = $app;
        return $app;
    }

    static function load($appId, $accountId): AppInstance {
        $data = @file_get_contents(self::buildFilename($appId, $accountId));
        if ($data === false) {
            $app = new AppInstance($appId, $accountId);
        } else {
            $app = unserialize($data);
        }
        $GLOBALS['currentAppInstance'] = $app;
        return $app;
    }

}
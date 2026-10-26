<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$phase='BOOTSTRAP';$job=[];
try{
    if(PHP_VERSION!=='8.4.27' || PHP_INT_SIZE!==8)throw new LogicException('M1_PHP_8_4_27_REQUIRED');
    foreach(['curl_exec','curl_multi_exec','fsockopen','pfsockopen','stream_socket_client','proc_open','exec'] as $fn)if(function_exists($fn))throw new LogicException('M1_CHILD_NETWORK_OR_EXEC_NOT_DISABLED');
    if(filter_var(ini_get('allow_url_fopen'),FILTER_VALIDATE_BOOLEAN))throw new LogicException('M1_CHILD_URL_STREAMS_ENABLED');
    if(!extension_loaded('pdo_mysql') || !extension_loaded('mbstring') || !extension_loaded('ctype'))throw new LogicException('M1_PDO_MYSQL_MBSTRING_CTYPE_REQUIRED');
    $raw=stream_get_contents(STDIN,16385);if(strlen($raw)>16384)throw new InvalidArgumentException('M1_INPUT_TOO_LARGE');
    $job=json_decode($raw,true,32,JSON_THROW_ON_ERROR);
    if(!is_array($job) || !is_int($job['actor']??null) || $job['actor']<1)throw new InvalidArgumentException('M1_ACTOR_REQUIRED');
    $fields=match($job['mode']??null){
        'review_inspect'=>['mode','actor','job'],'review_create'=>['mode','actor','job','request_key','reason'],
        'decision_read'=>['mode','actor','case'],'decision_apply'=>['mode','actor','case','key','revision','verdict','note'],
        'recovery_read'=>['mode','actor','case'],'recovery_queue'=>['mode','actor','case','key','revision'],
        'wallet_report'=>['mode','actor','scope','partner'],default=>throw new InvalidArgumentException('M1_OPERATION_INVALID')};
    foreach($job as $key=>$value)if(!in_array($key,$fields,true))throw new InvalidArgumentException('M1_UNKNOWN_PARAMETER');
    foreach(['job','case'] as $key)if(isset($job[$key]) && (!is_int($job[$key]) || $job[$key]<1 || $job[$key]>2147483647))throw new InvalidArgumentException('M1_ID_INVALID');
    foreach(['request_key','reason','key','revision','verdict','note'] as $key)if(isset($job[$key]) && !is_string($job[$key]))throw new InvalidArgumentException('M1_STRING_REQUIRED');
    foreach($fields as $field)if(!array_key_exists($field,$job) && !($job['mode']==='decision_read' && $field==='case'))throw new InvalidArgumentException('M1_PARAMETER_REQUIRED');
    foreach(['GF_FIN_ISOLATED','GF_FIN_PAYOUT_QA','GF_FIN_DIRECT_QA','GF_FIN_WORKER_QA'] as $flag)define($flag,true);
    $root=dirname(__DIR__);require_once $root.'/config.php';
    if(DB_NAME!=='guinchafacil_dev' || !preg_match('/^(?:127\.0\.0\.1|localhost)(?:;port=[0-9]{1,5})?$/D',DB_HOST))throw new LogicException('M1_LOCAL_DEV_DATABASE_REQUIRED');
    require_once $root.'/src/Services/Finance/GFReconciliation.php';
    require_once $root.'/src/Services/Finance/GFQaPayoutWorker.php';
    require_once $root.'/src/Services/Finance/GFPayoutReviewService.php';
    require_once $root.'/src/Services/Finance/GFPayoutDecisionService.php';
    require_once $root.'/src/Services/Finance/GFPayoutRecoveryService.php';
    require_once $root.'/src/Services/Finance/CarteiraService.php';
    require_once $root.'/src/Services/Finance/GFWalletApplicationAccess.php';
    $phase='EXECUTE_SERVICE';$db=getPDO();
    if($db->query('SELECT DATABASE()')->fetchColumn()!=='guinchafacil_dev')throw new LogicException('M1_DATABASE_MISMATCH');
    $r=match($job['mode']){
        'review_inspect'=>GFPayoutReviewService::inspect($db,$job['actor'],$job['job']),
        'review_create'=>GFPayoutReviewService::create($db,$job['actor'],$job['job'],$job['request_key'],$job['reason']),
        'decision_read'=>GFPayoutDecisionService::read($db,$job['actor'],$job['case']??null),
        'decision_apply'=>GFPayoutDecisionService::decide($db,$job['actor'],$job['case'],$job['key'],$job['revision'],$job['verdict'],$job['note']),
        'recovery_read'=>GFPayoutRecoveryService::read($db,$job['actor'],$job['case']),
        'recovery_queue'=>GFPayoutRecoveryService::queue($db,$job['actor'],$job['case'],$job['key'],$job['revision']),
        'wallet_report'=>GFWalletApplicationAccess::report($db,$job['actor'],$job['scope'],$job['partner'])};
    error_log(json_encode(['project'=>'GuinchaFacil','class'=>'GFPayoutHttpWorker','function'=>$job['mode'],'phase'=>'SERVICE_FINISHED','status'=>$r['ok']?'PASS':'REJECTED','actor'=>$job['actor'],'case'=>$job['case']??null,'job'=>$job['job']??null,'remaining_percent'=>30],JSON_THROW_ON_ERROR));
    echo json_encode($r,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);exit(0);
}catch(Throwable $e){
    $d=['project'=>'GuinchaFacil','class'=>'GFPayoutHttpWorker','function'=>(string)($job['mode']??'bootstrap'),'phase'=>$phase,'status'=>'FAIL','error'=>$e instanceof PDOException?'M1_DATABASE_ERROR':$e->getMessage(),'file'=>basename($e->getFile()),'line'=>$e->getLine(),'sqlstate'=>$e instanceof PDOException?($e->errorInfo[0]??null):null,'remaining_percent'=>30];
    error_log(json_encode($d,JSON_INVALID_UTF8_SUBSTITUTE));echo json_encode(['ok'=>false,'http'=>503,'erro'=>'executor_financeiro_indisponivel','diagnostic'=>$d],JSON_INVALID_UTF8_SUBSTITUTE);exit(1);
}

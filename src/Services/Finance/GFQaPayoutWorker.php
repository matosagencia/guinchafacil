<?php
declare(strict_types=1);
require_once __DIR__.'/GFIntegratedPayout.php';
/** Contrato de transporte SOMENTE QA: nenhuma implementacao de rede neste corte. */
interface GFQaPayoutHttp
{
    public function request(string $method,string $path,array $headers,string $body):array;
}
final class GFQaPayoutWorker
{
    public static function guard(PDO $db):void
    {
        GFIntegratedPayout::guard($db);
        if (!defined('GF_FIN_WORKER_QA') || GF_FIN_WORKER_QA!==true || function_exists('curl_exec') || filter_var(ini_get('allow_url_fopen'),FILTER_VALIDATE_BOOLEAN)) throw new LogicException('FIN_WORKER_QA_NETWORK_DISABLED_REQUIRED');
    }
    private static function log(string $phase,string $status,array $ctx=[]):void
    {
        $r=['project'=>'GuinchaFacil','class'=>self::class,'function'=>'process','component'=>'payout_worker','phase'=>$phase,'status'=>$status,'message'=>$ctx['error']??$phase,'context'=>$ctx];
        if(isset($GLOBALS['gf_fin_log_sink']))($GLOBALS['gf_fin_log_sink'])($r);else error_log(json_encode($r,JSON_THROW_ON_ERROR));
    }
    private static function context(PDO $db,int $jobId):array
    {
        // Lookup nao autoriza envio: enqueue reconfere o pedido e toda a prova sob lock.
        $q=$db->prepare('SELECT pedido_id,payload_json FROM payment_jobs WHERE id=?');$q->execute([$jobId]);$old=$q->fetch(PDO::FETCH_ASSOC);
        if(!$old)throw new DomainException('FIN_WORKER_JOB_MISSING');
        $payload=json_decode($old['payload_json'],true,512,JSON_THROW_ON_ERROR);
        $r=GFIntegratedPayout::enqueue($db,(int)$old['pedido_id'],$payload['amount']??null,$payload['platform']??null,(string)($payload['part']??''));
        if(!$r['ok'] || $r['job_id']!==$jobId || $r['queued'])throw new DomainException('FIN_WORKER_JOB_NOT_VERIFIED:'.($r['erro']??''));
        $q=$db->prepare('SELECT *, (available_at<=NOW()) AS due FROM payment_jobs WHERE id=? FOR UPDATE');$q->execute([$jobId]);$job=$q->fetch(PDO::FETCH_ASSOC);
        $payload=json_decode($job['payload_json'],true,512,JSON_THROW_ON_ERROR);
        if($job['status']==='queued' && !(int)$job['due'])throw new DomainException('FIN_WORKER_JOB_NOT_DUE');
        if(!in_array($job['status'],['queued','completed'],true) || ($payload['version']??null)!=='R11AL')throw new DomainException('FIN_WORKER_LEGACY_JOB_BLOCKED');
        $q=$db->prepare('SELECT chave_pix,chave_pix_tipo FROM guinchos WHERE id=? FOR UPDATE');$q->execute([$payload['guincho_id']]);$g=$q->fetch(PDO::FETCH_ASSOC);
        $type=match($g['chave_pix_tipo']??'') {'cpf'=>'CPF','cnpj'=>'CNPJ','email'=>'EMAIL','telefone'=>'PHONE','aleatoria'=>'PIX_CODE',default=>throw new DomainException('FIN_WORKER_PIX_TYPE_INVALID')};
        $amount=GFMoneyInput::decimal($payload['amount']);
        if(GFExactMoney::cents($amount)<100)throw new DomainException('FIN_WORKER_PROVIDER_MINIMUM_ONE_REAL');
        // Referencia e chave por job/parcela; nao se usa apenas o numero do pedido.
        $ref='GFQA'.(string)$jobId;$idempotency=hash('sha256','payout-qa-v1|'.GFIntegratedApproval::DATABASE.'|'.$jobId);
        $body=json_encode(['external_reference'=>$ref,'description'=>'GuinchaFacil QA payout','transactions'=>[['description'=>'GuinchaFacil QA payout','type'=>'pix','pix'=>['type'=>$type,'chave'=>$g['chave_pix']],'amount'=>['currency'=>'BRL','value'=>$amount],'external_reference'=>$ref]]],JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);
        $body=str_replace('"value":"'.$amount.'"','"value":'.$amount,$body,$replaced);
        if($replaced!==1)throw new LogicException('FIN_WORKER_EXACT_BODY_FAILED');
        return ['job'=>$job,'payload'=>$payload,'body'=>$body,'reference'=>$ref,'idempotency'=>$idempotency,'fingerprint'=>hash('sha256',$body)];
    }
    private static function http(GFQaPayoutHttp $http,string $method,string $path,string $body,string $key):array
    {
        $r=$http->request($method,$path,['X-Idempotency-Key'=>$key,'X-test-token'=>'true','X-enforce-signature'=>'false','Content-Type'=>'application/json'],$body);
        if(($r['error']??'')!=='' || !in_array($r['code']??0,[200,202],true) || !is_string($r['body']??null))throw new RuntimeException('FIN_WORKER_HTTP_UNCERTAIN');
        return GFExactJson::decode($r['body']);
    }
    public static function process(PDO $db,int $jobId,GFQaPayoutHttp $http,?callable $hook=null):array
    {
        $token=null;$phase='GUARD';$owned=false;
        try {
            self::guard($db);
            if($db->inTransaction())throw new LogicException('FIN_WORKER_OWNS_TRANSACTION_REQUIRED');
            $phase='CLAIM';$db->beginTransaction();$owned=true;$c=self::context($db,$jobId);
            $q=$db->prepare('SELECT *, (lease_until IS NOT NULL AND lease_until>NOW(6)) AS active FROM fin3_payout_dispatch_qa WHERE job_id=? FOR UPDATE');$q->execute([$jobId]);$d=$q->fetch(PDO::FETCH_ASSOC);
            if($d && ($d['fingerprint']!==$c['fingerprint'] || $d['request_json']!==$c['body'] || $d['idempotency_key']!==$c['idempotency']))throw new DomainException('FIN_WORKER_FROZEN_REQUEST_DRIFT');
            if($d && $d['state']==='confirmed') {
                $r=GFIntegratedPayout::confirm($db,$jobId,GFQaPayoutReceipt::confirmed($jobId,(int)$c['job']['pagamento_id'],$c['payload']['guincho_id'],$c['payload']['amount'],'qa_pix_'.hash('sha256',$d['transaction_id'])));
                if(!$r['ok'] || !$r['duplicate_verified'])throw new DomainException('FIN_WORKER_CONFIRMED_LEDGER_DRIFT');
                $db->commit();$owned=false;return ['ok'=>true,'processed'=>false,'status'=>'confirmed','duplicate_verified'=>true];
            }
            if($d && (int)$d['active']===1 && $d['claim_token']!==null) {$db->commit();$owned=false;return ['ok'=>true,'processed'=>false,'status'=>'busy'];}
            if($d && in_array($d['state'],['rejected','review'],true)) {$db->commit();$owned=false;return ['ok'=>false,'processed'=>false,'status'=>$d['state'],'erro'=>'FIN_WORKER_ADMIN_REVIEW_REQUIRED'];}
            if($c['job']['status']!=='queued' || (int)$c['job']['attempt_count']!==0)throw new DomainException('FIN_WORKER_UNTRACKED_COMPLETED_JOB');
            $token=bin2hex(random_bytes(16));
            if(!$d) {
                $db->prepare("INSERT INTO fin3_payout_dispatch_qa(job_id,idempotency_key,fingerprint,request_json,state,claim_token,lease_until,claim_count) VALUES (?,?,?,?,'sending',?,DATE_ADD(NOW(6),INTERVAL 90 SECOND),1)")->execute([$jobId,$c['idempotency'],$c['fingerprint'],$c['body'],$token]);
                $d=['payout_id'=>null,'transaction_id'=>null,'claim_count'=>0];
            } else {
                if((int)$d['claim_count']>=5) {
                    $db->prepare("UPDATE fin3_payout_dispatch_qa SET state='review',claim_token=NULL,lease_until=NULL,last_error='FIN_WORKER_RECOVERY_LIMIT_REVIEW_REQUIRED' WHERE job_id=?")->execute([$jobId]);
                    $db->commit();$owned=false;return ['ok'=>false,'processed'=>false,'status'=>'review','erro'=>'FIN_WORKER_RECOVERY_LIMIT_REVIEW_REQUIRED'];
                }
                $db->prepare("UPDATE fin3_payout_dispatch_qa SET state='sending',claim_token=?,lease_until=DATE_ADD(NOW(6),INTERVAL 90 SECOND),claim_count=claim_count+1,updated_at=NOW(6) WHERE job_id=?")->execute([$token,$jobId]);
            }
            $db->commit();$owned=false;self::log('CLAIM_COMMIT','PASS',['job_id'=>$jobId]);if($hook)$hook('after_claim');
            // Nunca manter locks de banco durante transporte.
            if(!$d['payout_id']) {
                $phase='SUBMIT';$created=self::http($http,'POST','/v1/payouts',$c['body'],$c['idempotency']);
                if($hook)$hook('after_submit');
                if(!preg_match('/^[A-Za-z0-9_-]{1,80}$/D',(string)($created['id']??'')) || ($created['external_reference']??null)!==$c['reference'] || count($created['transactions']??[])!==1)throw new DomainException('FIN_WORKER_CREATE_RESPONSE_MISMATCH');
                $t=$created['transactions'][0];
                if(!preg_match('/^[A-Za-z0-9_-]{1,80}$/D',(string)($t['id']??'')) || ($t['external_reference']??null)!==$c['reference'] || ($t['amount']['currency']??null)!=='BRL' || GFMoneyInput::decimal($t['amount']['value']??null)!==$c['payload']['amount'])throw new DomainException('FIN_WORKER_CREATE_RESPONSE_MISMATCH');
                $d['payout_id']=$created['id'];$d['transaction_id']=$t['id'];
                $q=$db->prepare('UPDATE fin3_payout_dispatch_qa SET payout_id=?,transaction_id=?,updated_at=NOW(6) WHERE job_id=? AND claim_token=? AND lease_until>NOW(6)');$q->execute([$d['payout_id'],$d['transaction_id'],$jobId,$token]);
                if($q->rowCount()!==1)throw new DomainException('FIN_WORKER_CLAIM_LOST');
            }
            $phase='LOOKUP';$r=self::http($http,'GET','/v1/payouts/'.$d['payout_id'].'/transactions/'.$d['transaction_id'],'',$c['idempotency']);
            if(($r['id']??null)!==$d['transaction_id'] || ($r['external_reference']??null)!==$c['reference'] || ($r['amount']['currency']??null)!=='BRL' || GFMoneyInput::decimal($r['amount']['value']??null)!==$c['payload']['amount'])throw new DomainException('FIN_WORKER_LOOKUP_RESPONSE_MISMATCH');
            $state=match($r['status']??'') {
                'success'=>match($r['status_detail']??'') {'accredited'=>'confirmed','in_progress'=>'pending',default=>throw new DomainException('FIN_WORKER_STATUS_DETAIL_UNRECOGNIZED')},
                'approved'=>($r['status_detail']??'')==='partially_refunded'?throw new DomainException('FIN_WORKER_REFUND_FLOW_NOT_MIGRATED'):'pending',
                'pending','created','processing','processed','transaction_in_process','in_process'=>'pending',
                'error','canceled','rejected'=>'rejected',
                'refunded'=>throw new DomainException('FIN_WORKER_REFUND_FLOW_NOT_MIGRATED'),
                default=>throw new DomainException('FIN_WORKER_STATUS_UNRECOGNIZED')};
            if($hook)$hook('before_finalize');
            $phase='FINALIZE';$db->beginTransaction();$owned=true;$current=self::context($db,$jobId);
            $q=$db->prepare('SELECT *, (lease_until>NOW(6)) AS active FROM fin3_payout_dispatch_qa WHERE job_id=? FOR UPDATE');$q->execute([$jobId]);$stored=$q->fetch(PDO::FETCH_ASSOC);
            if(!$stored || $stored['claim_token']!==$token || !(int)$stored['active'])throw new DomainException('FIN_WORKER_CLAIM_LOST');
            if($stored['fingerprint']!==$current['fingerprint'] || $stored['request_json']!==$current['body'] || $stored['transaction_id']!==$d['transaction_id'] || $stored['payout_id']!==$d['payout_id'])throw new DomainException('FIN_WORKER_FROZEN_REQUEST_DRIFT');
            if($state==='confirmed') {
                $receipt=GFQaPayoutReceipt::confirmed($jobId,(int)$current['job']['pagamento_id'],$current['payload']['guincho_id'],$current['payload']['amount'],'qa_pix_'.hash('sha256',$d['transaction_id']));
                $result=GFIntegratedPayout::confirm($db,$jobId,$receipt);
                if(!$result['ok'])throw new DomainException('FIN_WORKER_LEDGER_CONFIRM_FAILED:'.$result['erro']);
                if($hook)$hook('after_ledger');
            }
            $q=$db->prepare('UPDATE fin3_payout_dispatch_qa SET state=?,response_json=?,claim_token=NULL,lease_until=NULL,last_error=NULL,updated_at=NOW(6) WHERE job_id=? AND claim_token=?');$q->execute([$state,json_encode($r,JSON_THROW_ON_ERROR),$jobId,$token]);
            if($q->rowCount()!==1)throw new DomainException('FIN_WORKER_CLAIM_LOST');$db->commit();$owned=false;
            self::log('FINALIZE_COMMIT','PASS',['job_id'=>$jobId,'state'=>$state]);return ['ok'=>true,'processed'=>true,'status'=>$state];
        } catch(Throwable $e) {
            if($owned && $db->inTransaction())$db->rollBack();
            $recordError=null;
            if($token!==null) {
                try {$q=$db->prepare("UPDATE fin3_payout_dispatch_qa SET state=?,claim_token=NULL,lease_until=NULL,last_error=?,updated_at=NOW(6) WHERE job_id=? AND claim_token=?");$q->execute([$e instanceof DomainException?'review':'uncertain',$e->getMessage(),$jobId,$token]);}
                catch(Throwable $secondary) {$recordError=$secondary->getMessage();}
            }
            self::log($phase,'FAIL',['job_id'=>$jobId,'error'=>$e->getMessage(),'exception'=>$e::class,'file'=>basename($e->getFile()),'line'=>$e->getLine(),'sqlstate'=>$e instanceof PDOException?($e->errorInfo[0]??null):null,'diagnostic_write_error'=>$recordError]);
            return ['ok'=>false,'processed'=>false,'erro'=>$e->getMessage()];
        }
    }
}

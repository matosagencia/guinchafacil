<?php
declare(strict_types=1);
/** Recuperacao exclusivamente QA de job comprovadamente nao enviado pelo worker migrado. */
final class GFPayoutRecoveryService
{
    private static function fail(string $m,int $http=409):void {throw new DomainException($m,$http);}
    private static function json(array $a):string {return json_encode($a,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);}
    private static function actor(PDO $db,int $id):void
    {
        $q=$db->prepare('SELECT tipo,ativo FROM usuarios WHERE id=? LOCK IN SHARE MODE');$q->execute([$id]);$u=$q->fetch(PDO::FETCH_ASSOC);
        if(!$u || $u['tipo']!=='admin' || (int)$u['ativo']!==1)self::fail('acesso_negado',403);
    }
    private static function approvers(PDO $db,array $row):void
    {
        $ids=[(int)$row['gerente_id']];if((bool)$row['requer_dupla_aprovacao'])$ids[]=(int)$row['segundo_gerente_id'];sort($ids,SORT_NUMERIC);
        foreach($ids as $id){$q=$db->prepare('SELECT tipo,ativo FROM usuarios WHERE id=? LOCK IN SHARE MODE');$q->execute([$id]);$u=$q->fetch(PDO::FETCH_ASSOC);if(!$u || !in_array($u['tipo'],['admin','gerente'],true) || (int)$u['ativo']!==1)self::fail('aprovador_inativo_ou_sem_permissao',403);}
    }
    private static function current(PDO $db,array $s):array
    {
        $o=GFReconciliation::rows($db,'SELECT id FROM pedidos WHERE id=?',[$s['order_id']],true);if(!$o)self::fail('contexto_financeiro_divergente');
        GFReconciliation::rows($db,'SELECT id FROM pagamentos WHERE id=?',[$s['payment_id']],true);
        $partners=GFReconciliation::rows($db,'SELECT * FROM guinchos WHERE id=?',[$s['partner_id']],true);
        $jobs=GFReconciliation::rows($db,'SELECT * FROM payment_jobs WHERE id=?',[$s['job_id']],true);if(!$jobs || !$partners)self::fail('contexto_financeiro_divergente');$job=$jobs[0];
        if($job['job_type']!=='pix_payout' || (int)$job['pedido_id']!==$s['order_id'] || (int)$job['pagamento_id']!==$s['payment_id'] || $job['idempotency_key']!==$s['idempotency_key'] || hash('sha256',$job['payload_json'])!==$s['payload_sha256'])self::fail('contexto_financeiro_divergente');
        $dispatch=GFReconciliation::rows($db,'SELECT * FROM fin3_payout_dispatch_qa WHERE job_id=?',[$s['job_id']],true);
        return ['job'=>$job,'partner'=>$partners[0],'dispatch'=>$dispatch[0]??null];
    }
    private static function frozen(array $s,array $partner):array
    {
        $type=match($partner['chave_pix_tipo']??''){'cpf'=>'CPF','cnpj'=>'CNPJ','email'=>'EMAIL','telefone'=>'PHONE','aleatoria'=>'PIX_CODE',default=>throw new DomainException('FIN_RECOVERY_PIX_TYPE_INVALID')};
        if(empty($partner['chave_pix']) || (int)$partner['aprovado']!==1)self::fail('destino_pix_indisponivel');$amount=GFMoneyInput::decimal($s['amount']);if(GFExactMoney::cents($amount)<100)self::fail('valor_abaixo_do_minimo_qa');
        $ref='GFQA'.$s['job_id'];$key=hash('sha256','payout-qa-v1|'.GFIntegratedApproval::DATABASE.'|'.$s['job_id']);
        $body=json_encode(['external_reference'=>$ref,'description'=>'GuinchaFacil QA payout','transactions'=>[['description'=>'GuinchaFacil QA payout','type'=>'pix','pix'=>['type'=>$type,'chave'=>$partner['chave_pix']],'amount'=>['currency'=>'BRL','value'=>$amount],'external_reference'=>$ref]]],JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);
        $body=str_replace('"value":"'.$amount.'"','"value":'.$amount,$body,$count);if($count!==1)throw new LogicException('FIN_RECOVERY_EXACT_BODY_FAILED');
        return ['reference'=>$ref,'idempotency_key'=>$key,'request_json'=>$body,'fingerprint'=>hash('sha256',$body)];
    }
    private static function existing(PDO $db,array $row,array $proof,array $c):array
    {
        $e=$proof['recovery'];$s=$proof['job_snapshot'];$request=$e['request']??[];$before=$e['job_before']??[];$f=$e['frozen']??[];$d=$c['dispatch'];
        if(($e['version']??null)!=='R11AW' || ($e['request_sha256']??null)!==hash('sha256',self::json($request)) || ($e['job_before_sha256']??null)!==hash('sha256',self::json($before)) ||
            ($request['case']??null)!==(int)$row['id'] || !is_int($request['actor']??null) || !preg_match('/^[a-f0-9]{32}$/D',(string)($request['key']??'')) || !preg_match('/^[a-f0-9]{64}$/D',(string)($request['revision']??'')) ||
            (int)($before['id']??0)!==$s['job_id'] || ($before['idempotency_key']??null)!==$s['idempotency_key'] || hash('sha256',(string)($before['payload_json']??''))!==$s['payload_sha256'] ||
            !$d || ($f['fingerprint']??null)!==hash('sha256',(string)($f['request_json']??'')) || $d['fingerprint']!==($f['fingerprint']??null) || $d['request_json']!==($f['request_json']??null) || $d['idempotency_key']!==($f['idempotency_key']??null))self::fail('prova_de_recuperacao_divergente');
        $state=$d['state'];$job=$c['job'];
        if(!in_array($state,['prepared','sending','pending','uncertain','review','rejected','confirmed'],true))self::fail('prova_de_recuperacao_divergente');
        if($job['status']==='completed'){
            if($state!=='confirmed' || empty($d['transaction_id']))self::fail('confirmacao_divergente');
            $receipt=GFQaPayoutReceipt::confirmed($s['job_id'],$s['payment_id'],$s['partner_id'],$s['amount'],'qa_pix_'.hash('sha256',$d['transaction_id']));
            $verified=GFIntegratedPayout::confirm($db,$s['job_id'],$receipt);if(!$verified['ok'] || !($verified['duplicate_verified']??false))self::fail('confirmacao_divergente');
            return ['financial_status'=>'confirmado_qa','job_status'=>'completed','dispatch_state'=>$state];
        }
        if($job['status']!=='queued' || (int)$job['attempt_count']!==0 || $state==='confirmed')self::fail('prova_de_recuperacao_divergente');
        if($state==='prepared' && ((int)$d['claim_count']!==0 || $d['claim_token']!==null || $d['lease_until']!==null || $d['payout_id']!==null || $d['transaction_id']!==null))self::fail('prova_de_recuperacao_divergente');
        return ['financial_status'=>$state==='prepared'?'aguardando_worker_qa':'exige_conciliacao','job_status'=>$job['status'],'dispatch_state'=>$state];
    }
    public static function read(PDO $db,int $actor,int $id):array
    {
        $owned=false;$phase='GUARD';try{
            GFQaPayoutWorker::guard($db);if($db->inTransaction())throw new LogicException('FIN_RECOVERY_OWNS_TRANSACTION_REQUIRED');$db->beginTransaction();$owned=true;$phase='AUTHORIZE';self::actor($db,$actor);
            $phase='READ_CONTEXT';$case=GFPayoutDecisionService::caseForRecovery($db,$id);$row=$case['row'];$p=$case['proof'];$c=self::current($db,$p['job_snapshot']);
            $out=isset($p['recovery'])?self::existing($db,$row,$p,$c):['financial_status'=>$c['dispatch']!==null?'exige_conciliacao':'nao_encaminhado','job_status'=>$c['job']['status'],'dispatch_state'=>$c['dispatch']['state']??null];
            $result=['ok'=>true,'http'=>200,'id'=>$id,'job_id'=>$p['job_snapshot']['job_id'],'amount'=>$row['valor_envolvido'],'case_status'=>$row['status'],'revision'=>hash('sha256',self::json($row)),
                'queue_available'=>$row['status']==='aprovada' && !isset($p['recovery']) && $c['dispatch']===null && in_array($c['job']['status'],['retry','failed'],true),'live_pix'=>false]+$out;
            $db->commit();$owned=false;return $result;
        }catch(Throwable $e){if($owned && $db->inTransaction())$db->rollBack();return self::error('read',$phase,$e);}
    }
    public static function queue(PDO $db,int $actor,int $id,string $key,string $revision):array
    {
        $owned=false;$phase='GUARD';try{
            GFQaPayoutWorker::guard($db);if($db->inTransaction())throw new LogicException('FIN_RECOVERY_OWNS_TRANSACTION_REQUIRED');
            if($id<1 || !preg_match('/^[a-f0-9]{32}$/D',$key) || !preg_match('/^[a-f0-9]{64}$/D',$revision))self::fail('parametro_invalido',422);
            $db->beginTransaction();$owned=true;$phase='AUTHORIZE';self::actor($db,$actor);$case=GFPayoutDecisionService::caseForRecovery($db,$id);$row=$case['row'];$p=$case['proof'];$s=$p['job_snapshot'];
            if($row['status']!=='aprovada')self::fail('demanda_sem_aprovacao_final');self::approvers($db,$row);
            $request=['actor'=>$actor,'case'=>$id,'key'=>$key,'revision'=>$revision];$requestHash=hash('sha256',self::json($request));
            $phase='LOCK_FINANCIAL_CONTEXT';$c=self::current($db,$s);
            if(isset($p['recovery'])){
                $phase='REPLAY';$out=self::existing($db,$row,$p,$c);
                if($p['recovery']['request']!==$request || $p['recovery']['request_sha256']!==$requestHash)self::fail('recuperacao_ja_encaminhada');
                $db->commit();$owned=false;return ['ok'=>true,'http'=>200,'id'=>$id,'job_id'=>$s['job_id'],'replayed'=>true,'queued'=>false,'live_pix'=>false]+$out;
            }
            $phase='VERIFY_UNSENT_QA';if(!hash_equals(hash('sha256',self::json($row)),$revision))self::fail('demanda_alterada_atualize_a_pagina');
            if($c['dispatch']!==null)self::fail('resultado_externo_exige_conciliacao');$job=$c['job'];
            if(!in_array($job['status'],['retry','failed'],true) || (int)$job['attempt_count']!==0 || $job['worker_id']!==null || $job['locked_at']!==null || $job['finished_at']!==null)self::fail('ausencia_de_envio_nao_comprovada');
            if(GFReconciliation::rows($db,'SELECT id FROM payment_job_attempts WHERE payment_job_id=?',[$s['job_id']],true))self::fail('ausencia_de_envio_nao_comprovada');
            GFPayoutDecisionService::caseForRecovery($db,$id,true);
            $balance=GFIntegratedPayout::balance($db,$s['payment_id']);$reserved=0;
            foreach(GFReconciliation::rows($db,"SELECT id,payload_json FROM payment_jobs WHERE pagamento_id=? AND job_type='pix_payout' AND status IN ('queued','retry','running')",[$s['payment_id']],true) as $other){
                if((int)$other['id']===$s['job_id'])continue;$payload=json_decode($other['payload_json'],true,512,JSON_THROW_ON_ERROR);if(($payload['version']??null)!=='R11AL')self::fail('reserva_legada_exige_conciliacao');$reserved+=GFExactMoney::cents(GFMoneyInput::decimal($payload['amount']??null));
            }
            if(GFExactMoney::cents($s['amount'])>$balance['remaining']-$reserved)self::fail('saldo_disponivel_insuficiente');
            foreach(GFReconciliation::rows($db,"SELECT metadata_json FROM payout_ledger_entries WHERE pagamento_id=? AND entry_type='debito_repasse_guincho'",[$s['payment_id']],true) as $debit){$meta=json_decode($debit['metadata_json'],true,512,JSON_THROW_ON_ERROR);if(($meta['job_id']??null)===$s['job_id'])self::fail('repasse_ja_contabilizado');}
            $phase='FREEZE_DISPATCH';$f=self::frozen($s,$c['partner']);
            // Intencao preparada; claim_count=0 e nenhum transporte efetuado nesta transacao.
            $db->prepare("INSERT INTO fin3_payout_dispatch_qa(job_id,idempotency_key,fingerprint,request_json,state,claim_count) VALUES (?,?,?,?,'prepared',0)")->execute([$s['job_id'],$f['idempotency_key'],$f['fingerprint'],$f['request_json']]);
            $p['recovery']=['version'=>'R11AW','request'=>$request,'request_sha256'=>$requestHash,'job_before'=>$job,'job_before_sha256'=>hash('sha256',self::json($job)),'frozen'=>$f,'queued_at'=>date('Y-m-d H:i:s')];
            $phase='QUEUE_AND_RECORD';$q=$db->prepare("UPDATE payment_jobs SET status='queued',available_at=NOW(),updated_at=NOW() WHERE id=? AND status=? AND attempt_count=0");$q->execute([$s['job_id'],$job['status']]);if($q->rowCount()!==1)throw new RuntimeException('FIN_RECOVERY_JOB_UPDATE_LOST');
            $q=$db->prepare("UPDATE demandas SET payload_json=? WHERE id=? AND status='aprovada'");$q->execute([self::json($p),$id]);if($q->rowCount()!==1)throw new RuntimeException('FIN_RECOVERY_CASE_UPDATE_LOST');
            $phase='COMMIT';$db->commit();$owned=false;return ['ok'=>true,'http'=>200,'id'=>$id,'job_id'=>$s['job_id'],'queued'=>true,'replayed'=>false,'financial_status'=>'aguardando_worker_qa','job_status'=>'queued','dispatch_state'=>'prepared','live_pix'=>false];
        }catch(Throwable $e){if($owned && $db->inTransaction())$db->rollBack();return self::error('queue',$phase,$e);}
    }
    private static function error(string $function,string $phase,Throwable $e):array
    {
        $http=$e instanceof DomainException && in_array($e->getCode(),[403,404,409,422],true)?$e->getCode():500;
        $d=['project'=>'GuinchaFacil','class'=>self::class,'function'=>$function,'component'=>'payout_recovery','phase'=>$phase,'status'=>$http===500?'FAIL':'REJECTED','error'=>$e->getMessage(),'exception'=>$e::class,'file'=>basename($e->getFile()),'line'=>$e->getLine(),'sqlstate'=>$e instanceof PDOException?($e->errorInfo[0]??null):null,'driver_code'=>$e instanceof PDOException?($e->errorInfo[1]??null):null,'remaining_percent'=>50];
        error_log(json_encode($d,JSON_THROW_ON_ERROR|JSON_INVALID_UTF8_SUBSTITUTE));return ['ok'=>false,'http'=>$http,'erro'=>$http===500?'recuperacao_indisponivel':$e->getMessage(),'diagnostic'=>$d];
    }
}

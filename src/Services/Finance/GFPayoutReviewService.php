<?php
declare(strict_types=1);
require_once __DIR__.'/../../Models/Demanda.php';
/** Abre somente demanda pendente. Nao aprova nem executa reprocessamento. */
final class GFPayoutReviewService
{
    private static function fail(string $message,int $http=409):void {throw new DomainException($message,$http);}
    private static function actor(PDO $db,int $actor):void
    {
        $q=$db->prepare('SELECT tipo,ativo FROM usuarios WHERE id=? FOR UPDATE');$q->execute([$actor]);$u=$q->fetch(PDO::FETCH_ASSOC);
        if(!$u || $u['tipo']!=='admin' || (int)$u['ativo']!==1)self::fail('acesso_negado',403);
    }
    private static function setting(PDO $db,string $key,string $default):string
    {
        $q=$db->prepare('SELECT valor FROM configuracoes WHERE chave=?');$q->execute([$key]);$v=$q->fetchAll(PDO::FETCH_COLUMN);
        if(count($v)>1)throw new DomainException('FIN_REVIEW_CONFIG_DUPLICATE');return count($v)===1?(string)$v[0]:$default;
    }
    private static function context(PDO $db,int $job):array
    {
        $q=$db->prepare('SELECT * FROM payment_jobs WHERE id=? FOR UPDATE');$q->execute([$job]);$j=$q->fetch(PDO::FETCH_ASSOC);if(!$j)self::fail('job_nao_encontrado',404);
        if($j['job_type']!=='pix_payout' || $j['status']==='completed')self::fail('job_nao_elegivel');
        $payload=json_decode($j['payload_json'],true,512,JSON_THROW_ON_ERROR);if(($payload['version']??null)!=='R11AL')self::fail('job_nao_migrado');
        $d=GFReconciliation::rows($db,'SELECT state FROM fin3_payout_dispatch_qa WHERE job_id=?',[$job]);
        if(!in_array($j['status'],['retry','failed'],true) && !in_array($d[0]['state']??'', ['review','rejected','uncertain'],true))self::fail('job_sem_falha_para_triagem');
        $v=GFReconciliation::online($db,(int)$j['pagamento_id']);$o=$v['order'];$p=$v['payment'];
        if((int)$o['id']!==(int)$j['pedido_id'] || ($payload['pedido_id']??null)!==(int)$o['id'] || ($payload['pagamento_id']??null)!==(int)$p['id'] || ($payload['guincho_id']??null)!==(int)$o['guincho_id'] || ($payload['platform']??null)!==$p['valor_plataforma'])throw new DomainException('FIN_REVIEW_JOB_IDENTITY_DRIFT');
        $amount=GFMoneyInput::decimal($payload['amount']??null);if($amount==='0.00')throw new DomainException('FIN_REVIEW_ZERO_AMOUNT');
        $remaining=0;foreach(GFReconciliation::rows($db,'SELECT entry_type,valor FROM payout_ledger_entries WHERE pagamento_id=?',[$p['id']]) as $e){$n=GFExactMoney::cents($e['valor']);$remaining+=match($e['entry_type']){'credito_guincho'=>$n,'debito_repasse_guincho','estorno_credito_guincho'=>-$n,default=>0};}
        if(GFExactMoney::cents($amount)>$remaining)throw new DomainException('FIN_REVIEW_AMOUNT_DRIFT');
        return ['job_id'=>$job,'order_id'=>(int)$o['id'],'payment_id'=>(int)$p['id'],'partner_id'=>(int)$o['guincho_id'],'amount'=>$amount,'job_status'=>$j['status'],'dispatch_state'=>$d[0]['state']??null,'idempotency_key'=>$j['idempotency_key'],'payload_sha256'=>hash('sha256',$j['payload_json'])];
    }
    private static function publicCase(array $row,bool $created):array
    {
        return ['ok'=>true,'http'=>$created?201:200,'id'=>(int)$row['id'],'created'=>$created,'status'=>$row['status'],'amount'=>$row['valor_envolvido'],'requires_dual'=>(bool)$row['requer_dupla_aprovacao'],'execution_enabled'=>false];
    }
    public static function inspect(PDO $db,int $actor,int $job):array
    {
        $owned=false;
        try{GFDirectBilling::guard($db);if($db->inTransaction())throw new LogicException('FIN_REVIEW_OWNS_TRANSACTION_REQUIRED');$db->beginTransaction();$owned=true;self::actor($db,$actor);$c=self::context($db,$job);$rows=GFReconciliation::rows($db,"SELECT id,status FROM demandas WHERE tipo='pagamento' AND payment_job_id=? AND status IN ('pendente','aprovada_parcial','aprovada') ORDER BY id",[$job]);$db->commit();$owned=false;return ['ok'=>true,'http'=>200,'job'=>['id'=>$job,'amount'=>$c['amount'],'status'=>$c['job_status']],'existing_case'=>$rows[0]??null,'execution_enabled'=>false];}
        catch(Throwable $e){if($owned && $db->inTransaction())$db->rollBack();return self::error('inspect','READ',$e);}
    }
    public static function create(PDO $db,int $actor,int $job,string $key,string $reason):array
    {
        $owned=false;$phase='GUARD';
        try{
            GFDirectBilling::guard($db);if($db->inTransaction())throw new LogicException('FIN_REVIEW_OWNS_TRANSACTION_REQUIRED');
            if($job<1 || $job>2147483647 || !preg_match('/^[a-f0-9]{32}$/D',$key))self::fail('parametro_invalido',422);
            $reason=trim($reason);$db->beginTransaction();$owned=true;$phase='AUTHORIZE';self::actor($db,$actor);
            $minimum=self::setting($db,'demanda_justificativa_min_chars','20');if(!ctype_digit($minimum) || (int)$minimum<1 || (int)$minimum>1000)throw new DomainException('FIN_REVIEW_INVALID_MIN_LENGTH');
            if(mb_strlen($reason,'UTF-8')<(int)$minimum || mb_strlen($reason,'UTF-8')>1000)self::fail('justificativa_invalida',422);
            $request=['actor'=>$actor,'job'=>$job,'request_key'=>$key,'reason'=>$reason];$requestHash=hash('sha256',json_encode($request,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE));$hash=hash('sha256','r11au|'.$actor.'|'.$key);
            $phase='REPLAY';$old=GFReconciliation::rows($db,'SELECT * FROM demandas WHERE hash_idempotencia=?',[$hash],true);
            if($old){$row=$old[0];$proof=json_decode($row['payload_json'],true,512,JSON_THROW_ON_ERROR);$snapshot=$proof['job_snapshot']??[];
                if(($proof['review_only']??null)!==true || ($proof['execution_enabled']??null)!==false || ($proof['version']??null)!=='R11AU' || ($proof['request_sha256']??null)!==$requestHash || ($proof['request']??null)!==$request || ($proof['snapshot_sha256']??null)!==hash('sha256',json_encode($snapshot,JSON_THROW_ON_ERROR)) || $row['tipo']!=='pagamento' || (int)$row['solicitante_id']!==$actor || (int)$row['payment_job_id']!==$job || (int)$row['pedido_id']!==($snapshot['order_id']??null) || (int)$row['guincho_id']!==($snapshot['partner_id']??null) || $row['valor_envolvido']!==($snapshot['amount']??null) || $row['justificativa']!==$reason || (bool)$row['requer_dupla_aprovacao']!==($proof['requires_dual']??null))self::fail('idempotencia_divergente');
                $db->commit();$owned=false;return self::publicCase($row,false);
            }
            $phase='FREEZE_CONTEXT';$snapshot=self::context($db,$job);
            $open=GFReconciliation::rows($db,"SELECT id FROM demandas WHERE tipo='pagamento' AND payment_job_id=? AND status IN ('pendente','aprovada_parcial','aprovada')",[$job],true);if($open)self::fail('triagem_ja_aberta');
            $threshold=GFMoneyInput::decimal(self::setting($db,'demanda_valor_dupla_aprovacao','500.00'));$dual=GFExactMoney::cents($snapshot['amount'])>=GFExactMoney::cents($threshold);
            $proof=['version'=>'R11AU','request'=>$request,'request_sha256'=>$requestHash,'job_snapshot'=>$snapshot,'snapshot_sha256'=>hash('sha256',json_encode($snapshot,JSON_THROW_ON_ERROR)),'requires_dual'=>$dual,'review_only'=>true,'execution_enabled'=>false];
            $phase='INSERT_PENDING_CASE';$id=Demanda::criar(['tipo'=>'pagamento','status'=>'pendente','solicitante_id'=>$actor,'pedido_id'=>$snapshot['order_id'],'guincho_id'=>$snapshot['partner_id'],'payment_job_id'=>$job,'valor_envolvido'=>$snapshot['amount'],'requer_dupla_aprovacao'=>$dual,'justificativa'=>$reason,'payload_json'=>json_encode($proof,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),'hash_idempotencia'=>$hash]);
            $row=GFReconciliation::rows($db,'SELECT * FROM demandas WHERE id=?',[$id])[0];$db->commit();$owned=false;return self::publicCase($row,true);
        }catch(Throwable $e){if($owned && $db->inTransaction())$db->rollBack();return self::error('create',$phase,$e);}
    }
    private static function error(string $function,string $phase,Throwable $e):array
    {
        $http=$e instanceof DomainException && in_array($e->getCode(),[403,404,409,422],true)?$e->getCode():500;
        return ['ok'=>false,'http'=>$http,'erro'=>$http===500?'triagem_indisponivel':$e->getMessage(),'diagnostic'=>['project'=>'GuinchaFacil','class'=>self::class,'function'=>$function,'component'=>'payout_review','phase'=>$phase,'error'=>$e->getMessage(),'exception'=>$e::class,'file'=>basename($e->getFile()),'line'=>$e->getLine(),'sqlstate'=>$e instanceof PDOException?($e->errorInfo[0]??null):null]];
    }
}

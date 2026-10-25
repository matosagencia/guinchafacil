<?php
declare(strict_types=1);
/** Decide demandas R11AU em QA. Nunca executa nem reenfileira transferencias. */
final class GFPayoutDecisionService
{
    private static function fail(string $message,int $http=409):void {throw new DomainException($message,$http);}
    private static function json(array $v):string {return json_encode($v,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);}
    private static function actor(PDO $db,int $id):void
    {
        $q=$db->prepare('SELECT tipo,ativo FROM usuarios WHERE id=? FOR UPDATE');$q->execute([$id]);$u=$q->fetch(PDO::FETCH_ASSOC);
        if(!$u || !in_array($u['tipo'],['admin','gerente'],true) || (int)$u['ativo']!==1)self::fail('acesso_negado',403);
    }
    private static function config(PDO $db,string $key,string $default):string
    {
        $q=$db->prepare('SELECT valor FROM configuracoes WHERE chave=? LOCK IN SHARE MODE');$q->execute([$key]);$v=$q->fetchAll(PDO::FETCH_COLUMN);
        if(count($v)>1)throw new DomainException('FIN_DECISION_CONFIG_DUPLICATE');return count($v)===1?(string)$v[0]:$default;
    }
    private static function proof(array $d):array
    {
        $p=json_decode($d['payload_json'],true,512,JSON_THROW_ON_ERROR);$s=$p['job_snapshot']??[];$r=$p['request']??[];
        if(($p['version']??null)!=='R11AU' || ($p['review_only']??null)!==true || ($p['execution_enabled']??null)!==false ||
            ($p['request_sha256']??null)!==hash('sha256',self::json($r)) || ($p['snapshot_sha256']??null)!==hash('sha256',json_encode($s,JSON_THROW_ON_ERROR)) ||
            $d['tipo']!=='pagamento' || (int)$d['solicitante_id']!==($r['actor']??null) || (int)$d['payment_job_id']!==($r['job']??null) ||
            (int)$d['payment_job_id']!==($s['job_id']??null) || (int)$d['pedido_id']!==($s['order_id']??null) || (int)$d['guincho_id']!==($s['partner_id']??null) ||
            $d['valor_envolvido']!==($s['amount']??null) || $d['justificativa']!==($r['reason']??null) || (bool)$d['requer_dupla_aprovacao']!==($p['requires_dual']??null) ||
            !is_string($r['request_key']??null) || $d['hash_idempotencia']!==hash('sha256','r11au|'.$r['actor'].'|'.$r['request_key']))self::fail('prova_da_demanda_divergente');
        $events=$p['decisions']??[];if(!is_array($events) || !array_is_list($events) || count($events)>2)self::fail('historico_divergente');
        $state='pendente';$seen=[];$physical=['gerente_id'=>null,'decidido_em'=>null,'nota_gerente'=>null,'segundo_gerente_id'=>null,'segundo_decidido_em'=>null,'segunda_nota'=>null];
        foreach($events as $i=>$e){
            $request=$e['request']??[];$a=$request['actor']??null;$verdict=$request['verdict']??null;
            if(($e['version']??null)!=='R11AV' || ($e['request_sha256']??null)!==hash('sha256',self::json($request)) || !is_int($a) || $a<1 || $a===(int)$d['solicitante_id'] || isset($seen[$a]) ||
                ($request['case']??null)!==(int)$d['id'] || !preg_match('/^[a-f0-9]{32}$/D',(string)($request['key']??'')) || !preg_match('/^[a-f0-9]{64}$/D',(string)($request['revision']??'')) ||
                !is_string($request['note']??null) || !in_array($state,['pendente','aprovada_parcial'],true) || !in_array($verdict,['aprovar','rejeitar'],true) ||
                !preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}$/D',(string)($e['at']??'')))self::fail('historico_divergente');
            $state=$verdict==='rejeitar'?'rejeitada':($i===0 && $p['requires_dual']?'aprovada_parcial':'aprovada');
            if(($e['status']??null)!==$state)self::fail('historico_divergente');
            if($i===0){$physical['gerente_id']=$a;$physical['decidido_em']=$e['at'];$physical['nota_gerente']=$request['note'];}
            else{$physical['segundo_gerente_id']=$a;$physical['segundo_decidido_em']=$e['at'];$physical['segunda_nota']=$request['note'];}
            $seen[$a]=true;
        }
        if($d['status']!==$state || $d['executado_em']!==null || $d['erro_execucao']!==null)self::fail('historico_divergente');
        foreach($physical as $k=>$v){$actual=$d[$k];if(str_ends_with($k,'_id') && $actual!==null)$actual=(int)$actual;if($actual!==$v)self::fail('historico_divergente');}
        return $p;
    }
    private static function publicCase(array $d):array
    {
        return ['id'=>(int)$d['id'],'job_id'=>(int)$d['payment_job_id'],'requester_id'=>(int)$d['solicitante_id'],'status'=>$d['status'],'amount'=>$d['valor_envolvido'],'requires_dual'=>(bool)$d['requer_dupla_aprovacao'],
            'first_manager'=>$d['gerente_id']===null?null:(int)$d['gerente_id'],'first_note'=>$d['nota_gerente'],'first_at'=>$d['decidido_em'],
            'second_manager'=>$d['segundo_gerente_id']===null?null:(int)$d['segundo_gerente_id'],'second_note'=>$d['segunda_nota'],'second_at'=>$d['segundo_decidido_em'],
            'reason'=>$d['justificativa'],'revision'=>hash('sha256',self::json($d)),'execution_enabled'=>false];
    }
    private static function eligible(PDO $db,array $d,array $proof):void
    {
        $s=$proof['job_snapshot'];
        // Ordem pedido -> pagamento -> parceiro -> job -> ledger. Nenhum transporte sob lock.
        $order=GFReconciliation::rows($db,'SELECT id,status,attendance_mode FROM pedidos WHERE id=?',[$s['order_id']],true);
        if(!$order || $order[0]['status']!=='concluido' || $order[0]['attendance_mode']!=='TOWING')self::fail('contexto_financeiro_divergente');
        GFReconciliation::rows($db,'SELECT id FROM pagamentos WHERE id=?',[$s['payment_id']],true);
        $partner=GFReconciliation::rows($db,'SELECT id,aprovado,chave_pix,chave_pix_tipo FROM guinchos WHERE id=?',[$s['partner_id']],true);if(!$partner || (int)$partner[0]['aprovado']!==1 || empty($partner[0]['chave_pix']) || empty($partner[0]['chave_pix_tipo']))self::fail('contexto_financeiro_divergente');
        $jobs=GFReconciliation::rows($db,'SELECT * FROM payment_jobs WHERE id=?',[$s['job_id']],true);if(!$jobs)self::fail('contexto_financeiro_divergente');$j=$jobs[0];
        $dispatch=GFReconciliation::rows($db,'SELECT state FROM fin3_payout_dispatch_qa WHERE job_id=?',[$s['job_id']],true);$ds=$dispatch[0]['state']??null;
        if($j['status']==='completed' || $j['job_type']!=='pix_payout' || (int)$j['pedido_id']!==$s['order_id'] || (int)$j['pagamento_id']!==$s['payment_id'] || $j['status']!==$s['job_status'] || $ds!==$s['dispatch_state'] ||
            $j['idempotency_key']!==$s['idempotency_key'] || hash('sha256',$j['payload_json'])!==$s['payload_sha256'] ||
            (!in_array($j['status'],['retry','failed'],true) && !in_array($ds,['review','rejected','uncertain'],true)))self::fail('contexto_financeiro_divergente');
        $v=GFReconciliation::online($db,$s['payment_id'],true);$payload=json_decode($j['payload_json'],true,512,JSON_THROW_ON_ERROR);
        if((int)$v['order']['id']!==$s['order_id'] || (int)$v['order']['guincho_id']!==$s['partner_id'] || ($payload['version']??null)!=='R11AL' ||
            ($payload['pedido_id']??null)!==$s['order_id'] || ($payload['pagamento_id']??null)!==$s['payment_id'] || ($payload['guincho_id']??null)!==$s['partner_id'] ||
            GFMoneyInput::decimal($payload['amount']??null)!==$s['amount'] || ($payload['platform']??null)!==$v['payment']['valor_plataforma'])self::fail('contexto_financeiro_divergente');
        $remaining=0;foreach(GFReconciliation::rows($db,'SELECT entry_type,valor FROM payout_ledger_entries WHERE pagamento_id=?',[$s['payment_id']],true) as $e){$n=GFExactMoney::cents($e['valor']);$remaining+=match($e['entry_type']){'credito_guincho'=>$n,'debito_repasse_guincho','estorno_credito_guincho'=>-$n,default=>0};}
        if(GFExactMoney::cents($s['amount'])<1 || GFExactMoney::cents($s['amount'])>$remaining)self::fail('contexto_financeiro_divergente');
        $threshold=GFMoneyInput::decimal(self::config($db,'demanda_valor_dupla_aprovacao','500.00'));
        if($proof['requires_dual']!==(GFExactMoney::cents($s['amount'])>=GFExactMoney::cents($threshold)))self::fail('politica_de_aprovacao_alterada');
    }
    public static function caseForRecovery(PDO $db,int $id,bool $financial=false):array
    {
        GFDirectBilling::guard($db);if(!$db->inTransaction())throw new LogicException('FIN_RECOVERY_TRANSACTION_REQUIRED');
        $rows=GFReconciliation::rows($db,'SELECT * FROM demandas WHERE id=?',[$id],true);if(!$rows)self::fail('demanda_nao_encontrada',404);
        $row=$rows[0];$proof=self::proof($row);if($financial)self::eligible($db,$row,$proof);return ['row'=>$row,'proof'=>$proof];
    }
    public static function read(PDO $db,int $actor,?int $id=null):array
    {
        $owned=false;$phase='GUARD';try{
            GFDirectBilling::guard($db);if($db->inTransaction())throw new LogicException('FIN_DECISION_OWNS_TRANSACTION_REQUIRED');$db->beginTransaction();$owned=true;$phase='AUTHORIZE';self::actor($db,$actor);
            $phase='READ_CASE';$rows=$id===null?GFReconciliation::rows($db,"SELECT * FROM demandas WHERE tipo='pagamento' ORDER BY id DESC LIMIT 100"):GFReconciliation::rows($db,'SELECT * FROM demandas WHERE id=?',[$id],true);
            if($id!==null && !$rows)self::fail('demanda_nao_encontrada',404);$out=[];
            foreach($rows as $d){if($id===null){$p=json_decode($d['payload_json'],true);if(!is_array($p) || ($p['version']??null)!=='R11AU')continue;}self::proof($d);$out[]=self::publicCase($d);}
            $db->commit();$owned=false;return ['ok'=>true,'http'=>200,'cases'=>$out,'execution_enabled'=>false];
        }catch(Throwable $e){if($owned && $db->inTransaction())$db->rollBack();return self::error('read',$phase,$e);}
    }
    public static function decide(PDO $db,int $actor,int $id,string $key,string $revision,string $verdict,string $note):array
    {
        $owned=false;$phase='GUARD';try{
            GFDirectBilling::guard($db);if($db->inTransaction())throw new LogicException('FIN_DECISION_OWNS_TRANSACTION_REQUIRED');
            if($id<1 || !preg_match('/^[a-f0-9]{32}$/D',$key) || !preg_match('/^[a-f0-9]{64}$/D',$revision) || !in_array($verdict,['aprovar','rejeitar'],true))self::fail('parametro_invalido',422);
            $db->beginTransaction();$owned=true;$phase='AUTHORIZE';self::actor($db,$actor);
            $note=trim($note);$minimum=self::config($db,'demanda_nota_gerente_min_chars','10');
            if(!ctype_digit($minimum) || (int)$minimum<1 || (int)$minimum>500)throw new DomainException('FIN_DECISION_INVALID_MIN_LENGTH');
            if(!mb_check_encoding($note,'UTF-8') || mb_strlen($note,'UTF-8')<(int)$minimum || mb_strlen($note,'UTF-8')>500)self::fail('nota_invalida',422);
            $phase='LOCK_AND_VERIFY';$rows=GFReconciliation::rows($db,'SELECT * FROM demandas WHERE id=?',[$id],true);if(!$rows)self::fail('demanda_nao_encontrada',404);$d=$rows[0];$p=self::proof($d);
            if((int)$d['solicitante_id']===$actor)self::fail('solicitante_nao_pode_decidir',403);
            $request=['actor'=>$actor,'case'=>$id,'key'=>$key,'revision'=>$revision,'verdict'=>$verdict,'note'=>$note];$requestHash=hash('sha256',self::json($request));
            $phase='REPLAY';foreach($p['decisions']??[] as $event){if($event['request']['actor']===$actor && $event['request']['key']===$key){
                if($event['request']!==$request || $event['request_sha256']!==$requestHash)self::fail('idempotencia_divergente');
                $db->commit();$owned=false;return ['ok'=>true,'http'=>200,'id'=>$id,'status'=>$event['status'],'current_status'=>$d['status'],'replayed'=>true,'execution_enabled'=>false];
            }}
            $phase='TRANSITION';if(!hash_equals(self::publicCase($d)['revision'],$revision))self::fail('demanda_alterada_atualize_a_pagina');
            if(!in_array($d['status'],['pendente','aprovada_parcial'],true))self::fail('demanda_ja_decidida');
            if($d['gerente_id']!==null && (int)$d['gerente_id']===$actor)self::fail('segundo_responsavel_deve_ser_distinto',403);
            if($verdict==='aprovar'){$phase='VERIFY_FINANCIAL_CONTEXT';self::eligible($db,$d,$p);}
            $next=$verdict==='rejeitar'?'rejeitada':($d['status']==='pendente' && (bool)$d['requer_dupla_aprovacao']?'aprovada_parcial':'aprovada');$at=date('Y-m-d H:i:s');
            $p['decisions'][]=['version'=>'R11AV','request'=>$request,'request_sha256'=>$requestHash,'status'=>$next,'at'=>$at];
            $first=$d['status']==='pendente';$columns=$first?'gerente_id=?,decidido_em=?,nota_gerente=?':'segundo_gerente_id=?,segundo_decidido_em=?,segunda_nota=?';
            $phase='PERSIST_DECISION';$q=$db->prepare('UPDATE demandas SET status=?,'.$columns.',payload_json=? WHERE id=? AND status=?');$q->execute([$next,$actor,$at,$note,self::json($p),$id,$d['status']]);if($q->rowCount()!==1)throw new RuntimeException('FIN_DECISION_UPDATE_LOST');
            $updated=GFReconciliation::rows($db,'SELECT * FROM demandas WHERE id=?',[$id],true)[0];self::proof($updated);
            $phase='COMMIT';$db->commit();$owned=false;return ['ok'=>true,'http'=>200,'id'=>$id,'status'=>$next,'current_status'=>$next,'replayed'=>false,'execution_enabled'=>false];
        }catch(Throwable $e){if($owned && $db->inTransaction())$db->rollBack();return self::error('decide',$phase,$e);}
    }
    private static function error(string $function,string $phase,Throwable $e):array
    {
        $http=$e instanceof DomainException && in_array($e->getCode(),[403,404,409,422],true)?$e->getCode():500;
        $diagnostic=['project'=>'GuinchaFacil','class'=>self::class,'function'=>$function,'component'=>'payout_decision','phase'=>$phase,'status'=>$http===500?'FAIL':'REJECTED','error'=>$e->getMessage(),'exception'=>$e::class,'file'=>basename($e->getFile()),'line'=>$e->getLine(),'sqlstate'=>$e instanceof PDOException?($e->errorInfo[0]??null):null,'driver_code'=>$e instanceof PDOException?($e->errorInfo[1]??null):null,'remaining_percent'=>50];
        error_log(json_encode($diagnostic,JSON_THROW_ON_ERROR|JSON_INVALID_UTF8_SUBSTITUTE));
        return ['ok'=>false,'http'=>$http,'erro'=>$http===500?'decisao_indisponivel':$e->getMessage(),'diagnostic'=>$diagnostic];
    }
}

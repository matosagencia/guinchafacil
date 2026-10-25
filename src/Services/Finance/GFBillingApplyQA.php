<?php
declare(strict_types=1);
/** Mutacao explicita por fatura QA. Sem cobranca, baixa ou notificacao. */
final class GFBillingApplyQA
{
    private static function fail(string $m,int $code=409):void {throw new DomainException($m,$code);}
    private static function json(array $a):string {return json_encode($a,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);}
    private static function actor(PDO $db,int $id):void
    {
        $q=$db->prepare('SELECT tipo,ativo FROM usuarios WHERE id=? LOCK IN SHARE MODE');$q->execute([$id]);$u=$q->fetch(PDO::FETCH_ASSOC);if(!$u || $u['tipo']!=='admin' || (int)$u['ativo']!==1)self::fail('acesso_negado',403);
    }
    private static function context(PDO $db,int $id):array
    {
        $q=$db->prepare("SELECT valor FROM configuracoes WHERE chave='bloqueio_financeiro_ativo' LOCK IN SHARE MODE");$q->execute();$values=$q->fetchAll(PDO::FETCH_COLUMN);if(count($values)>1 || !in_array($values[0]??'0',['0','1'],true))self::fail('configuracao_de_bloqueio_invalida');$flag=$values[0]??'0';
        $rows=GFReconciliation::rows($db,'SELECT id FROM faturas_parceiro WHERE id=?',[$id],true);if(!$rows)self::fail('fatura_nao_encontrada',404);
        $v=GFDirectBilling::verifyInvoice($db,$id);$f=$v['invoice'];$pays=GFReconciliation::rows($db,'SELECT * FROM faturas_pagamentos WHERE fatura_id=?',[$id],true);
        if($f['status']==='paga'){
            if(count($pays)!==1 || $pays[0]['transacao_id']!==$f['pix_transacao_id'] || $pays[0]['valor']!==$v['amount'])self::fail('comprovante_da_fatura_divergente');$proof=json_decode($pays[0]['payload'],true,512,JSON_THROW_ON_ERROR);if(count($proof)!==4 || $proof[0]!==$id || $proof[1]!==$v['amount'] || $proof[2]!==$pays[0]['transacao_id'])self::fail('comprovante_da_fatura_divergente');
        }elseif($pays)self::fail('comprovante_da_fatura_divergente');
        $fp=hash('sha256',self::json([$id,(int)$f['parceiro_id'],$f['ciclo_inicio'],$f['ciclo_fim'],$f['vencimento_em'],$f['total_faturado'],$f['total_repasse'],$f['saldo'],$v['closed']['snapshot_sha256']]));
        $now=(new DateTimeImmutable('now',new DateTimeZone('America/Sao_Paulo')))->format('Y-m-d H:i:s');$due=GFInvoiceReadModel::time($f['vencimento_em']);$overdue=$due<GFInvoiceReadModel::time($now);$revision=hash('sha256',self::json([$fp,$f['status'],$f['updated_at'],$flag]));
        return ['invoice'=>$f,'amount'=>$v['amount'],'fingerprint'=>$fp,'revision'=>$revision,'enabled'=>$flag==='1','server_reference'=>$now,'overdue'=>$overdue];
    }
    public static function inspect(PDO $db,int $actor,int $id):array
    {
        $owned=false;$phase='GUARD';try{GFDirectBilling::guard($db);if($db->inTransaction())throw new LogicException('FIN_BLOCK_OWNS_TRANSACTION_REQUIRED');$db->beginTransaction();$owned=true;$phase='AUTHORIZE';self::actor($db,$actor);$phase='VERIFY_INVOICE';$c=self::context($db,$id);$db->commit();$owned=false;return ['ok'=>true,'http'=>200,'invoice_id'=>$id,'status'=>$c['invoice']['status'],'amount'=>$c['amount'],'due_at'=>$c['invoice']['vencimento_em'],'revision'=>$c['revision'],'server_reference'=>$c['server_reference'],'policy_enabled'=>$c['enabled'],'currently_blocked'=>$c['enabled'] && $c['invoice']['status']==='bloqueada','eligible'=>$c['enabled'] && $c['overdue'] && $c['invoice']['status']==='aberta' && GFExactMoney::cents($c['amount'])>0];}
        catch(Throwable $e){if($owned && $db->inTransaction())$db->rollBack();return self::error('inspect',$phase,$e);}
    }
    public static function apply(PDO $db,int $actor,int $id,string $key,string $revision,string $reason):array
    {
        $owned=false;$phase='GUARD';try{
            GFDirectBilling::guard($db);if($db->inTransaction())throw new LogicException('FIN_BLOCK_OWNS_TRANSACTION_REQUIRED');$reason=trim($reason);
            if($id<1 || !preg_match('/^[a-f0-9]{32}$/D',$key) || !preg_match('/^[a-f0-9]{64}$/D',$revision) || !mb_check_encoding($reason,'UTF-8') || mb_strlen($reason,'UTF-8')<20 || mb_strlen($reason,'UTF-8')>500)self::fail('parametro_ou_motivo_invalido',422);
            $db->beginTransaction();$owned=true;$phase='AUTHORIZE';self::actor($db,$actor);$phase='VERIFY_INVOICE';$c=self::context($db,$id);
            $request=['actor'=>$actor,'invoice'=>$id,'key'=>$key,'revision'=>$revision,'reason'=>$reason];$hash=hash('sha256',self::json($request));$operation=hash('sha256','r11az|'.$actor.'|'.$key);
            $phase='REPLAY';$old=GFReconciliation::rows($db,'SELECT * FROM fin3_invoice_actions_qa WHERE operation_key=?',[$operation],true);
            if($old){$a=$old[0];$p=json_decode($a['proof_json'],true,512,JSON_THROW_ON_ERROR);
                if((int)$a['invoice_id']!==$id || (int)$a['actor_id']!==$actor || $a['action_type']!=='invoice_block' || $a['before_status']!=='aberta' || $a['after_status']!=='bloqueada' || $a['reason']!==$reason || $a['request_sha256']!==$hash || $a['invoice_fingerprint']!==$c['fingerprint'] || ($p['version']??null)!=='R11AZ' || ($p['request']??null)!==$request || ($p['request_sha256']??null)!==$hash || ($p['invoice_fingerprint']??null)!==$c['fingerprint'] || ($p['policy_enabled']??null)!==true || ($p['before_sha256']??null)!==hash('sha256',self::json($p['before_invoice']??[])) || (int)($p['before_invoice']['id']??0)!==$id || ($p['before_invoice']['status']??null)!=='aberta' || ($p['server_reference']??null)!==$a['created_at'])self::fail('idempotencia_divergente');
                if(!in_array($c['invoice']['status'],['bloqueada','paga'],true))self::fail('bloqueio_revertido_exige_revisao');$db->commit();$owned=false;return ['ok'=>true,'http'=>200,'invoice_id'=>$id,'replayed'=>true,'historical_status'=>'bloqueada','current_status'=>$c['invoice']['status'],'currently_blocked'=>$c['enabled'] && $c['invoice']['status']==='bloqueada','notification_sent'=>false];
            }
            $phase='REVALIDATE';if(!$c['enabled'])self::fail('politica_de_bloqueio_inativa');if(!hash_equals($c['revision'],$revision))self::fail('fatura_alterada_atualize_a_pagina');if($c['invoice']['status']!=='aberta' || !$c['overdue'] || GFExactMoney::cents($c['amount'])<1)self::fail('fatura_nao_elegivel_para_bloqueio');
            $proof=['version'=>'R11AZ','request'=>$request,'request_sha256'=>$hash,'invoice_fingerprint'=>$c['fingerprint'],'before_invoice'=>$c['invoice'],'before_sha256'=>hash('sha256',self::json($c['invoice'])),'policy_enabled'=>true,'server_reference'=>$c['server_reference'],'notification_sent'=>false];
            $phase='UPDATE_AND_AUDIT';$q=$db->prepare("UPDATE faturas_parceiro SET status='bloqueada' WHERE id=? AND status='aberta'");$q->execute([$id]);if($q->rowCount()!==1)throw new RuntimeException('FIN_BLOCK_UPDATE_LOST');
            $db->prepare("INSERT INTO fin3_invoice_actions_qa(operation_key,invoice_id,actor_id,action_type,before_status,after_status,invoice_fingerprint,request_sha256,reason,proof_json,created_at) VALUES (?,?,?,'invoice_block','aberta','bloqueada',?,?,?,?,?)")->execute([$operation,$id,$actor,$c['fingerprint'],$hash,$reason,self::json($proof),$c['server_reference']]);
            $phase='COMMIT';$db->commit();$owned=false;return ['ok'=>true,'http'=>200,'invoice_id'=>$id,'replayed'=>false,'historical_status'=>'bloqueada','current_status'=>'bloqueada','currently_blocked'=>true,'notification_sent'=>false];
        }catch(Throwable $e){if($owned && $db->inTransaction())$db->rollBack();return self::error('apply',$phase,$e);}
    }
    private static function error(string $function,string $phase,Throwable $e):array
    {
        $http=$e instanceof DomainException && in_array($e->getCode(),[403,404,409,422],true)?$e->getCode():500;$d=['project'=>'GuinchaFacil','class'=>self::class,'function'=>$function,'component'=>'invoice_block','phase'=>$phase,'status'=>$http===500?'FAIL':'REJECTED','error'=>$e->getMessage(),'file'=>basename($e->getFile()),'line'=>$e->getLine(),'exception'=>$e::class,'sqlstate'=>$e instanceof PDOException?($e->errorInfo[0]??null):null,'driver_code'=>$e instanceof PDOException?($e->errorInfo[1]??null):null,'remaining_percent'=>40];error_log(json_encode($d,JSON_THROW_ON_ERROR));return ['ok'=>false,'http'=>$http,'erro'=>$http===500?'bloqueio_indisponivel':$e->getMessage(),'diagnostic'=>$d];
    }
}

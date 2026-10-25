<?php
declare(strict_types=1);
/** Motor CLI exclusivo de QA. O objeto de comprovante deriva de GET simulado validado. */
final class GFInvoiceSettlementQA
{
    private static function json(array $a):string{return json_encode($a,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);}
    private static function deny(string $m,int $code=409):never{throw new DomainException($m,$code);}
    private static function paidProof(PDO $db,int $id,array $v,GFQaInvoiceReceipt $receipt):void
    {
        $p=GFReconciliation::rows($db,'SELECT * FROM faturas_pagamentos WHERE fatura_id=?',[$id],true);
        if($v['invoice']['status']!=='paga' || count($p)!==1 || $p[0]['transacao_id']!==$receipt->external || $p[0]['valor']!==$receipt->amount || $p[0]['payload']!==json_encode($receipt->data(),JSON_THROW_ON_ERROR) || $v['invoice']['pix_transacao_id']!==$receipt->external || $v['closed']['status']!=='liquidado')self::deny('baixa_sem_comprovante_exato');
    }
    /** Estado corrente sob locks; nenhuma fatura alheia e desbloqueada. */
    private static function blocking(PDO $db,int $partner):array
    {
        $q=$db->prepare("SELECT valor FROM configuracoes WHERE chave='bloqueio_financeiro_ativo' LOCK IN SHARE MODE");$q->execute();$flags=$q->fetchAll(PDO::FETCH_COLUMN);if(count($flags)>1 || !in_array($flags[0]??'0',['0','1'],true))self::deny('configuracao_de_bloqueio_invalida');
        $rows=GFReconciliation::rows($db,"SELECT id FROM faturas_parceiro WHERE parceiro_id=? AND status='bloqueada' ORDER BY id",[$partner],true);$ids=[];foreach($rows as $row){$id=(int)$row['id'];$v=GFDirectBilling::verifyInvoice($db,$id);if($v['invoice']['status']!=='bloqueada' || GFReconciliation::rows($db,'SELECT id FROM faturas_pagamentos WHERE fatura_id=?',[$id],true))self::deny('outra_divida_bloqueada_inconsistente');$ids[]=$id;}
        return ['policy_enabled'=>($flags[0]??'0')==='1','remaining_blocked_invoices'=>$ids,'partner_currently_blocked'=>($flags[0]??'0')==='1' && count($ids)>0,'state_scope'=>'current_locked_snapshot','notification_sent'=>false,'live_pix'=>false];
    }
    public static function settle(PDO $db,int $actor,int $id,string $key,?GFQaInvoiceReceipt $receipt,string $reason):array
    {
        $owned=false;$phase='GUARD';try{
            GFDirectBilling::guard($db);if($db->inTransaction())throw new LogicException('FIN_SETTLEMENT_OWNS_TRANSACTION_REQUIRED');$reason=trim($reason);
            if($id<1 || !preg_match('/^[a-f0-9]{32}$/D',$key) || !mb_check_encoding($reason,'UTF-8') || mb_strlen($reason,'UTF-8')<20 || mb_strlen($reason,'UTF-8')>500)self::deny('parametro_ou_motivo_invalido',422);
            if(!$receipt || $receipt->invoice!==$id)self::deny('comprovante_verificado_da_fatura_obrigatorio');
            $db->beginTransaction();$owned=true;$phase='AUTHORIZE';$q=$db->prepare('SELECT tipo,ativo FROM usuarios WHERE id=? LOCK IN SHARE MODE');$q->execute([$actor]);$u=$q->fetch(PDO::FETCH_ASSOC);if(!$u || $u['tipo']!=='admin' || (int)$u['ativo']!==1)self::deny('acesso_negado',403);
            $phase='LOCK_PARTNER';$q=$db->prepare('SELECT parceiro_id FROM faturas_parceiro WHERE id=?');$q->execute([$id]);$partner=$q->fetchColumn();if($partner===false)self::deny('fatura_nao_encontrada',404);$partner=(int)$partner;
            $p=GFReconciliation::rows($db,'SELECT id FROM guinchos WHERE id=?',[$partner],true);if(count($p)!==1)self::deny('parceiro_nao_encontrado');
            // Mesmo sentido de locks do fechamento e da baixa exata: parceiro -> faturas.
            GFReconciliation::rows($db,'SELECT id FROM faturas_parceiro WHERE parceiro_id=? ORDER BY id',[$partner],true);
            $phase='VERIFY_INVOICE';$v=GFDirectBilling::verifyInvoice($db,$id);if((int)$v['invoice']['parceiro_id']!==$partner)self::deny('parceiro_da_fatura_alterado');if($receipt->amount!==$v['amount'])self::deny('valor_do_comprovante_divergente');
            $f=$v['invoice'];$fingerprint=hash('sha256',self::json([$id,$partner,$f['ciclo_inicio'],$f['ciclo_fim'],$f['vencimento_em'],$f['total_faturado'],$f['total_repasse'],$f['saldo'],$v['closed']['snapshot_sha256']]));
            $request=['actor'=>$actor,'invoice'=>$id,'key'=>$key,'receipt'=>$receipt->data(),'reason'=>$reason];$hash=hash('sha256',self::json($request));$op=hash('sha256','r11ba|'.$actor.'|'.$key);$phase='REPLAY';$old=GFReconciliation::rows($db,'SELECT * FROM fin3_invoice_actions_qa WHERE operation_key=?',[$op],true);
            if($old){$a=$old[0];$proof=json_decode($a['proof_json'],true,512,JSON_THROW_ON_ERROR);
                if((int)$a['invoice_id']!==$id || (int)$a['actor_id']!==$actor || $a['action_type']!=='invoice_settle' || !in_array($a['before_status'],['aberta','bloqueada','vencida'],true) || $a['after_status']!=='paga' || $a['reason']!==$reason || $a['request_sha256']!==$hash || $a['invoice_fingerprint']!==$fingerprint || ($proof['version']??null)!=='R11BA' || ($proof['request']??null)!==$request || ($proof['before_invoice']['status']??null)!==$a['before_status'] || (int)($proof['before_invoice']['id']??0)!==$id || ($proof['before_sha256']??null)!==hash('sha256',self::json($proof['before_invoice']??[])) || ($proof['server_reference']??null)!==$a['created_at'])self::deny('idempotencia_divergente');
                self::paidProof($db,$id,$v,$receipt);$current=self::blocking($db,$partner);$phase='COMMIT';$db->commit();$owned=false;return ['ok'=>true,'http'=>200,'invoice_id'=>$id,'partner_id'=>$partner,'replayed'=>true,'current_status'=>'paga','historical_status'=>'paga']+$current;
            }
            if(!in_array($f['status'],['aberta','bloqueada','vencida'],true))self::deny('fatura_ja_paga_ou_nao_elegivel');
            if(GFReconciliation::rows($db,'SELECT id FROM faturas_pagamentos WHERE fatura_id=?',[$id],true))self::deny('comprovante_preexistente_inconsistente');
            $phase='PAY_EXACT';$paid=GFDirectBilling::pay($db,$id,$receipt);if(!$paid['ok'])self::deny('baixa_exata_rejeitada:'.$paid['erro']);if($paid['duplicate_verified'])self::deny('baixa_concorrente_exige_revisao');
            $after=GFDirectBilling::verifyInvoice($db,$id);self::paidProof($db,$id,$after,$receipt);$phase='REEVALUATE_PARTNER';$current=self::blocking($db,$partner);
            $now=(new DateTimeImmutable('now',new DateTimeZone('America/Sao_Paulo')))->format('Y-m-d H:i:s');$proof=['version'=>'R11BA','request'=>$request,'before_invoice'=>$f,'before_sha256'=>hash('sha256',self::json($f)),'server_reference'=>$now,'blocking_at_commit'=>$current];
            $phase='AUDIT';$db->prepare("INSERT INTO fin3_invoice_actions_qa(operation_key,invoice_id,actor_id,action_type,before_status,after_status,invoice_fingerprint,request_sha256,reason,proof_json,created_at) VALUES (?,?,?,'invoice_settle',?,'paga',?,?,?,?,?)")->execute([$op,$id,$actor,$f['status'],$fingerprint,$hash,$reason,self::json($proof),$now]);
            $phase='COMMIT';$db->commit();$owned=false;return ['ok'=>true,'http'=>200,'invoice_id'=>$id,'partner_id'=>$partner,'replayed'=>false,'current_status'=>'paga','historical_status'=>'paga']+$current;
        }catch(Throwable $e){if($owned && $db->inTransaction())$db->rollBack();$http=$e instanceof DomainException && in_array($e->getCode(),[403,404,409,422],true)?$e->getCode():500;$diagnostic=['project'=>'GuinchaFacil','class'=>self::class,'function'=>'settle','component'=>'invoice_settlement','phase'=>$phase,'status'=>$http===500?'FAIL':'REJECTED','error'=>$e->getMessage(),'exception'=>$e::class,'file'=>basename($e->getFile()),'line'=>$e->getLine(),'sqlstate'=>$e instanceof PDOException?($e->errorInfo[0]??null):null,'driver_code'=>$e instanceof PDOException?($e->errorInfo[1]??null):null,'remaining_percent'=>40];error_log(json_encode($diagnostic,JSON_THROW_ON_ERROR));return ['ok'=>false,'http'=>$http,'erro'=>$http===500?'baixa_indisponivel':$e->getMessage(),'diagnostic'=>$diagnostic];}
    }
}

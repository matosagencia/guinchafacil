<?php
declare(strict_types=1);
require_once __DIR__.'/GFIntegratedApproval.php';

/** Evidencia SINTETICA de QA. Nao e adaptador nem comprovante de um banco real. */
final class GFQaPayoutReceipt
{
    private function __construct(public int $jobId,public int $paymentId,
        public int $partnerId,public string $amount,public string $externalId) {}
    public static function confirmed(int $job,int $payment,int $partner,mixed $amount,string $external): self
    {
        GFIntegratedPayout::guard(getPDO());
        if ($job<1 || $payment<1 || $partner<1 || !preg_match('/^qa_pix_[a-zA-Z0-9_-]{1,100}$/D',$external)) throw new InvalidArgumentException('FIN_PAYOUT_QA_RECEIPT_INVALID');
        $amount=GFMoneyInput::decimal($amount);
        if ($amount==='0.00') throw new InvalidArgumentException('FIN_PAYOUT_ZERO');
        return new self($job,$payment,$partner,$amount,$external);
    }
    public function fingerprint(): string
    {
        return hash('sha256',json_encode([$this->jobId,$this->paymentId,$this->partnerId,$this->amount,$this->externalId],JSON_THROW_ON_ERROR));
    }
}

final class GFIntegratedPayout
{
    // R11AM: o pedido serializa as operacoes; evidencias e saldo usam leituras
    // atuais para nao reutilizar o snapshot aberto pelo lookup inicial do job.

    public static function guard(PDO $db): void
    {
        GFIntegratedApproval::guard($db);
        if (!defined('GF_FIN_PAYOUT_QA') || GF_FIN_PAYOUT_QA!==true) throw new LogicException('FIN_PAYOUT_QA_REQUIRED');
    }
    private static function atomic(PDO $db,string $function,callable $fn): array
    {
        $owner=false;$point=null;
        try {
            self::guard($db);
            if (!$db->inTransaction()) {$db->beginTransaction();$owner=true;}
            else {$point='al_'.bin2hex(random_bytes(8));$db->exec('SAVEPOINT '.$point);}
            $result=$fn();
            if ($owner) $db->commit();else $db->exec('RELEASE SAVEPOINT '.$point);
            self::log($function,'COMMIT_OR_RELEASE','PASS',['transaction_owner'=>$owner]+$result);
            return ['ok'=>true]+$result;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                if ($owner) $db->rollBack();
                elseif ($point!==null) {$db->exec('ROLLBACK TO SAVEPOINT '.$point);$db->exec('RELEASE SAVEPOINT '.$point);}
            }
            self::log($function,'REJECT_OR_ROLLBACK','FAIL',['error'=>$e->getMessage(),'exception'=>$e::class,'file'=>basename($e->getFile()),'line'=>$e->getLine(),'sqlstate'=>$e instanceof PDOException?($e->errorInfo[0]??null):null]);
            return ['ok'=>false,'erro'=>$e->getMessage()];
        }
    }
    public static function log(string $function,string $phase,string $status,array $context): void
    {
        $r=['project'=>'GuinchaFacil','class'=>self::class,'function'=>$function,'component'=>'payout_wallet','phase'=>$phase,'status'=>$status,'message'=>$context['error']??($function==='enqueue'?'Job de repasse QA criado ou conferido.':'Confirmacao sintetica QA contabilizada com valores exatos.'),'context'=>$context];
        if (isset($GLOBALS['gf_fin_log_sink'])) ($GLOBALS['gf_fin_log_sink'])($r);else error_log(json_encode($r,JSON_THROW_ON_ERROR));
    }
    private static function locked(PDO $db,int $order): array
    {
        $q=$db->prepare('SELECT * FROM pedidos WHERE id=? FOR UPDATE');$q->execute([$order]);$o=$q->fetch(PDO::FETCH_ASSOC);
        $q=$db->prepare('SELECT * FROM pagamentos WHERE pedido_id=? FOR UPDATE');$q->execute([$order]);$rows=$q->fetchAll(PDO::FETCH_ASSOC);
        if (!$o || count($rows)!==1) throw new DomainException('FIN_PAYOUT_ORDER_OR_PAYMENT_AMBIGUOUS');
        $p=$rows[0];
        if ($o['status']!=='concluido' || $o['attendance_mode']!=='TOWING' || empty($o['guincho_id']) || $p['status']!=='aprovado' || $p['metodo']!=='mercadopago') throw new DomainException('FIN_PAYOUT_NOT_ELIGIBLE');
        $q=$db->prepare('SELECT aprovado,chave_pix,chave_pix_tipo FROM guinchos WHERE id=? FOR UPDATE');$q->execute([$o['guincho_id']]);$g=$q->fetch(PDO::FETCH_ASSOC);
        if (!$g || (int)$g['aprovado']!==1 || empty($g['chave_pix']) || empty($g['chave_pix_tipo'])) throw new DomainException('FIN_PAYOUT_PARTNER_PIX_REQUIRED');
        $q=$db->prepare('SELECT * FROM fin3_operational_approval WHERE pedido_id=? AND pagamento_id=? FOR UPDATE');$q->execute([$order,$p['id']]);$approval=$q->fetch(PDO::FETCH_ASSOC);
        if ($approval===false) throw new DomainException('FIN_PAYOUT_APPROVAL_REQUIRED');
        $split=json_decode($approval['split_json'],true,512,JSON_THROW_ON_ERROR);
        if ($split['partner']!==$p['valor_guincho'] || $split['platform']!==$p['valor_plataforma'] || $o['valor_liquido_parceiro']!==$p['valor_guincho'] || $split['gross']!==$p['valor_total'] || $split['gross']!==$o['custo_final'] || $split['gross']!==$o['valor_final'] || $split['platform']!==$o['comissao_valor'] || $approval['external_id']!==$p['id_externo'] || $approval['provider']!==$p['metodo']) throw new DomainException('FIN_PAYOUT_FROZEN_SPLIT_DRIFT');
        $q=$db->prepare("SELECT pedido_id,entry_type,valor,referencia_externa,metadata_json FROM payout_ledger_entries WHERE pagamento_id=? AND entry_type IN ('credito_guincho','credito_plataforma','reserva_gateway') FOR UPDATE");$q->execute([$p['id']]);$physical=[];$expected=[];
        foreach (['partner'=>'credito_guincho','platform'=>'credito_plataforma','gateway'=>'reserva_gateway'] as $field=>$type) if (GFExactMoney::cents($split[$field])>0) $expected[$type]=$split[$field];
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $entry) {
            $meta=json_decode($entry['metadata_json'],true,512,JSON_THROW_ON_ERROR);
            if ((int)$entry['pedido_id']!==$order || isset($physical[$entry['entry_type']]) || $entry['referencia_externa']!==$p['id_externo'] || ($meta['fin3_event_key']??null)!==$approval['event_key'] || ($meta['fingerprint']??null)!==$approval['fingerprint']) throw new DomainException('FIN_PAYOUT_APPROVAL_LEDGER_DRIFT');
            $physical[$entry['entry_type']]=$entry['valor'];
        }
        ksort($physical,SORT_STRING);ksort($expected,SORT_STRING);
        if ($physical!==$expected) throw new DomainException('FIN_PAYOUT_APPROVAL_LEDGER_DRIFT');
        $q=$db->prepare("SELECT COUNT(*) FROM fin3_operational_inbox WHERE event_key=? AND fingerprint=? AND status='done' FOR UPDATE");$q->execute([$approval['event_key'],$approval['fingerprint']]);
        if ((int)$q->fetchColumn()!==1) throw new DomainException('FIN_PAYOUT_APPROVAL_INBOX_DRIFT');
        $balance=self::balance($db,(int)$p['id']);
        if ($balance['credit']!==GFExactMoney::cents($p['valor_guincho'])) throw new DomainException('FIN_PAYOUT_LEDGER_CREDIT_DRIFT');
        if ((int)$p['pago_guincho']!==($balance['remaining']===0?1:0)) throw new DomainException('FIN_PAYOUT_FLAG_DRIFT');
        return [$o,$p,$balance];
    }
    public static function balance(PDO $db,int $payment): array
    {
        $q=$db->prepare('SELECT entry_type,valor FROM payout_ledger_entries WHERE pagamento_id=? FOR UPDATE');$q->execute([$payment]);
        $credit=0;$paid=0;$refund=0;
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $v=GFExactMoney::cents($r['valor']);
            switch ($r['entry_type']) {
                case 'credito_guincho': $credit+=$v;break;
                case 'debito_repasse_guincho':$paid+=$v;break;
                case 'estorno_credito_guincho':$refund+=$v;break;
            }
        }
        $remaining=$credit-$paid-$refund;
        if ($remaining<0) throw new DomainException('FIN_PAYOUT_NEGATIVE_BALANCE');
        return ['credit'=>$credit,'paid'=>$paid,'refund'=>$refund,'remaining'=>$remaining];
    }
    public static function enqueue(PDO $db,int $order,mixed $amount,mixed $platform,string $part='full'): array
    {
        return self::atomic($db,__FUNCTION__,static function() use($db,$order,$amount,$platform,$part):array {
            $amount=GFMoneyInput::decimal($amount);$platform=GFMoneyInput::decimal($platform);
            if ($amount==='0.00' || !preg_match('/^[a-zA-Z0-9_-]{1,48}$/D',$part)) throw new InvalidArgumentException('FIN_PAYOUT_PART_INVALID');
            [$o,$p,$b]=self::locked($db,$order);
            if ($platform!==$p['valor_plataforma']) throw new DomainException('FIN_PAYOUT_PLATFORM_DRIFT');
            $key='r11al_payout:'.$order.':'.$part;
            $payload=['pedido_id'=>$order,'pagamento_id'=>(int)$p['id'],'guincho_id'=>(int)$o['guincho_id'],'amount'=>$amount,'platform'=>$platform,'part'=>$part,'version'=>'R11AL'];
            $json=json_encode($payload,JSON_THROW_ON_ERROR);
            $q=$db->prepare('SELECT * FROM payment_jobs WHERE idempotency_key=? FOR UPDATE');$q->execute([$key]);$old=$q->fetch(PDO::FETCH_ASSOC);
            if ($old) {
                if ($old['payload_json']!==$json || (int)$old['pagamento_id']!==(int)$p['id']) throw new DomainException('FIN_PAYOUT_JOB_IDEMPOTENCY_DRIFT');
                return ['job_id'=>(int)$old['id'],'queued'=>false,'status'=>$old['status']];
            }
            $reserved=0;
            $q=$db->prepare("SELECT payload_json FROM payment_jobs WHERE pagamento_id=? AND job_type='pix_payout' AND status IN ('queued','retry','running') FOR UPDATE");$q->execute([$p['id']]);
            foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $raw) {
                $r=json_decode($raw,true,512,JSON_THROW_ON_ERROR);
                if (($r['version']??'')!=='R11AL') throw new DomainException('FIN_PAYOUT_LEGACY_JOB_NOT_MIGRATED');
                $reserved+=GFExactMoney::cents(GFMoneyInput::decimal($r['amount']??null));
            }
            if (GFExactMoney::cents($amount)>$b['remaining']-$reserved) throw new DomainException('FIN_PAYOUT_EXCEEDS_AVAILABLE');
            $db->prepare("INSERT INTO payment_jobs(pedido_id,pagamento_id,job_type,idempotency_key,status,payload_json) VALUES (?,?,'pix_payout',?,'queued',?)")->execute([$order,$p['id'],$key,$json]);
            return ['job_id'=>(int)$db->lastInsertId(),'queued'=>true,'status'=>'queued'];
        });
    }
    public static function confirm(PDO $db,int $jobId,?GFQaPayoutReceipt $receipt): array
    {
        return self::atomic($db,__FUNCTION__,static function() use($db,$jobId,$receipt):array {
            if (!$receipt || $receipt->jobId!==$jobId) throw new DomainException('FIN_PAYOUT_CONFIRMED_RECEIPT_REQUIRED');
            $q=$db->prepare('SELECT pedido_id FROM payment_jobs WHERE id=?');$q->execute([$jobId]);$order=$q->fetchColumn();
            if ($order===false) throw new DomainException('FIN_PAYOUT_JOB_MISSING');
            [$o,$p,$b]=self::locked($db,(int)$order);
            $q=$db->prepare('SELECT * FROM payment_jobs WHERE id=? FOR UPDATE');$q->execute([$jobId]);$job=$q->fetch(PDO::FETCH_ASSOC);
            $payload=json_decode($job['payload_json'],true,512,JSON_THROW_ON_ERROR);
            if ($job['job_type']!=='pix_payout' || (int)$job['pedido_id']!==(int)$o['id'] || (int)$job['pagamento_id']!==(int)$p['id'] || ($payload['version']??'')!=='R11AL' || (int)$p['id']!==$receipt->paymentId || (int)$o['guincho_id']!==$receipt->partnerId || ($payload['guincho_id']??null)!==$receipt->partnerId || ($payload['amount']??null)!==$receipt->amount || ($payload['platform']??null)!==$p['valor_plataforma']) throw new DomainException('FIN_PAYOUT_RECEIPT_JOB_MISMATCH');
            $key=hash('sha256','payout-v1|qa_pix|'.$receipt->externalId);$fp=$receipt->fingerprint();
            $q=$db->prepare('SELECT * FROM fin3_operational_inbox WHERE event_key=? FOR UPDATE');$q->execute([$key]);$inbox=$q->fetch(PDO::FETCH_ASSOC);
            $q=$db->prepare("SELECT pedido_id,pagamento_id,valor,metadata_json FROM payout_ledger_entries WHERE entry_type='debito_repasse_guincho' AND referencia_externa=? FOR UPDATE");$q->execute([$receipt->externalId]);$entries=$q->fetchAll(PDO::FETCH_ASSOC);
            if ($inbox) {
                if (!hash_equals($inbox['fingerprint'],$fp) || $inbox['status']!=='done' || $inbox['claim_token']!==null || $inbox['lease_until']!==null || count($entries)!==1 || $job['status']!=='completed') throw new DomainException('FIN_PAYOUT_IDEMPOTENCY_DRIFT');
                $entry=$entries[0];$meta=json_decode($entry['metadata_json'],true,512,JSON_THROW_ON_ERROR);
                if ((int)$entry['pagamento_id']!==$receipt->paymentId || (int)$entry['pedido_id']!==(int)$o['id'] || $entry['valor']!==$receipt->amount || ($meta['fingerprint']??null)!==$fp || ($meta['payout_event_key']??null)!==$key || ($meta['job_id']??null)!==$jobId) throw new DomainException('FIN_PAYOUT_PHYSICAL_LEDGER_DRIFT');
                $q=$db->prepare('SELECT COUNT(*) FROM payment_job_attempts WHERE payment_job_id=? AND success=1 FOR UPDATE');$q->execute([$jobId]);
                if ((int)$q->fetchColumn()!==1) throw new DomainException('FIN_PAYOUT_ATTEMPT_DRIFT');
                return ['duplicate_verified'=>true,'job_id'=>$jobId,'remaining'=>GFExactMoney::decimal($b['remaining'])];
            }
            if ($entries!==[] || $job['status']!=='queued' || $job['attempt_count']!=0) throw new DomainException('FIN_PAYOUT_JOB_OR_LEDGER_DRIFT');
            $cents=GFExactMoney::cents($receipt->amount);
            if ($cents>$b['remaining']) throw new DomainException('FIN_PAYOUT_EXCEEDS_AVAILABLE');
            $token=bin2hex(random_bytes(16));
            $db->prepare("INSERT INTO fin3_operational_inbox(event_key,fingerprint,status,claim_token,lease_until) VALUES (?,?,'processing',?,DATE_ADD(NOW(6),INTERVAL 90 SECOND))")->execute([$key,$fp,$token]);
            PayoutLedgerEntry::registrar($db,['pagamento_id'=>$receipt->paymentId,'pedido_id'=>(int)$o['id'],'entry_type'=>'debito_repasse_guincho','valor'=>$receipt->amount,'referencia_externa'=>$receipt->externalId,'metadata'=>['payout_event_key'=>$key,'fingerprint'=>$fp,'job_id'=>$jobId,'guincho_id'=>$receipt->partnerId,'provider'=>'qa_pix']]);
            $remaining=$b['remaining']-$cents;
            $db->prepare("UPDATE pagamentos SET id_transacao_pix=?,status_pix=?,pago_guincho=?,data_pagamento_guincho=NOW() WHERE id=?")->execute([$receipt->externalId,$remaining===0?'concluido':'processando',$remaining===0?1:0,$p['id']]);
            $db->prepare("INSERT INTO payment_job_attempts(payment_job_id,attempt_number,worker_id,success,response_json,created_at) VALUES (?,1,'r11al_qa_confirmation',1,?,NOW())")->execute([$jobId,json_encode(['provider'=>'qa_pix','external_id'=>$receipt->externalId,'amount'=>$receipt->amount],JSON_THROW_ON_ERROR)]);
            $db->prepare("UPDATE payment_jobs SET status='completed',attempt_count=1,worker_id='r11al_qa_confirmation',finished_at=NOW(),updated_at=NOW() WHERE id=?")->execute([$jobId]);
            $q=$db->prepare("UPDATE fin3_operational_inbox SET status='done',claim_token=NULL,lease_until=NULL WHERE event_key=? AND claim_token=?");$q->execute([$key,$token]);
            if ($q->rowCount()!==1) throw new RuntimeException('FIN_PAYOUT_INBOX_TOKEN_LOST');
            return ['duplicate_verified'=>false,'job_id'=>$jobId,'remaining'=>GFExactMoney::decimal($remaining)];
        });
    }
}

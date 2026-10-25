<?php
declare(strict_types=1);
require_once __DIR__.'/GFDirectBilling.php';
final class GFReconciliation
{
    public static function guard(PDO $db):void {GFDirectBilling::guard($db);}
    private static function check(bool $ok,string $reason):void {if(!$ok)throw new DomainException($reason);}
    public static function rows(PDO $db,string $sql,array $args=[],bool $lock=false):array
    {
        $q=$db->prepare($sql.($lock?' FOR UPDATE':''));$q->execute($args);return $q->fetchAll(PDO::FETCH_ASSOC);
    }
    public static function online(PDO $db,int $payment,bool $lock=false):array
    {
        self::guard($db);$p=self::rows($db,'SELECT * FROM pagamentos WHERE id=?',[$payment],$lock);self::check(count($p)===1,'FIN_RECON_PAYMENT_MISSING');$p=$p[0];
        $o=self::rows($db,'SELECT * FROM pedidos WHERE id=?',[$p['pedido_id']],$lock);$a=self::rows($db,'SELECT * FROM fin3_operational_approval WHERE pagamento_id=?',[$payment],$lock);
        self::check(count($o)===1 && count($a)===1,'FIN_RECON_APPROVAL_MISSING');$o=$o[0];$a=$a[0];$s=json_decode($a['split_json'],true,512,JSON_THROW_ON_ERROR);
        self::check($p['status']==='aprovado' && $p['metodo']==='mercadopago' && $a['provider']===$p['metodo'] && $a['external_id']===$p['id_externo'] && (int)$a['pedido_id']===(int)$o['id'] && $o['comissao_tipo']==='descontada','FIN_RECON_APPROVAL_STATE_DRIFT');
        foreach(['gross','partner','platform','gateway'] as $field)$s[$field]=GFMoneyInput::decimal($s[$field]??null);
        self::check(GFExactMoney::cents($s['gross'])===GFExactMoney::cents($s['partner'])+GFExactMoney::cents($s['platform'])+GFExactMoney::cents($s['gateway']) && $s['gross']===$p['valor_total'] && $s['partner']===$p['valor_guincho'] && $s['platform']===$p['valor_plataforma'] && $o['custo_final']===$s['gross'] && $o['valor_final']===$s['gross'] && $o['valor_liquido_parceiro']===$s['partner'] && $o['comissao_valor']===$s['platform'],'FIN_RECON_FROZEN_SPLIT_DRIFT');
        $expected=[];foreach(['partner'=>'credito_guincho','platform'=>'credito_plataforma','gateway'=>'reserva_gateway'] as $field=>$type)if($s[$field]!=='0.00')$expected[$type]=$s[$field];
        $entries=self::rows($db,"SELECT * FROM payout_ledger_entries WHERE pagamento_id=? AND entry_type IN ('credito_guincho','credito_plataforma','reserva_gateway')",[$payment],$lock);$actual=[];
        foreach($entries as $e) {
            $m=json_decode($e['metadata_json'],true,512,JSON_THROW_ON_ERROR);
            self::check((int)$e['pedido_id']===(int)$o['id'] && !isset($actual[$e['entry_type']]) && $e['referencia_externa']===$a['external_id'] && ($m['fin3_event_key']??null)===$a['event_key'] && ($m['fingerprint']??null)===$a['fingerprint'],'FIN_RECON_CREDIT_METADATA_DRIFT');$actual[$e['entry_type']]=$e['valor'];
        }
        ksort($expected,SORT_STRING);ksort($actual,SORT_STRING);self::check($actual===$expected,'FIN_RECON_PHYSICAL_CREDIT_DRIFT');
        $inbox=self::rows($db,'SELECT * FROM fin3_operational_inbox WHERE event_key=?',[$a['event_key']],$lock);
        self::check(count($inbox)===1 && $inbox[0]['fingerprint']===$a['fingerprint'] && $inbox[0]['status']==='done' && $inbox[0]['claim_token']===null && $inbox[0]['lease_until']===null,'FIN_RECON_APPROVAL_INBOX_DRIFT');
        $events=self::rows($db,"SELECT id FROM pedido_eventos WHERE pedido_id=? AND evento='pagamento_aprovado'",[$o['id']],$lock);self::check(count($events)===1,'FIN_RECON_ORDER_EVENT_DRIFT');
        $out=self::rows($db,"SELECT * FROM fin3_post_commit_outbox WHERE event_key=? AND event_type='payment_approved'",[$a['event_key']],$lock);self::check(count($out)===1,'FIN_RECON_OUTBOX_MISSING');
        $payload=json_decode($out[0]['payload_json'],true,512,JSON_THROW_ON_ERROR);$want=['pedido_id'=>(int)$o['id'],'pagamento_id'=>$payment];ksort($payload,SORT_STRING);ksort($want,SORT_STRING);self::check($payload===$want,'FIN_RECON_OUTBOX_PAYLOAD_DRIFT');
        return ['order'=>$o,'payment'=>$p,'approval'=>$a,'split'=>$s,'outbox'=>$out[0]];
    }
    public static function scan(PDO $db):array
    {
        self::guard($db);self::check($db->inTransaction() && $db->query('SELECT @@tx_isolation')->fetchColumn()==='REPEATABLE-READ','FIN_RECON_CONSISTENT_TRANSACTION_REQUIRED');
        $started=$db->query('SELECT NOW(6)')->fetchColumn();$findings=[];$counts=['online'=>0,'direct'=>0,'invoices'=>0,'payouts'=>0,'outbox_done'=>0,'outbox_pending'=>0,'outbox_failed'=>0];$digest=hash_init('sha256');
        $capture=static function(string $component,int|string $id,callable $fn)use(&$findings,$digest):void {
            try{$evidence=$fn();hash_update($digest,json_encode([$component,$id,$evidence],JSON_THROW_ON_ERROR)."\n");}
            catch(Throwable $e){$findings[]=['component'=>$component,'entity_id'=>$id,'class'=>self::class,'function'=>'scan','phase'=>'VERIFY','code'=>$e->getMessage(),'exception'=>$e::class,'file'=>basename($e->getFile()),'line'=>$e->getLine()];}
        };
        $payments=self::rows($db,"SELECT * FROM pagamentos WHERE status='aprovado' ORDER BY id");$approvedIds=[];
        foreach($payments as $p) {
            $id=(int)$p['id'];$approvedIds[$id]=true;
            if($p['metodo']==='mercadopago') {
                $counts['online']++;
                $capture('online',$id,static function()use($db,$id,&$counts):array {
                    $v=self::online($db,$id);$out=$v['outbox'];$counts['outbox_'.$out['status']]++;
                    if($out['status']==='done') {
                        $d=self::rows($db,'SELECT * FROM fin3_outbox_delivery_qa WHERE outbox_id=?',[$out['id']]);
                        self::check(count($d)===1 && $d[0]['state']==='confirmed' && $d[0]['claim_token']===null && $d[0]['lease_until']===null && $d[0]['fingerprint']===GFQaOutboxDispatcher::fingerprint($out),'FIN_RECON_DONE_WITHOUT_DELIVERY');
                        GFQaOutboxDispatcher::validateAck(json_decode($d[0]['ack_json'],true,512,JSON_THROW_ON_ERROR),GFQaOutboxDispatcher::key($out),$d[0]['fingerprint']);
                    }
                    return ['approval'=>$v['approval']['event_key'],'split'=>$v['split'],'outbox_status'=>$out['status']];
                });
            } elseif($p['metodo']==='dinheiro') {
                $counts['direct']++;$capture('direct',$id,static function()use($db,$p):array {$o=self::rows($db,'SELECT * FROM pedidos WHERE id=?',[$p['pedido_id']]);self::check(count($o)===1,'FIN_RECON_DIRECT_ORDER_MISSING');return GFDirectBilling::directEvidence($db,$o[0],$p);});
            } else $findings[]=['component'=>'payment','entity_id'=>$id,'code'=>'FIN_RECON_PROVIDER_NOT_MIGRATED','method'=>$p['metodo']];
        }
        foreach(self::rows($db,'SELECT pagamento_id,event_key FROM fin3_operational_approval ORDER BY event_key') as $a)if(!isset($approvedIds[(int)$a['pagamento_id']]))$findings[]=['component'=>'approval','entity_id'=>$a['event_key'],'code'=>'FIN_RECON_APPROVAL_WITHOUT_APPROVED_PAYMENT'];
        foreach(self::rows($db,'SELECT DISTINCT pagamento_id FROM payout_ledger_entries ORDER BY pagamento_id') as $e)if(!isset($approvedIds[(int)$e['pagamento_id']]))$findings[]=['component'=>'ledger','entity_id'=>$e['pagamento_id'],'code'=>'FIN_RECON_LEDGER_WITHOUT_APPROVED_PAYMENT'];
        foreach(self::rows($db,"SELECT * FROM payment_jobs WHERE job_type='pix_payout' ORDER BY id") as $job) {
            $capture('payout_job',(int)$job['id'],static function()use($db,$job,&$counts):array {
                $payload=json_decode($job['payload_json'],true,512,JSON_THROW_ON_ERROR);self::check(($payload['version']??null)==='R11AL','FIN_RECON_PAYOUT_JOB_NOT_MIGRATED');
                $orders=self::rows($db,'SELECT guincho_id FROM pedidos WHERE id=?',[$job['pedido_id']]);$payments=self::rows($db,'SELECT pedido_id,valor_plataforma,metodo,status FROM pagamentos WHERE id=?',[$job['pagamento_id']]);
                self::check(count($orders)===1 && count($payments)===1 && (int)$payments[0]['pedido_id']===(int)$job['pedido_id'] && $payments[0]['metodo']==='mercadopago' && $payments[0]['status']==='aprovado' && ($payload['pedido_id']??null)===(int)$job['pedido_id'] && ($payload['pagamento_id']??null)===(int)$job['pagamento_id'] && ($payload['guincho_id']??null)===(int)$orders[0]['guincho_id'] && ($payload['platform']??null)===$payments[0]['valor_plataforma'],'FIN_RECON_PAYOUT_IDENTITY_DRIFT');
                $debits=self::rows($db,"SELECT * FROM payout_ledger_entries WHERE pagamento_id=? AND entry_type='debito_repasse_guincho'",[$job['pagamento_id']]);$matches=[];
                foreach($debits as $e){$meta=json_decode($e['metadata_json'],true,512,JSON_THROW_ON_ERROR);if(($meta['job_id']??null)===(int)$job['id'])$matches[]=[$e,$meta];}
                $attempts=self::rows($db,'SELECT * FROM payment_job_attempts WHERE payment_job_id=?',[$job['id']]);
                if($job['status']==='completed') {
                    self::check(count($matches)===1 && count($attempts)===1 && (int)$attempts[0]['success']===1 && (int)$job['attempt_count']===1,'FIN_RECON_PAYOUT_JOB_LEDGER_DRIFT');[$e,$meta]=$matches[0];
                    $key=hash('sha256','payout-v1|qa_pix|'.$e['referencia_externa']);$fp=hash('sha256',json_encode([(int)$job['id'],(int)$job['pagamento_id'],$payload['guincho_id'],$payload['amount'],$e['referencia_externa']],JSON_THROW_ON_ERROR));
                    self::check($e['valor']===$payload['amount'] && (int)$e['pedido_id']===(int)$job['pedido_id'] && ($meta['payout_event_key']??null)===$key && ($meta['fingerprint']??null)===$fp,'FIN_RECON_PAYOUT_PROOF_DRIFT');
                    $inbox=self::rows($db,'SELECT * FROM fin3_operational_inbox WHERE event_key=?',[$key]);self::check(count($inbox)===1 && $inbox[0]['fingerprint']===$fp && $inbox[0]['status']==='done' && $inbox[0]['claim_token']===null && $inbox[0]['lease_until']===null,'FIN_RECON_PAYOUT_INBOX_DRIFT');$response=json_decode($attempts[0]['response_json'],true,512,JSON_THROW_ON_ERROR);self::check(($meta['guincho_id']??null)===$payload['guincho_id'] && ($response['external_id']??null)===$e['referencia_externa'] && ($response['amount']??null)===$e['valor'],'FIN_RECON_PAYOUT_ATTEMPT_RECEIPT_DRIFT');$counts['payouts']++;
                } else self::check($matches===[] && $attempts===[],'FIN_RECON_UNFINISHED_PAYOUT_HAS_DEBIT');
                return ['id'=>(int)$job['id'],'status'=>$job['status'],'amount'=>$payload['amount']];
            });
        }
        $capture('payout_coverage','global',static function()use($db,&$counts):array {
            $n=(int)$db->query("SELECT COUNT(*) FROM payout_ledger_entries WHERE entry_type='debito_repasse_guincho'")->fetchColumn();self::check($n===$counts['payouts'],'FIN_RECON_ORPHAN_OR_DUPLICATE_PAYOUT_DEBIT');
            foreach(self::rows($db,"SELECT DISTINCT pagamento_id FROM payment_jobs WHERE job_type='pix_payout'") as $r) {
                $remaining=0;$reserved=0;
                foreach(self::rows($db,'SELECT entry_type,valor FROM payout_ledger_entries WHERE pagamento_id=?',[$r['pagamento_id']]) as $entry) {
                    $amount=GFExactMoney::cents($entry['valor']);$remaining+=match($entry['entry_type']) {'credito_guincho'=>$amount,'debito_repasse_guincho','estorno_credito_guincho'=>-$amount,default=>0};
                }
                foreach(self::rows($db,"SELECT payload_json FROM payment_jobs WHERE pagamento_id=? AND job_type='pix_payout' AND status IN ('queued','retry','running')",[$r['pagamento_id']]) as $j) {
                    $payload=json_decode($j['payload_json'],true,512,JSON_THROW_ON_ERROR);$reserved+=GFExactMoney::cents(GFMoneyInput::decimal($payload['amount']??null));
                }
                self::check($remaining>=0 && $reserved<=$remaining,'FIN_RECON_RESERVED_BALANCE_EXCEEDED');
            }
            return ['payout_debits'=>$n];
        });
        foreach(self::rows($db,'SELECT id FROM faturas_parceiro ORDER BY id') as $row) {
            $counts['invoices']++;$capture('invoice',(int)$row['id'],static function()use($db,$row):array {
                $v=GFDirectBilling::verifyInvoice($db,(int)$row['id'],false);$pays=self::rows($db,'SELECT * FROM faturas_pagamentos WHERE fatura_id=?',[$row['id']]);
                if($v['invoice']['status']==='paga') {
                    self::check(count($pays)===1 && $pays[0]['transacao_id']===$v['invoice']['pix_transacao_id'] && $pays[0]['valor']===$v['amount'],'FIN_RECON_INVOICE_PAYMENT_DRIFT');
                    $proof=json_decode($pays[0]['payload'],true,512,JSON_THROW_ON_ERROR);self::check(count($proof)===4 && $proof[0]===(int)$row['id'] && $proof[1]===$v['amount'] && $proof[2]===$pays[0]['transacao_id'],'FIN_RECON_INVOICE_RECEIPT_DRIFT');
                } else self::check($pays===[],'FIN_RECON_UNPAID_INVOICE_HAS_PAYMENT');
                return ['id'=>(int)$row['id'],'snapshot'=>$v['closed']['snapshot_sha256'],'status'=>$v['invoice']['status'],'amount'=>$v['amount']];
            });
        }
        $capture('wallet','global',static function():array {$r=CarteiraService::checarReconciliacaoGlobal();self::check($r['ok'] && $r['consistente'],'FIN_RECON_WALLET_AGGREGATE_DRIFT');return $r;});
        return ['version'=>'R11AP','scan_started_at'=>$started,'scan_finished_at'=>$db->query('SELECT NOW(6)')->fetchColumn(),'status'=>$findings===[]?'PASS':'FAIL','counts'=>$counts,'findings'=>$findings,'evidence_sha256'=>hash_final($digest),'limits'=>['gateway_real'=>false,'refund_flow'=>'NOT_MIGRATED','sink'=>'LOCAL_QA_ONLY']];
    }
    public static function run(PDO $db,string $key):array
    {
        $owned=false;
        try {
            self::guard($db);self::check(!$db->inTransaction(),'FIN_RECON_OWNS_TRANSACTION_REQUIRED');self::check((bool)preg_match('/^[a-f0-9]{32}$/D',$key),'FIN_RECON_RUN_KEY_INVALID');
            $old=self::rows($db,'SELECT * FROM fin3_reconciliation_runs_qa WHERE run_key=?',[$key]);
            if($old){$r=$old[0];self::check(hash('sha256',$r['report_json'])===$r['report_sha256'],'FIN_RECON_STORED_REPORT_DRIFT');return ['ok'=>$r['status']==='PASS','cached'=>true,'run_id'=>(int)$r['id'],'report'=>json_decode($r['report_json'],true,512,JSON_THROW_ON_ERROR)];}
            // Primeira leitura InnoDB fixa um snapshot REPEATABLE READ para toda a auditoria.
            $db->beginTransaction();$owned=true;$report=self::scan($db);$json=json_encode($report,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);
            $db->prepare('INSERT INTO fin3_reconciliation_runs_qa(run_key,status,report_json,report_sha256) VALUES (?,?,?,?)')->execute([$key,$report['status'],$json,hash('sha256',$json)]);$id=(int)$db->lastInsertId();$db->commit();$owned=false;
            return ['ok'=>$report['status']==='PASS','cached'=>false,'run_id'=>$id,'report'=>$report];
        } catch(Throwable $e) {
            if($owned && $db->inTransaction())$db->rollBack();return ['ok'=>false,'project'=>'GuinchaFacil','class'=>self::class,'function'=>'run','component'=>'financial_reconciliation','phase'=>'CONTROL','erro'=>$e->getMessage(),'exception'=>$e::class,'file'=>basename($e->getFile()),'line'=>$e->getLine(),'sqlstate'=>$e instanceof PDOException?($e->errorInfo[0]??null):null,'driver_code'=>$e instanceof PDOException?($e->errorInfo[1]??null):null];
        }
    }
}

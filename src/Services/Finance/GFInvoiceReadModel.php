<?php
declare(strict_types=1);
/** Consulta administrativa CLI de QA; nao e uma API autenticada ou cobranca. */
final class GFInvoiceReadModel
{
    public static function time(string $value):DateTimeImmutable
    {
        $date=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$value,new DateTimeZone('America/Sao_Paulo'));
        if(!$date || $date->format('Y-m-d H:i:s')!==$value)throw new InvalidArgumentException('FIN_READMODEL_INVALID_TIME');
        return $date;
    }
    public static function bucket(string $due,string $reference,bool $paid):array
    {
        $d=self::time($due);$at=self::time($reference);
        if($paid)return ['bucket'=>'paid','overdue_days'=>0,'overdue'=>false];
        if($d>=$at)return ['bucket'=>'not_due','overdue_days'=>0,'overdue'=>false];
        $days=(int)$d->setTime(0,0)->diff($at->setTime(0,0))->days;
        return ['bucket'=>$days<=7?'overdue_0_7':($days<=30?'overdue_8_30':($days<=60?'overdue_31_60':'overdue_61_plus')),'overdue_days'=>$days,'overdue'=>true];
    }
    /** Explicit snapshot API: caller owns transaction. Does not commit/rollback or lock rows. */
    public static function scan(PDO $db,?int $partner,string $reference):array
    {
        GFDirectBilling::guard($db);
        if(!$db->inTransaction())throw new LogicException('FIN_READMODEL_SNAPSHOT_REQUIRED');
        if($db->query('SELECT @@tx_isolation')->fetchColumn()!=='REPEATABLE-READ')throw new LogicException('FIN_READMODEL_REPEATABLE_READ_REQUIRED');
        self::time($reference);
        if($partner!==null && $partner<=0)throw new InvalidArgumentException('FIN_READMODEL_INVALID_PARTNER');
        $sql="SELECT id FROM faturas_parceiro WHERE parceiro_tipo='guincho'";
        $ids=GFReconciliation::rows($db,$sql.($partner===null?'':' AND parceiro_id=?').' ORDER BY vencimento_em,id',$partner===null?[]:[$partner]);
        $totals=['invoiced'=>0,'paid'=>0,'outstanding'=>0,'overdue'=>0];
        $buckets=array_fill_keys(['not_due','overdue_0_7','overdue_8_30','overdue_31_60','overdue_61_plus','paid'],0);$rows=[];
        foreach($ids as $id) {
            $v=GFDirectBilling::verifyInvoice($db,(int)$id['id'],false);$f=$v['invoice'];
            $pays=GFReconciliation::rows($db,'SELECT * FROM faturas_pagamentos WHERE fatura_id=?',[$f['id']]);$paid=$f['status']==='paga';
            if($paid) {
                if(count($pays)!==1 || $pays[0]['transacao_id']!==$f['pix_transacao_id'] || $pays[0]['valor']!==$v['amount'])throw new DomainException('FIN_READMODEL_PAYMENT_DRIFT');
                $proof=json_decode($pays[0]['payload'],true,512,JSON_THROW_ON_ERROR);
                if(!is_array($proof) || count($proof)!==4 || $proof[0]!== (int)$f['id'] || $proof[1]!==$v['amount'] || $proof[2]!==$pays[0]['transacao_id'])throw new DomainException('FIN_READMODEL_RECEIPT_DRIFT');
            }elseif($pays!==[])throw new DomainException('FIN_READMODEL_UNPAID_HAS_PAYMENT');
            $age=self::bucket((string)$f['vencimento_em'],$reference,$paid);$cents=GFExactMoney::cents($v['amount']);
            foreach(['invoiced',($paid?'paid':'outstanding')] as $key)self::add($totals[$key],$cents);
            if($age['overdue'])self::add($totals['overdue'],$cents);
            self::add($buckets[$age['bucket']],$cents);
            $rows[]=['invoice_id'=>(int)$f['id'],'partner_id'=>(int)$f['parceiro_id'],'cycle_start'=>$f['ciclo_inicio'],'cycle_end'=>$f['ciclo_fim'],'due_at'=>$f['vencimento_em'],'stored_status'=>$f['status'],'amount'=>$v['amount'],'outstanding'=>$paid?'0.00':$v['amount']]+$age;
        }
        $money=static fn(array $a):array=>array_map(static fn(int $n):string=>GFExactMoney::decimal($n),$a);
        $body=['version'=>'R11AQ','scope'=>'QA_ADMIN_CLI','reference'=>$reference,'timezone'=>'America/Sao_Paulo','partner_id'=>$partner,'reference_semantics'=>'CURRENT_STATE_AGING_NOT_HISTORICAL_BALANCE','invoices'=>$rows,'totals'=>$money($totals),'buckets'=>$money($buckets),'limits'=>['external_collection'=>false,'automatic_block'=>false,'gateway_real'=>false]];
        return $body+['evidence_sha256'=>hash('sha256',json_encode($body,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES))];
    }
    private static function add(int &$sum,int $amount):void
    {
        if($amount<0 || $amount>GFExactMoney::MAX_CENTS-$sum)throw new OverflowException('FIN_READMODEL_TOTAL_OVERFLOW');
        $sum+=$amount;
    }
    public static function build(PDO $db,?int $partner,string $reference):array
    {
        $owned=false;$phase='GUARD';
        try {
            GFDirectBilling::guard($db);
            if($db->inTransaction())throw new LogicException('FIN_READMODEL_OWNS_TRANSACTION_REQUIRED');
            self::time($reference);
            $db->exec('START TRANSACTION READ ONLY, WITH CONSISTENT SNAPSHOT');$owned=true;$phase='SCAN';
            $report=self::scan($db,$partner,$reference);$db->commit();$owned=false;
            return ['ok'=>true,'report'=>$report];
        }catch(Throwable $e) {
            if($owned && $db->inTransaction())$db->rollBack();
            return ['ok'=>false,'project'=>'GuinchaFacil','class'=>self::class,'function'=>'build','component'=>'invoice_readmodel','phase'=>$phase,'erro'=>$e->getMessage(),'exception'=>$e::class,'file'=>basename($e->getFile()),'line'=>$e->getLine(),'sqlstate'=>$e instanceof PDOException?($e->errorInfo[0]??null):null];
        }
    }
    public static function html(array $r):string
    {
        $e=static fn(mixed $s):string=>htmlspecialchars((string)$s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
        $html='<!doctype html><html lang="pt-BR"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta http-equiv="Content-Security-Policy" content="default-src &#39;none&#39;; style-src &#39;unsafe-inline&#39;"><title>GuinchaFacil — Faturas QA</title><style>body{font:16px system-ui;margin:32px;color:#173042;background:#f4f7fa}main{max-width:1200px;margin:auto}table{border-collapse:collapse;background:white;width:100%}th,td{padding:12px;text-align:left;border-bottom:1px solid #ddd}caption{text-align:left;padding:16px 0}section{display:flex;gap:24px;flex-wrap:wrap;margin:24px 0}article{background:white;padding:16px;border-radius:8px}small{display:block}.scroll{overflow:auto}</style><main><h1>Faturas e vencimentos · QA</h1><p>Estado atual das faturas. A referencia calcula o atraso; nao reconstitui o saldo historico.</p><p>Referencia: '.$e($r['reference']).' · '.$e($r['timezone']).'</p><p>Sem cobranca externa ou bloqueio automatico.</p><section>';
        foreach(['invoiced'=>'Faturado','paid'=>'Pago','outstanding'=>'Em aberto','overdue'=>'Em atraso'] as $key=>$label)$html.='<article>'.$label.'<strong> R$ '.$e($r['totals'][$key]).'</strong></article>';
        $html.='</section><div class="scroll"><table><caption>Faturas conferidas · valores em BRL</caption><thead><tr><th scope="col">Fatura</th><th scope="col">Parceiro</th><th scope="col">Vencimento</th><th scope="col">Estado gravado</th><th scope="col">Faixa</th><th scope="col">Dias</th><th scope="col">Valor</th><th scope="col">Em aberto</th></tr></thead><tbody>';
        foreach($r['invoices'] as $row){$html.='<tr>';foreach(['invoice_id','partner_id','due_at','stored_status','bucket','overdue_days','amount','outstanding'] as $key)$html.='<td>'.$e($row[$key]).'</td>';$html.='</tr>';}
        if($r['invoices']===[])$html.='<tr><td colspan="8">Nenhuma fatura no escopo.</td></tr>';
        return $html.'</tbody></table></div><p>SHA-256: '.$e($r['evidence_sha256']).'</p></main></html>';
    }
}

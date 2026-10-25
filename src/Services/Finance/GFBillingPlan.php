<?php
declare(strict_types=1);
/** Projecao administrativa. Um plano nao autoriza mutacao nem envio. */
final class GFBillingPlan
{
    public static function classify(array $row,string $reference,bool $enabled):array
    {
        $status=$row['stored_status']??null;if(!in_array($status,['aberta','paga','vencida','bloqueada'],true))throw new DomainException('FIN_BILLING_STATUS_UNSUPPORTED');
        if(!is_int($row['invoice_id']??null) || $row['invoice_id']<1 || !is_int($row['partner_id']??null) || $row['partner_id']<1)throw new InvalidArgumentException('FIN_BILLING_ID_INVALID');
        $amount=GFMoneyInput::decimal($row['amount']??null);$outstanding=GFMoneyInput::decimal($row['outstanding']??null);$paid=$status==='paga';if($outstanding!==($paid?'0.00':$amount))throw new DomainException('FIN_BILLING_OUTSTANDING_DRIFT');
        $age=GFInvoiceReadModel::bucket((string)$row['due_at'],$reference,$paid);$positive=GFExactMoney::cents($outstanding)>0;$candidate=$status==='aberta' && $age['overdue'] && $positive;
        $review=!$paid && ((!$positive) || ($status==='vencida') || ($status==='bloqueada' && !$age['overdue']));
        return ['invoice_id'=>$row['invoice_id'],'partner_id'=>$row['partner_id'],'due_at'=>$row['due_at'],'stored_status'=>$status,'amount'=>$amount,'outstanding'=>$outstanding]+$age+[
            'block_candidate'=>$candidate,'proposed_status'=>$candidate?'bloqueada':null,'currently_blocked'=>$enabled && $status==='bloqueada','would_block_if_applied'=>$enabled && $candidate,'review_required'=>$review,'notification_proposed'=>false];
    }
    public static function project(array $rows,string $reference,string $flag):array
    {
        GFInvoiceReadModel::time($reference);if(!in_array($flag,['0','1'],true))throw new DomainException('FIN_BILLING_INVALID_BLOCK_CONFIG');$enabled=$flag==='1';$out=[];$partners=[];$totals=['outstanding'=>0,'overdue'=>0,'candidate'=>0];
        $add=static function(int &$sum,int $value):void{if($value<0 || $value>GFExactMoney::MAX_CENTS-$sum)throw new OverflowException('FIN_BILLING_TOTAL_OVERFLOW');$sum+=$value;};$seen=[];
        foreach($rows as $row){$v=self::classify($row,$reference,$enabled);if(isset($seen[$v['invoice_id']]))throw new DomainException('FIN_BILLING_DUPLICATE_INVOICE');$seen[$v['invoice_id']]=true;$n=GFExactMoney::cents($v['outstanding']);$add($totals['outstanding'],$n);if($v['overdue'])$add($totals['overdue'],$n);if($v['block_candidate'])$add($totals['candidate'],$n);
            $id=$v['partner_id'];$partners[$id]??=['partner_id'=>$id,'currently_blocked'=>false,'has_candidate'=>false,'outstanding_cents'=>0,'candidate_cents'=>0];$partners[$id]['currently_blocked']=$partners[$id]['currently_blocked'] || $v['currently_blocked'];$partners[$id]['has_candidate']=$partners[$id]['has_candidate'] || $v['block_candidate'];$add($partners[$id]['outstanding_cents'],$n);if($v['block_candidate'])$add($partners[$id]['candidate_cents'],$n);$out[]=$v;
        }
        ksort($partners,SORT_NUMERIC);foreach($partners as &$p){$p['would_be_blocked_if_applied']=$enabled && ($p['currently_blocked'] || $p['has_candidate']);$p['outstanding']=GFExactMoney::decimal($p['outstanding_cents']);$p['candidate_amount']=GFExactMoney::decimal($p['candidate_cents']);unset($p['outstanding_cents'],$p['candidate_cents']);}unset($p);
        return ['version'=>'R11AY','scope'=>'QA_ADMIN_BILLING_PLAN','reference'=>$reference,'timezone'=>'America/Sao_Paulo','reference_semantics'=>'CURRENT_STATE_AGING_NOT_HISTORICAL_BALANCE','invoices'=>$out,'partners'=>array_values($partners),'totals'=>array_map(static fn(int $n):string=>GFExactMoney::decimal($n),$totals),
            'policy'=>['blocking_enabled'=>$enabled,'configuration_key'=>'bloqueio_financeiro_ativo','candidate_rule'=>'aberta AND due_at<reference AND outstanding>0','notify_on_block'=>false,'applies_writes'=>false],
            'limits'=>['external_collection'=>false,'automatic_block'=>false,'status_mutation'=>false,'plan_is_authorization'=>false,'refunds_and_compensation'=>false,'partial_invoice_payment'=>'NOT_SUPPORTED_BY_CURRENT_VERIFIED_MODEL']];
    }
    public static function build(PDO $db,int $actor,?int $partner,string $reference):array
    {
        $owned=false;$phase='GUARD';try{
            GFDirectBilling::guard($db);if($db->inTransaction())throw new LogicException('FIN_BILLING_OWNS_TRANSACTION_REQUIRED');GFInvoiceReadModel::time($reference);$db->exec('START TRANSACTION READ ONLY, WITH CONSISTENT SNAPSHOT');$owned=true;$phase='AUTHORIZE';
            $q=$db->prepare('SELECT tipo,ativo FROM usuarios WHERE id=?');$q->execute([$actor]);$u=$q->fetch(PDO::FETCH_ASSOC);if(!$u || $u['tipo']!=='admin' || (int)$u['ativo']!==1)throw new DomainException('acesso_negado',403);
            $phase='READ_POLICY';$q=$db->query("SELECT valor FROM configuracoes WHERE chave='bloqueio_financeiro_ativo'");$values=$q->fetchAll(PDO::FETCH_COLUMN);if(count($values)>1)throw new DomainException('FIN_BILLING_DUPLICATE_CONFIG');$flag=$values[0]??'0';
            $phase='PROJECT';$source=GFInvoiceReadModel::scan($db,$partner,$reference);$report=self::project($source['invoices'],$reference,$flag);$report['partner_id']=$partner;$report['policy']['configuration_present']=count($values)===1;$report['source_evidence_sha256']=$source['evidence_sha256'];$report['evidence_sha256']=hash('sha256',json_encode($report,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES));$db->commit();$owned=false;return ['ok'=>true,'http'=>200,'report'=>$report];
        }catch(Throwable $e){if($owned && $db->inTransaction())$db->rollBack();$d=['project'=>'GuinchaFacil','class'=>self::class,'function'=>'build','component'=>'billing_plan','phase'=>$phase,'error'=>$e->getMessage(),'exception'=>$e::class,'file'=>basename($e->getFile()),'line'=>$e->getLine(),'sqlstate'=>$e instanceof PDOException?($e->errorInfo[0]??null):null,'driver_code'=>$e instanceof PDOException?($e->errorInfo[1]??null):null,'status'=>$e->getCode()===403?'REJECTED':'FAIL','remaining_percent'=>40];error_log(json_encode($d,JSON_THROW_ON_ERROR));return ['ok'=>false,'http'=>$e->getCode()===403?403:500,'erro'=>$e->getCode()===403?'acesso_negado':'planejamento_indisponivel','diagnostic'=>$d];}
    }
    public static function html(array $r):string
    {
        $e=static fn(mixed $v):string=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');$h='<!doctype html><html lang="pt-BR"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Planejamento de cobranca QA</title><style>body{font:16px system-ui;margin:24px;color:#192d3f;background:#f3f6fa}main{max-width:1200px;margin:auto}table{border-collapse:collapse;background:white;width:100%}th,td{padding:10px;text-align:left;border-bottom:1px solid #ddd}.scroll{overflow:auto}p{overflow-wrap:anywhere}</style><main><a href="/admin/faturas">Voltar as faturas</a><h1>Planejamento de cobranca e inadimplencia</h1><p>Avaliacao administrativa em QA. Nenhum bloqueio, envio ou baixa e executado por esta consulta.</p><p>Referencia: '.$e($r['reference']).' · America/Sao_Paulo. Estado atual; nao e saldo historico.</p><p>Regra operacional configurada: '.($r['policy']['blocking_enabled']?'ativa':'inativa').'. Notificacao de bloqueio nao proposta.</p><p>Em aberto: R$ '.$e($r['totals']['outstanding']).' · Em atraso: R$ '.$e($r['totals']['overdue']).' · Candidatas: R$ '.$e($r['totals']['candidate']).'</p><div class="scroll"><table><thead><tr><th>Fatura</th><th>Parceiro</th><th>Vencimento</th><th>Estado gravado</th><th>Em aberto</th><th>Dias</th><th>Candidata</th><th>Bloqueio atual</th><th>Revisao</th></tr></thead><tbody>';
        foreach($r['invoices'] as $v){$h.='<tr>';foreach(['invoice_id','partner_id','due_at','stored_status','outstanding','overdue_days','block_candidate','currently_blocked','review_required'] as $k)$h.='<td>'.$e(is_bool($v[$k])?($v[$k]?'sim':'nao'):$v[$k]).'</td>';$h.='</tr>';}
        return $h.'</tbody></table></div><p>Uma candidata nao e uma autorizacao de bloqueio. Estados divergentes exigem revisao e nao geram desbloqueio automatico.</p><p>SHA-256 '.$e($r['evidence_sha256']).'</p></main></html>';
    }
}

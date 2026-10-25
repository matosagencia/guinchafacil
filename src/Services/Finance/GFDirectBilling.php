<?php
declare(strict_types=1);
require_once __DIR__.'/GFIntegratedPayout.php';
final class GFQaDirectReceipt
{
    private function __construct(public int $order,public int $partner,public string $gross,public string $reference,public string $receivedAt) {}
    public static function confirmed(int $order,int $partner,mixed $gross,string $reference,string $receivedAt):self
    {
        GFDirectBilling::guard(getPDO());$gross=GFMoneyInput::decimal($gross);
        $date=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$receivedAt,new DateTimeZone('America/Sao_Paulo'));
        if($order<1 || $partner<1 || $gross==='0.00' || !preg_match('/^qa_cash_[A-Za-z0-9_-]{1,80}$/D',$reference) || !$date || $date->format('Y-m-d H:i:s')!==$receivedAt)throw new InvalidArgumentException('FIN_DIRECT_RECEIPT_INVALID');
        return new self($order,$partner,$gross,$reference,$receivedAt);
    }
    public function data():array {return [$this->order,$this->partner,$this->gross,$this->reference,$this->receivedAt];}
}
final class GFQaInvoiceReceipt
{
    private function __construct(public int $invoice,public string $amount,public string $external,public string $collector) {}
    /** Resposta de GET SIMULADA; nao aceita payload bruto de webhook como prova. */
    public static function fromQaMercadoPagoHttp(array $http,string $requested,string $collector):self
    {
        GFDirectBilling::guard(getPDO());
        if(($http['code']??0)!==200 || ($http['error']??'')!=='' || !preg_match('/^[0-9]{1,32}$/D',$requested) || !preg_match('/^[0-9]+$/D',$collector))throw new DomainException('FIN_INVOICE_LOOKUP_FAILED');
        $p=GFExactJson::decode($http['body']??'');$reference=$p['external_reference']??'';
        if(($p['id']??null)!==$requested || ($p['status']??null)!=='approved' || ($p['status_detail']??null)!=='accredited' || GFMoneyInput::decimal($p['transaction_amount_refunded']??null)!=='0.00' || ($p['payment_method_id']??null)!=='pix' || ($p['currency_id']??null)!=='BRL' || ($p['collector_id']??null)!==$collector || !preg_match('/^fatura:([1-9][0-9]{0,9})$/D',$reference,$m))throw new DomainException('FIN_INVOICE_RECEIPT_MISMATCH');
        $amount=GFMoneyInput::decimal($p['transaction_amount']??null);if($amount==='0.00')throw new DomainException('FIN_INVOICE_ZERO_RECEIPT');
        return new self((int)$m[1],$amount,'qa_invoice_mp_'.$requested,$collector);
    }
    public function data():array {return [$this->invoice,$this->amount,$this->external,$this->collector];}
}
final class GFDirectBilling
{
    public static function guard(PDO $db):void
    {
        GFIntegratedPayout::guard($db);
        if(!defined('GF_FIN_DIRECT_QA') || GF_FIN_DIRECT_QA!==true || function_exists('curl_exec') || filter_var(ini_get('allow_url_fopen'),FILTER_VALIDATE_BOOLEAN))throw new LogicException('FIN_DIRECT_QA_REQUIRED');
    }
    private static function atomic(PDO $db,string $function,callable $fn):array
    {
        $owned=false;$save=null;
        try {
            self::guard($db);
            if(!$db->inTransaction()){$db->beginTransaction();$owned=true;}else{$save='ao_'.bin2hex(random_bytes(8));$db->exec('SAVEPOINT '.$save);}
            $r=$fn();if($owned)$db->commit();else$db->exec('RELEASE SAVEPOINT '.$save);
            self::log($function,'COMMIT_OR_RELEASE','PASS',['transaction_owner'=>$owned]+$r);return ['ok'=>true]+$r;
        } catch(Throwable $e) {
            if($db->inTransaction()) {if($owned)$db->rollBack();elseif($save!==null){$db->exec('ROLLBACK TO SAVEPOINT '.$save);$db->exec('RELEASE SAVEPOINT '.$save);}}
            self::log($function,'REJECT_OR_ROLLBACK','FAIL',['error'=>$e->getMessage(),'exception'=>$e::class,'file'=>basename($e->getFile()),'line'=>$e->getLine(),'sqlstate'=>$e instanceof PDOException?($e->errorInfo[0]??null):null]);
            return ['ok'=>false,'erro'=>$e->getMessage()];
        }
    }
    public static function log(string $function,string $phase,string $status,array $context):void
    {
        $r=['project'=>'GuinchaFacil','class'=>self::class,'function'=>$function,'component'=>'direct_invoice','phase'=>$phase,'status'=>$status,'message'=>$context['error']??$phase,'context'=>$context];
        if(isset($GLOBALS['gf_fin_log_sink']))($GLOBALS['gf_fin_log_sink'])($r);else error_log(json_encode($r,JSON_THROW_ON_ERROR));
    }
    private static function partner(PDO $db,int $partner):void
    {
        $q=$db->prepare('SELECT aprovado FROM guinchos WHERE id=? FOR UPDATE');$q->execute([$partner]);if((int)$q->fetchColumn()!==1)throw new DomainException('FIN_DIRECT_PARTNER_NOT_APPROVED');
    }
    private static function order(PDO $db,int $order,bool $lock=true):array
    {
        $q=$db->prepare('SELECT * FROM pedidos WHERE id=?'.($lock?' FOR UPDATE':''));$q->execute([$order]);$o=$q->fetch(PDO::FETCH_ASSOC);
        $q=$db->prepare('SELECT * FROM pagamentos WHERE pedido_id=?'.($lock?' FOR UPDATE':''));$q->execute([$order]);$rows=$q->fetchAll(PDO::FETCH_ASSOC);
        if(!$o || count($rows)!==1)throw new DomainException('FIN_DIRECT_ORDER_PAYMENT_AMBIGUOUS');return [$o,$rows[0]];
    }
    public static function directEvidence(PDO $db,array $o,array $p,bool $lock=false):array
    {
        self::guard($db);$snap=json_decode($p['payload_webhook']??'{}',true,512,JSON_THROW_ON_ERROR);$r=$snap['receipt']??[];
        if(($snap['version']??null)!=='R11AO' || count($r)!==5 || $r[0]!=(int)$o['id'] || $r[1]!=(int)$o['guincho_id'] || $o['status']!=='concluido' || $o['attendance_mode']!=='TOWING' || $o['forma_pagamento_escolhida']!=='pago_na_chegada' || $o['comissao_tipo']!=='faturada' || $p['metodo']!=='dinheiro' || $p['status']!=='aprovado' || $p['id_externo']!==$r[3] || $p['valor_total']!==$r[2] || $p['valor_guincho']!==$r[2] || $o['valor_liquido_parceiro']!==$r[2] || $o['custo_final']!==$r[2] || $o['valor_final']!==$r[2] || (int)$p['pago_guincho']!==1)throw new DomainException('FIN_DIRECT_FROZEN_RECEIPT_DRIFT');
        $commission=GFExactMoney::decimal(GFExactMoney::percent(GFExactMoney::cents($r[2]),$snap['rate_bp']??-1));
        $fingerprint=hash('sha256',json_encode([$r,$commission,$snap['rate_bp']],JSON_THROW_ON_ERROR));
        if($commission==='0.00' || $commission!==$o['comissao_valor'] || $commission!==$p['valor_plataforma'] || ($snap['fingerprint']??null)!==$fingerprint)throw new DomainException('FIN_DIRECT_COMMISSION_DRIFT');
        $q=$db->prepare('SELECT * FROM fin_bilateral_eventos WHERE idempotency_key=?'.($lock?' FOR UPDATE':''));$q->execute(['r11ao_direct:'.$o['id']]);$event=$q->fetch(PDO::FETCH_ASSOC);
        if(!$event || $event['parceiro_tipo']!=='guincho' || (int)$event['parceiro_id']!==(int)$o['guincho_id'] || (int)$event['pedido_id']!==(int)$o['id'] || (int)$event['pagamento_id']!==(int)$p['id'] || $event['tipo_evento']!=='debito_comissao' || $event['canal']!=='parceiro' || (int)$event['valor_centavos']!==GFExactMoney::cents($commission) || $event['referencia_tipo']!=='r11ao_direct' || $event['referencia_id']!==$r[3] || $event['estado']!=='confirmado' || $event['competencia_em']!==$r[4])throw new DomainException('FIN_DIRECT_PHYSICAL_EVENT_DRIFT');
        $q=$db->prepare('SELECT COUNT(*) FROM payout_ledger_entries WHERE pagamento_id=?');$q->execute([$p['id']]);if((int)$q->fetchColumn()!==0)throw new DomainException('FIN_DIRECT_UNEXPECTED_PAYOUT_LEDGER');
        return $event;
    }
    public static function register(PDO $db,GFQaDirectReceipt $receipt):array
    {
        return self::atomic($db,__FUNCTION__,static function() use($db,$receipt):array {
            self::partner($db,$receipt->partner);[$o,$p]=self::order($db,$receipt->order);
            if((int)$o['guincho_id']!==$receipt->partner)throw new DomainException('FIN_DIRECT_RECEIPT_PARTNER_MISMATCH');
            if($p['status']==='aprovado') {
                $e=self::directEvidence($db,$o,$p,true);$snap=json_decode($p['payload_webhook'],true,512,JSON_THROW_ON_ERROR);
                if($snap['receipt']!==$receipt->data())throw new DomainException('FIN_DIRECT_IDEMPOTENCY_DRIFT');return ['duplicate_verified'=>true,'event_id'=>(int)$e['id']];
            }
            if($o['status']!=='concluido' || $o['attendance_mode']!=='TOWING' || $o['forma_pagamento_escolhida']!=='pago_na_chegada' || $p['metodo']!=='dinheiro' || $p['status']!=='pendente' || $o['fatura_id']!==null || $o['comissao_valor']!==null || $p['id_externo']!==null || $p['valor_total']!==$receipt->gross || ($o['custo_final']??$o['custo_estimado'])!==$receipt->gross)throw new DomainException('FIN_DIRECT_NOT_ELIGIBLE');
            $q=$db->prepare("SELECT id FROM fin_bilateral_fechamentos WHERE parceiro_tipo='guincho' AND parceiro_id=? AND ciclo_inicio<=? AND ciclo_fim>=? FOR UPDATE");$q->execute([$receipt->partner,$receipt->receivedAt,$receipt->receivedAt]);if($q->fetchColumn()!==false)throw new DomainException('FIN_DIRECT_CYCLE_ALREADY_CLOSED');
            $q=$db->prepare('SELECT id FROM pagamentos WHERE id_externo=? FOR UPDATE');$q->execute([$receipt->reference]);if($q->fetchColumn()!==false)throw new DomainException('FIN_DIRECT_REFERENCE_REUSED');
            $base=GFMoneyInput::basisPoints((string)$db->query("SELECT valor FROM configuracoes WHERE chave='comissao_plataforma'")->fetchColumn(),true);if($base!==800)throw new DomainException('FIN_DIRECT_BASE_RATE_DRIFT');$delta=0;
            if(!empty($o['cidade_id'])) {$q=$db->prepare('SELECT percentual FROM comissoes_cidade WHERE cidade_id=? AND ativo=1');$q->execute([$o['cidade_id']]);$values=$q->fetchAll(PDO::FETCH_COLUMN);if(count($values)>1)throw new DomainException('FIN_DIRECT_CITY_AMBIGUOUS');if($values)$delta=GFMoneyInput::basisPoints($values[0]);}
            $split=GFExactMoney::split($receipt->gross,'0.00',$base,$delta);if($split['platform']==='0.00')throw new DomainException('FIN_DIRECT_ZERO_COMMISSION_NOT_MIGRATED');
            $snap=['version'=>'R11AO','receipt'=>$receipt->data(),'rate_bp'=>$split['rate_bp'],'fingerprint'=>hash('sha256',json_encode([$receipt->data(),$split['platform'],$split['rate_bp']],JSON_THROW_ON_ERROR))];
            $db->prepare("UPDATE pedidos SET custo_final=?,valor_final=?,comissao_tipo='faturada',comissao_valor=?,valor_liquido_parceiro=? WHERE id=?")->execute([$receipt->gross,$receipt->gross,$split['platform'],$receipt->gross,$o['id']]);
            $db->prepare("UPDATE pagamentos SET status='aprovado',id_externo=?,status_gateway='qa_cash_confirmed',valor_guincho=?,valor_plataforma=?,pago_guincho=1,status_carteira='pago',data_pagamento=?,data_pagamento_guincho=?,payload_webhook=? WHERE id=?")->execute([$receipt->reference,$receipt->gross,$split['platform'],$receipt->receivedAt,$receipt->receivedAt,json_encode($snap,JSON_THROW_ON_ERROR),$p['id']]);
            $db->prepare("INSERT INTO fin_bilateral_eventos(idempotency_key,parceiro_tipo,parceiro_id,pedido_id,pagamento_id,tipo_evento,canal,valor_centavos,referencia_tipo,referencia_id,estado,competencia_em,confirmado_em) VALUES (?,'guincho',?,?,?,'debito_comissao','parceiro',?,'r11ao_direct',?,'confirmado',?,NOW())")->execute(['r11ao_direct:'.$o['id'],$receipt->partner,$o['id'],$p['id'],GFExactMoney::cents($split['platform']),$receipt->reference,$receipt->receivedAt]);
            return ['duplicate_verified'=>false,'event_id'=>(int)$db->lastInsertId(),'commission'=>$split['platform']];
        });
    }
    private static function signed(int $cents):string {return ($cents<0?'-':'').GFExactMoney::decimal(abs($cents));}
    private static function digest(array $events):string
    {
        usort($events,static fn($a,$b)=>(int)$a['id']<=>(int)$b['id']);$snapshot=[];
        foreach($events as $e)$snapshot[]=[(string)$e['id'],(string)$e['pedido_id'],(string)$e['valor_centavos'],$e['idempotency_key'],$e['referencia_id'],$e['competencia_em']];
        return hash('sha256',json_encode($snapshot,JSON_THROW_ON_ERROR));
    }
    public static function verifyInvoice(PDO $db,int $invoice,bool $lock=true):array
    {
        self::guard($db);$q=$db->prepare('SELECT * FROM faturas_parceiro WHERE id=?'.($lock?' FOR UPDATE':''));$q->execute([$invoice]);$f=$q->fetch(PDO::FETCH_ASSOC);
        if(!$f || $f['parceiro_tipo']!=='guincho' || !in_array($f['status'],['aberta','bloqueada','vencida','paga'],true))throw new DomainException('FIN_INVOICE_NOT_ELIGIBLE');
        $q=$db->prepare('SELECT * FROM faturas_itens WHERE fatura_id=? ORDER BY pedido_id'.($lock?' FOR UPDATE':''));$q->execute([$invoice]);$items=$q->fetchAll(PDO::FETCH_ASSOC);if(!$items)throw new DomainException('FIN_INVOICE_ITEMS_MISSING');$events=[];$sum=0;
        foreach($items as $i) {
            [$o,$p]=self::order($db,(int)$i['pedido_id'],$lock);$e=self::directEvidence($db,$o,$p,$lock);
            if((int)$o['fatura_id']!==$invoice || (int)$o['guincho_id']!==(int)$f['parceiro_id'] || $i['comissao_tipo']!=='faturada' || $i['valor_total']!==$p['valor_total'] || $i['valor_liquido_parceiro']!==$p['valor_total'] || $i['comissao_valor']!==$o['comissao_valor'] || $i['saldo_item']!==self::signed(-GFExactMoney::cents($o['comissao_valor'])) || $e['competencia_em']<$f['ciclo_inicio'] || $e['competencia_em']>$f['ciclo_fim'])throw new DomainException('FIN_INVOICE_ITEM_DRIFT');
            $sum+=GFExactMoney::cents($i['comissao_valor']);if($sum>GFExactMoney::MAX_CENTS)throw new OverflowException('FIN_INVOICE_TOTAL_OVERFLOW');$events[]=$e;
        }
        $q=$db->prepare("SELECT * FROM fin_bilateral_fechamentos WHERE parceiro_tipo='guincho' AND parceiro_id=? AND ciclo_inicio=? AND ciclo_fim=?".($lock?' FOR UPDATE':''));$q->execute([$f['parceiro_id'],$f['ciclo_inicio'],$f['ciclo_fim']]);$closed=$q->fetch(PDO::FETCH_ASSOC);
        if(!$closed || !in_array($closed['status'],['validado','liquidado'],true) || (int)$closed['credito_centavos']!==0 || (int)$closed['debito_centavos']!==$sum || (int)$closed['saldo_centavos']!==-$sum || $closed['snapshot_sha256']!==self::digest($events) || $f['total_repasse']!=='0.00' || $f['total_faturado']!==GFExactMoney::decimal($sum) || $f['saldo']!==self::signed(-$sum))throw new DomainException('FIN_INVOICE_SNAPSHOT_DRIFT');
        $q=$db->prepare('SELECT evento_id FROM fin_bilateral_fechamento_itens WHERE fechamento_id=? ORDER BY evento_id'.($lock?' FOR UPDATE':''));$q->execute([$closed['id']]);$ids=array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN));$expected=array_map(static fn($e)=>(int)$e['id'],$events);sort($expected,SORT_NUMERIC);
        if($ids!==$expected || ($f['status']==='paga')!==($closed['status']==='liquidado'))throw new DomainException('FIN_INVOICE_CLOSING_LINK_DRIFT');return ['invoice'=>$f,'closed'=>$closed,'amount'=>GFExactMoney::decimal($sum)];
    }
    public static function close(PDO $db,int $partner,DateTimeImmutable $start):array
    {
        return self::atomic($db,__FUNCTION__,static function() use($db,$partner,$start):array {
            if($start->getTimezone()->getName()!=='America/Sao_Paulo' || $start->format('H:i:s')!=='00:00:00')throw new InvalidArgumentException('FIN_INVOICE_CYCLE_TIMEZONE_REQUIRED');
            self::partner($db,$partner);$a=$start->format('Y-m-d H:i:s');$end=$start->modify('+7 days');$b=$end->modify('-1 second')->format('Y-m-d H:i:s');
            $q=$db->prepare("SELECT id,ciclo_inicio,ciclo_fim FROM faturas_parceiro WHERE parceiro_tipo='guincho' AND parceiro_id=? AND ciclo_inicio<=? AND ciclo_fim>=? FOR UPDATE");$q->execute([$partner,$b,$a]);$old=$q->fetchAll(PDO::FETCH_ASSOC);
            if($old) {if(count($old)!==1 || $old[0]['ciclo_inicio']!==$a || $old[0]['ciclo_fim']!==$b)throw new DomainException('FIN_INVOICE_CYCLE_OVERLAP');self::verifyInvoice($db,(int)$old[0]['id']);return ['created'=>false,'invoice_id'=>(int)$old[0]['id']];}
            $q=$db->prepare("SELECT e.* FROM fin_bilateral_eventos e LEFT JOIN fin_bilateral_fechamento_itens i ON i.evento_id=e.id WHERE e.parceiro_tipo='guincho' AND e.parceiro_id=? AND e.referencia_tipo='r11ao_direct' AND e.estado='confirmado' AND e.competencia_em>=? AND e.competencia_em<=? AND i.evento_id IS NULL ORDER BY e.id FOR UPDATE");$q->execute([$partner,$a,$b]);$events=$q->fetchAll(PDO::FETCH_ASSOC);if(!$events)return ['created'=>false,'invoice_id'=>null];$sum=0;$orders=[];
            foreach($events as $e) {[$o,$p]=self::order($db,(int)$e['pedido_id']);self::directEvidence($db,$o,$p,true);if($o['fatura_id']!==null)throw new DomainException('FIN_INVOICE_ORDER_ALREADY_LINKED');$sum+=(int)$e['valor_centavos'];if($sum>GFExactMoney::MAX_CENTS)throw new OverflowException('FIN_INVOICE_TOTAL_OVERFLOW');$orders[]=[$o,$p];}
            $db->prepare("INSERT INTO fin_bilateral_fechamentos(parceiro_tipo,parceiro_id,ciclo_inicio,ciclo_fim,credito_centavos,debito_centavos,saldo_centavos,status,snapshot_sha256) VALUES ('guincho',?,?,?,0,?,?,'validado',?)")->execute([$partner,$a,$b,$sum,-$sum,self::digest($events)]);$closed=(int)$db->lastInsertId();
            $db->prepare("INSERT INTO faturas_parceiro(parceiro_tipo,parceiro_id,ciclo_inicio,ciclo_fim,total_faturado,total_repasse,saldo,vencimento_em) VALUES ('guincho',?,?,?,?,'0.00',?,?)")->execute([$partner,$a,$b,GFExactMoney::decimal($sum),self::signed(-$sum),$end->modify('+7 days')->format('Y-m-d H:i:s')]);$invoice=(int)$db->lastInsertId();
            foreach($events as $index=>$e) {
                [$o,$p]=$orders[$index];$commission=$o['comissao_valor'];
                $db->prepare("INSERT INTO faturas_itens(fatura_id,pedido_id,comissao_tipo,valor_total,comissao_valor,valor_liquido_parceiro,saldo_item) VALUES (?,?,'faturada',?,?,?,?)")->execute([$invoice,$o['id'],$p['valor_total'],$commission,$p['valor_total'],self::signed(-GFExactMoney::cents($commission))]);
                $db->prepare('INSERT INTO fin_bilateral_fechamento_itens(fechamento_id,evento_id) VALUES (?,?)')->execute([$closed,$e['id']]);
                $q=$db->prepare('UPDATE pedidos SET fatura_id=? WHERE id=? AND fatura_id IS NULL');$q->execute([$invoice,$o['id']]);if($q->rowCount()!==1)throw new DomainException('FIN_INVOICE_LINK_LOST');
            }
            self::verifyInvoice($db,$invoice);return ['created'=>true,'invoice_id'=>$invoice,'amount'=>GFExactMoney::decimal($sum)];
        });
    }
    public static function pay(PDO $db,int $invoice,?GFQaInvoiceReceipt $receipt):array
    {
        return self::atomic($db,__FUNCTION__,static function() use($db,$invoice,$receipt):array {
            if(!$receipt || $receipt->invoice!==$invoice)throw new DomainException('FIN_INVOICE_VERIFIED_RECEIPT_REQUIRED');
            $q=$db->prepare('SELECT parceiro_id FROM faturas_parceiro WHERE id=?');$q->execute([$invoice]);$partner=$q->fetchColumn();if($partner===false)throw new DomainException('FIN_INVOICE_MISSING');self::partner($db,(int)$partner);$v=self::verifyInvoice($db,$invoice);
            if($receipt->amount!==$v['amount'])throw new DomainException('FIN_INVOICE_AMOUNT_MISMATCH');$proof=json_encode($receipt->data(),JSON_THROW_ON_ERROR);
            $q=$db->prepare('SELECT * FROM faturas_pagamentos WHERE transacao_id=? OR fatura_id=? FOR UPDATE');$q->execute([$receipt->external,$invoice]);$old=$q->fetchAll(PDO::FETCH_ASSOC);
            if($old) {if(count($old)!==1 || (int)$old[0]['fatura_id']!==$invoice || $old[0]['transacao_id']!==$receipt->external || $old[0]['valor']!==$receipt->amount || $old[0]['payload']!==$proof || $v['invoice']['status']!=='paga' || $v['invoice']['pix_transacao_id']!==$receipt->external)throw new DomainException('FIN_INVOICE_PAYMENT_IDEMPOTENCY_DRIFT');return ['duplicate_verified'=>true,'invoice_id'=>$invoice];}
            if($v['invoice']['status']==='paga')throw new DomainException('FIN_INVOICE_PAID_WITHOUT_PROOF');
            $db->prepare("INSERT INTO faturas_pagamentos(fatura_id,transacao_id,metodo,valor,payload) VALUES (?,?,'qa_mercadopago_pix',?,?)")->execute([$invoice,$receipt->external,$receipt->amount,$proof]);
            $db->prepare("UPDATE faturas_parceiro SET status='paga',paga_em=NOW(),pix_transacao_id=? WHERE id=?")->execute([$receipt->external,$invoice]);
            $db->prepare("UPDATE fin_bilateral_fechamentos SET status='liquidado' WHERE id=?")->execute([$v['closed']['id']]);return ['duplicate_verified'=>false,'invoice_id'=>$invoice];
        });
    }
}

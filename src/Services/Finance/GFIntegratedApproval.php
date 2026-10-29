<?php
declare(strict_types=1);
require_once __DIR__.'/GFPaymentReceipt.php';
require_once __DIR__.'/../../Models/Pagamento.php';
require_once __DIR__.'/../../Models/Finance/PayoutLedgerEntry.php';
require_once __DIR__.'/../../DTO/PedidoTransitionResult.php';
require_once __DIR__.'/../PedidoEventoService.php';

/** Cobranca inicial TOWING, MP/BRL, em banco de desenvolvimento ou QA isolado. */
final class GFIntegratedApproval
{
    public const DATABASE='guinchafacil_fin3_r11ai_qa';

    public static function guard(PDO $db): void
    {
        $database=(string)$db->query('SELECT DATABASE()')->fetchColumn();
        if (getPDO()!==$db || $db->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql'
            || !in_array($database,[self::DATABASE,'guinchafacil_dev','guinchafacil_phpunit_qa'],true)) throw new LogicException('FIN_FINANCE_DATABASE_NOT_ALLOWED');
    }

    private static function log(string $phase,string $status,string $message,array $context=[]): void
    {
        $row=['project'=>'GuinchaFacil','class'=>self::class,'function'=>'approve','component'=>'financial_approval','phase'=>$phase,'status'=>$status,'message'=>$message,'context'=>$context];
        if (isset($GLOBALS['gf_fin_log_sink']) && is_callable($GLOBALS['gf_fin_log_sink'])) ($GLOBALS['gf_fin_log_sink'])($row);
        else error_log(json_encode($row,JSON_THROW_ON_ERROR));
    }

    public static function approve(PDO $db,int $orderId,string $external,?GFPaymentReceipt $receipt,int $actor=0): PedidoTransitionResult
    {
        $phase='GUARD'; $owned=false; $savepoint=null;
        try {
            self::guard($db);
            if (!$receipt || $receipt->orderId!==$orderId || $receipt->externalId!==$external) throw new DomainException('FIN_VERIFIED_RECEIPT_REQUIRED');
            if (!$db->inTransaction()) { $db->beginTransaction(); $owned=true; }
            else { $savepoint='fin_'.bin2hex(random_bytes(8)); $db->exec('SAVEPOINT '.$savepoint); }
            $phase='LOCK_ORDER_PAYMENT';
            $q=$db->prepare('SELECT * FROM pedidos WHERE id=? FOR UPDATE'); $q->execute([$orderId]); $order=$q->fetch(PDO::FETCH_ASSOC);
            $q=$db->prepare('SELECT * FROM pagamentos WHERE pedido_id=? FOR UPDATE'); $q->execute([$orderId]); $payment=$q->fetch(PDO::FETCH_ASSOC);
            if (!$order || !$payment) throw new DomainException('FIN_ORDER_OR_PAYMENT_MISSING');
            if ($payment['metodo']!==$receipt->provider) throw new DomainException('FIN_PAYMENT_PROVIDER_MISMATCH');
            if (($payment['id_externo']??'')!=='' && $payment['id_externo']!==$external) throw new DomainException('FIN_EXTERNAL_PAYMENT_ID_DRIFT');
            if (GFMoneyInput::decimal($payment['valor_total'])!==$receipt->gross) throw new DomainException('FIN_PAYMENT_GROSS_MISMATCH');
            $key=hash('sha256','approval-v1|'.$receipt->provider.'|'.$external);
            $fingerprint=$receipt->fingerprint();
            $q=$db->prepare('SELECT * FROM fin3_operational_approval WHERE event_key=? FOR UPDATE');$q->execute([$key]);$existing=$q->fetch(PDO::FETCH_ASSOC);
            if ($existing) {
                $phase='VERIFY_DUPLICATE';
                if (!hash_equals($existing['fingerprint'],$fingerprint) || (int)$existing['pedido_id']!==$orderId || (int)$existing['pagamento_id']!==(int)$payment['id']) throw new DomainException('FIN_IDEMPOTENCY_DRIFT');
                $split=json_decode($existing['split_json'],true,512,JSON_THROW_ON_ERROR);
                self::verifyPosted($db,$order,$payment,$receipt,$key,$fingerprint,$split);
                if ($owned) $db->commit(); else $db->exec('RELEASE SAVEPOINT '.$savepoint);
                self::log($phase,'PASS','Mesma identidade e lancamentos fisicos conferidos.',['pedido_id'=>$orderId,'event_key'=>$key]);
                return PedidoTransitionResult::success($order,['duplicate_verified'=>true,'event_key'=>$key,'split'=>$split]);
            }
            $phase='VALIDATE_FIRST_APPROVAL';
            if ($order['attendance_mode']!=='TOWING' || !empty($order['incidente_id']) || !empty($order['fatura_id'])) throw new DomainException('FIN_FLOW_NOT_MIGRATED');
            if ($order['status']!=='aguardando_pagamento' || !in_array($payment['status'],['pendente','recusado'],true)) throw new DomainException('FIN_PAYMENT_STATE_NOT_PENDING');
            $total=GFMoneyInput::decimal($order['custo_final']??$order['custo_estimado']);
            if ($total!==$receipt->gross) throw new DomainException('FIN_ORDER_GROSS_MISMATCH');
            $cfg=$db->query("SELECT chave,valor FROM configuracoes WHERE chave IN ('comissao_plataforma','tempo_expiracao_min','raio_inicial_km')")->fetchAll(PDO::FETCH_KEY_PAIR);
            $base=GFMoneyInput::basisPoints($cfg['comissao_plataforma']??'0.08',true);
            if ($base!==800) throw new DomainException('FIN_BASE_COMMISSION_MUST_BE_EIGHT_PERCENT');
            $delta=0;
            if (!empty($order['cidade_id'])) {
                $q=$db->prepare('SELECT percentual FROM comissoes_cidade WHERE cidade_id=? AND ativo=1');$q->execute([$order['cidade_id']]);$raw=$q->fetchColumn();
                if ($raw!==false) $delta=GFMoneyInput::basisPoints($raw);
            }
            $split=GFExactMoney::split($total,$receipt->fee,$base,$delta);
            // Congele os campos ANTES de criar a identidade que ativa o trigger.
            $db->prepare("UPDATE pedidos SET custo_final=?,valor_final=?,comissao_valor=?,comissao_tipo='descontada',valor_liquido_parceiro=? WHERE id=?")->execute([$total,$total,$split['platform'],$split['partner'],$orderId]);
            $phase='INBOX_AND_JOURNAL';
            $token=bin2hex(random_bytes(16));
            $db->prepare("INSERT INTO fin3_operational_inbox(event_key,fingerprint,status,claim_token,lease_until) VALUES (?,?,'processing',?,DATE_ADD(NOW(6),INTERVAL 90 SECOND))")->execute([$key,$fingerprint,$token]);
            $db->prepare('INSERT INTO fin3_operational_approval(event_key,provider,external_id,pedido_id,pagamento_id,fingerprint,split_json) VALUES (?,?,?,?,?,?,?)')->execute([$key,$receipt->provider,$external,$orderId,(int)$payment['id'],$fingerprint,json_encode($split,JSON_THROW_ON_ERROR)]);
            foreach (self::amounts($split) as $type=>$amount) PayoutLedgerEntry::registrar($db,[
                'pagamento_id'=>(int)$payment['id'],'pedido_id'=>$orderId,'entry_type'=>$type,'valor'=>$amount,
                'referencia_externa'=>$external,'metadata'=>['fin3_event_key'=>$key,'fingerprint'=>$fingerprint],
            ]);
            $phase='PAYMENT_AND_ORDER';
            Pagamento::aprovarFinanceiroExato($db,(int)$payment['id'],$external);
            Pagamento::atualizarSplitFinanceiroExato($db,(int)$payment['id'],$split['partner'],$split['platform']);
            $db->prepare("UPDATE pedidos SET status='aguardando_guincho',expiracao_aceite=DATE_ADD(NOW(),INTERVAL ? MINUTE),raio_atual_km=? WHERE id=?")->execute([(int)($cfg['tempo_expiracao_min']??5),(int)($cfg['raio_inicial_km']??10),$orderId]);
            $phase='ORDER_EVENT_AND_OUTBOX';
            PedidoEventoService::registrarFinanceiro($db,$orderId,'pagamento_aprovado','aguardando_pagamento','aguardando_guincho','system',$actor,null,['pagamento_id'=>(int)$payment['id'],'fin3_event_key'=>$key]);
            $db->prepare("INSERT INTO fin3_post_commit_outbox(event_key,event_type,payload_json,status) VALUES (?,'payment_approved',?,'pending')")->execute([$key,json_encode(['pedido_id'=>$orderId,'pagamento_id'=>(int)$payment['id']],JSON_THROW_ON_ERROR)]);
            $q=$db->prepare("UPDATE fin3_operational_inbox SET status='done',claim_token=NULL,lease_until=NULL WHERE event_key=? AND claim_token=? AND status='processing'");$q->execute([$key,$token]);
            if ($q->rowCount()!==1) throw new RuntimeException('FIN_INBOX_TOKEN_LOST');
            $q=$db->prepare('SELECT * FROM pedidos WHERE id=?');$q->execute([$orderId]);$order=$q->fetch(PDO::FETCH_ASSOC);
            if ($owned) $db->commit(); else $db->exec('RELEASE SAVEPOINT '.$savepoint);
            self::log('COMMIT_OR_RELEASE','PASS','Aprovacao, inbox, ledger, pedido, evento e outbox atomicos.',['pedido_id'=>$orderId,'event_key'=>$key,'transaction_owner'=>$owned]);
            return PedidoTransitionResult::success($order,['duplicate_verified'=>false,'event_key'=>$key,'split'=>$split]);
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                if ($owned) $db->rollBack();
                elseif ($savepoint!==null) { $db->exec('ROLLBACK TO SAVEPOINT '.$savepoint);$db->exec('RELEASE SAVEPOINT '.$savepoint); }
            }
            self::log($phase,'FAIL',$e->getMessage(),['pedido_id'=>$orderId,'exception'=>$e::class,'line'=>$e->getLine()]);
            return PedidoTransitionResult::failure($e->getMessage(),['phase'=>$phase]);
        }
    }

    private static function amounts(array $split): array
    {
        $out=[];
        foreach (['partner'=>'credito_guincho','platform'=>'credito_plataforma','gateway'=>'reserva_gateway'] as $field=>$type) {
            $amount=GFMoneyInput::decimal($split[$field]??null);
            if (GFExactMoney::cents($amount)>0) $out[$type]=$amount;
        }
        ksort($out,SORT_STRING);return $out;
    }

    private static function verifyPosted(PDO $db,array $order,array $payment,GFPaymentReceipt $receipt,string $key,string $fingerprint,array $split): void
    {
        if ($payment['status']!=='aprovado' || $payment['id_externo']!==$receipt->externalId || GFMoneyInput::decimal($payment['valor_guincho'])!==$split['partner']
            || GFMoneyInput::decimal($payment['valor_plataforma'])!==$split['platform'] || GFMoneyInput::decimal($order['custo_final'])!==$receipt->gross
            || GFMoneyInput::decimal($order['valor_final'])!==$receipt->gross || GFMoneyInput::decimal($order['comissao_valor'])!==$split['platform']
            || GFMoneyInput::decimal($order['valor_liquido_parceiro'])!==$split['partner'] || $order['comissao_tipo']!=='descontada') throw new DomainException('FIN_APPROVAL_STATE_DRIFT');
        $q=$db->prepare('SELECT entry_type,valor,metadata_json FROM payout_ledger_entries WHERE pagamento_id=? AND referencia_externa=?');$q->execute([$payment['id'],$receipt->externalId]);
        $actual=[];
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $meta=json_decode($row['metadata_json'],true,512,JSON_THROW_ON_ERROR);
            if (($meta['fin3_event_key']??null)!==$key || ($meta['fingerprint']??null)!==$fingerprint || isset($actual[$row['entry_type']])) throw new DomainException('FIN_PHYSICAL_LEDGER_DRIFT');
            $actual[$row['entry_type']]=GFMoneyInput::decimal($row['valor']);
        }
        ksort($actual,SORT_STRING);
        if ($actual!==self::amounts($split)) throw new DomainException('FIN_REGISTRY_WITHOUT_MATCHING_LEDGER');
        $q=$db->prepare("SELECT COUNT(*) FROM fin3_operational_inbox WHERE event_key=? AND fingerprint=? AND status='done'");$q->execute([$key,$fingerprint]);
        if ((int)$q->fetchColumn()!==1) throw new DomainException('FIN_INBOX_APPROVAL_DRIFT');
        $q=$db->prepare("SELECT COUNT(*) FROM fin3_post_commit_outbox WHERE event_key=? AND event_type='payment_approved'");$q->execute([$key]);
        if ((int)$q->fetchColumn()!==1) throw new DomainException('FIN_APPROVAL_OUTBOX_DRIFT');
    }
}

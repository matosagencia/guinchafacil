<?php
declare(strict_types=1);
/** Adaptador de acesso exclusivo de QA; catalogo privado substitui GET externo. */
final class GFInvoiceSettlementAccessQA
{
    public static function read(PDO $db,int $actor,int $invoice):array
    {
        $r=GFBillingApplyQA::inspect($db,$actor,$invoice);if(!$r['ok'])return $r;
        return ['ok'=>true,'http'=>200,'invoice_id'=>$invoice,'status'=>$r['status'],'amount'=>$r['amount'],'due_at'=>$r['due_at'],'server_reference'=>$r['server_reference'],'settlement_available'=>in_array($r['status'],['aberta','bloqueada','vencida'],true) && GFExactMoney::cents($r['amount'])>0,'live_pix'=>false];
    }
    public static function apply(PDO $db,int $actor,int $invoice,string $key,string $paymentId,string $reason,array $catalog):array
    {
        $phase='AUTHORIZE';try{
            $read=self::read($db,$actor,$invoice);if(!$read['ok'])return $read;
            $phase='VALIDATE_INPUT';$reason=trim($reason);if(!preg_match('/^[0-9]{1,32}$/D',$paymentId) || !preg_match('/^[a-f0-9]{32}$/D',$key) || !mb_check_encoding($reason,'UTF-8') || mb_strlen($reason,'UTF-8')<20 || mb_strlen($reason,'UTF-8')>500)throw new DomainException('parametro_ou_motivo_invalido',422);
            $phase='QA_LOOKUP';if(($catalog['mode']??null)!=='SIMULATED_ONLY' || ($catalog['collector']??null)!=='900001' || !is_array($catalog['payments']??null))throw new LogicException('BB_PRIVATE_QA_CATALOG_REQUIRED');
            if(!isset($catalog['payments'][$paymentId]))throw new DomainException('pagamento_nao_encontrado_na_consulta_qa',404);
            $http=$catalog['payments'][$paymentId];if(!is_array($http))throw new LogicException('BB_QA_RESPONSE_INVALID');$phase='VERIFY_RECEIPT';
            try{$receipt=GFQaInvoiceReceipt::fromQaMercadoPagoHttp($http,$paymentId,$catalog['collector']);}catch(DomainException|InvalidArgumentException $e){throw new DomainException('comprovante_nao_validado',409,$e);}
            $phase='SETTLE';return GFInvoiceSettlementQA::settle($db,$actor,$invoice,$key,$receipt,$reason);
        }catch(Throwable $e){$http=$e instanceof DomainException && in_array($e->getCode(),[403,404,409,422],true)?$e->getCode():500;$d=['project'=>'GuinchaFacil','class'=>self::class,'function'=>'apply','component'=>'settlement_interface','phase'=>$phase,'status'=>$http===500?'FAIL':'REJECTED','error'=>$e->getMessage(),'cause'=>$e->getPrevious()?->getMessage(),'file'=>basename($e->getFile()),'line'=>$e->getLine(),'exception'=>$e::class,'sqlstate'=>$e instanceof PDOException?($e->errorInfo[0]??null):null,'driver_code'=>$e instanceof PDOException?($e->errorInfo[1]??null):null,'remaining_percent'=>40];error_log(json_encode($d,JSON_THROW_ON_ERROR));return ['ok'=>false,'http'=>$http,'erro'=>$http===500?'consulta_de_baixa_indisponivel':$e->getMessage(),'diagnostic'=>$d];}
    }
}

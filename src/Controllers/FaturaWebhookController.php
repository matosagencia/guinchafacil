<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../Services/FaturaService.php';
require_once __DIR__ . '/../Services/Logger.php';

/** Endpoint A: POST /webhook/mercadopago/pix (registro pelo dono em index.php). */
final class FaturaWebhookController
{
    public function mercadoPagoPix(): void
    {
        $body=(string)file_get_contents('php://input'); $data=json_decode($body,true);
        if(!is_array($data)){http_response_code(400);echo 'invalid payload';return;}
        $x=(string)($_SERVER['HTTP_X_SIGNATURE']??'');$rid=(string)($_SERVER['HTTP_X_REQUEST_ID']??'');$id=(string)($_GET['data.id']??$data['data']['id']??$data['id']??'');$ts='';$sig='';
        foreach(explode(',',$x) as $part){[$k,$v]=array_pad(explode('=',trim($part),2),2,'');if($k==='ts')$ts=$v;if($k==='v1')$sig=$v;}
        $expected=hash_hmac('sha256',"id:{$id};request-id:{$rid};ts:{$ts};",(string)MP_WEBHOOK_SECRET);
        if($sig===''||!hash_equals($expected,$sig)){Logger::log(Logger::LEVEL_WARN,__CLASS__,__FUNCTION__,'pix_fatura','FATURA-WH-001: assinatura inválida.',['transacao'=>$id]);http_response_code(401);return;}
        $tx=(string)($data['transaction_id']??$data['id']??$id);$copia=(string)($data['pix_copia_cola']??'');
        $pdo=getPDO();$q=$pdo->prepare('SELECT id FROM faturas_parceiro WHERE pix_transacao_id=? OR (?<>\'\' AND pix_copia_cola=?) LIMIT 1');$q->execute([$tx,$copia,$copia]);$faturaId=(int)$q->fetchColumn();
        if($faturaId<1){Logger::log(Logger::LEVEL_WARN,__CLASS__,__FUNCTION__,'pix_fatura','FATURA-WH-002: fatura não localizada.',['transacao'=>$tx]);http_response_code(200);echo 'ignored';return;}
        $ok=FaturaService::marcarPaga($faturaId,$tx,'mercadopago_pix');
        Logger::log($ok?Logger::LEVEL_INFO:Logger::LEVEL_ERROR,__CLASS__,__FUNCTION__,'pix_fatura',$ok?'FATURA-WH-OK: pagamento processado.':'FATURA-WH-003: falha ao baixar fatura.',['fatura_id'=>$faturaId,'transacao'=>$tx]);http_response_code($ok?200:500);echo $ok?'OK':'error';
    }
}

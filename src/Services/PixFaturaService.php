<?php
declare(strict_types=1);

require_once __DIR__ . '/../Models/Configuracao.php';

/** Gera cobrança PIX de fatura; não reutiliza o repasse de pedido. */
final class PixFaturaService
{
    public static function gerar(int $faturaId, float $valor, string $direcao): array
    {
        $email = (string) Configuracao::get('pix_fatura_payer_email', defined('ADMIN_EMAIL') ? ADMIN_EMAIL : '');
        if ($faturaId < 1 || $valor <= 0 || $email === '') return ['ok'=>false,'erro'=>'PIX-FATURA-001: configuração ou valor inválido.'];
        $payload = json_encode(['transaction_amount'=>round($valor,2),'payment_method_id'=>'pix','description'=>"Fatura parceiro #{$faturaId} ({$direcao})",'external_reference'=>"fatura:{$faturaId}",'payer'=>['email'=>$email]]);
        $ch=curl_init('https://api.mercadopago.com/v1/payments'); curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$payload,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.MP_ACCESS_TOKEN,'Content-Type: application/json','X-Idempotency-Key: pix-fatura-'.$faturaId],CURLOPT_TIMEOUT=>30]);
        $body=(string)curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);$data=json_decode($body,true);$tx=$data['point_of_interaction']['transaction_data']??[];
        if($code>=200&&$code<300&&!empty($data['id'])&&!empty($tx['qr_code']))return ['ok'=>true,'qrcode'=>$tx['qr_code_base64']??$tx['qr_code'],'copia_cola'=>$tx['qr_code'],'transacao_id'=>'mp_'.$data['id']];
        return ['ok'=>false,'erro'=>(string)($data['message']??$err??'PIX-FATURA-002: resposta inválida.')];
    }
}

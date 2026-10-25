<?php
declare(strict_types=1);
require_once __DIR__.'/GFExactMoney.php';

/** Decodifica numeros JSON como lexemas, sem passagem por float. */
final class GFExactJson
{
    public static function decode(string $json): array
    {
        $out=''; $length=strlen($json);
        for ($i=0;$i<$length;) {
            if ($json[$i]==='"') {
                $start=$i++;
                while ($i<$length) {
                    if ($json[$i]==='\\') { $i+=2; continue; }
                    if ($json[$i++]==='"') break;
                }
                $out.=substr($json,$start,$i-$start);
            } elseif ($json[$i]==='-' || str_contains('0123456789',$json[$i])) {
                if (!preg_match('/\G-?(?:0|[1-9][0-9]*)(?:\.[0-9]+)?(?:[eE][+-]?[0-9]+)?/A',$json,$m,0,$i)) throw new InvalidArgumentException('FIN_INVALID_JSON_NUMBER');
                $out.='"'.$m[0].'"'; $i+=strlen($m[0]);
            } else { $out.=$json[$i++]; }
        }
        $decoded=json_decode($out,true,512,JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) throw new InvalidArgumentException('FIN_JSON_OBJECT_REQUIRED');
        return $decoded;
    }
}

/** Somente o adaptador servidor chama esta factory com resposta de GET autenticado.
 * O body do webhook nunca e aceito como comprovante de tarifa.
 */
final class GFPaymentReceipt
{
    private function __construct(
        public string $provider, public string $externalId,
        public int $orderId, public string $gross,
        public string $fee, public string $collector,
        public string $currency
    ) {}

    public static function fromMercadoPagoHttp(array $http,string $requestedId,string $expectedCollector): self
    {
        if (($http['code']??0)!==200 || ($http['error']??'')!=='' || !is_string($http['body']??null)) throw new DomainException('FIN_PROVIDER_LOOKUP_FAILED');
        if (!preg_match('/^[0-9]{1,32}$/D',$requestedId) || !preg_match('/^[0-9]+$/D',$expectedCollector)) throw new InvalidArgumentException('FIN_SERVER_PROVIDER_CONFIG_REQUIRED');
        $p=GFExactJson::decode($http['body']);
        if (($p['id']??null)!==$requestedId || ($p['status']??null)!=='approved') throw new DomainException('FIN_PROVIDER_ID_OR_STATUS_MISMATCH');
        if (($p['currency_id']??null)!=='BRL' || ($p['collector_id']??null)!==$expectedCollector) throw new DomainException('FIN_PROVIDER_CURRENCY_OR_COLLECTOR_MISMATCH');
        $reference=$p['external_reference']??'';
        if (!is_string($reference) || !preg_match('/^[1-9][0-9]{0,9}$/D',$reference) || (int)$reference>2147483647) throw new DomainException('FIN_ORDER_REFERENCE_INVALID');
        $gross=GFMoneyInput::decimal($p['transaction_amount']??null);
        $net=GFMoneyInput::decimal($p['transaction_details']['net_received_amount']??null);
        if (!isset($p['fee_details']) || !is_array($p['fee_details'])) throw new DomainException('FIN_ACTUAL_FEE_MISSING');
        $fee=0;
        foreach ($p['fee_details'] as $detail) {
            if (!is_array($detail) || !in_array($detail['fee_payer']??null,['collector','payer'],true)) throw new DomainException('FIN_FEE_PAYER_UNVERIFIED');
            $amount=GFExactMoney::cents(GFMoneyInput::decimal($detail['amount']??null));
            if ($detail['fee_payer']==='collector') {
                if (($detail['type']??null)!=='mercadopago_fee') throw new DomainException('FIN_COLLECTOR_FEE_TYPE_NOT_MIGRATED');
                $fee+=$amount;
            }
            if ($fee>GFExactMoney::MAX_CENTS) throw new OverflowException('FIN_FEE_OVERFLOW');
        }
        if (GFExactMoney::cents($gross)<1 || GFExactMoney::cents($gross)-$fee!==GFExactMoney::cents($net)) throw new DomainException('FIN_ACTUAL_FEE_NET_MISMATCH');
        return new self('mercadopago','mp_'.$requestedId,(int)$reference,$gross,GFExactMoney::decimal($fee),$expectedCollector,'BRL');
    }

    public function fingerprint(): string
    {
        return hash('sha256',json_encode([$this->provider,$this->externalId,$this->orderId,$this->gross,$this->fee,$this->collector,$this->currency],JSON_THROW_ON_ERROR));
    }
}

final class GFMoneyInput
{
    public static function decimal(mixed $value): string
    {
        if (!is_string($value)) throw new InvalidArgumentException('FIN_FLOAT_OR_NONSTRING_NOT_ALLOWED');
        return GFExactMoney::decimal(GFExactMoney::cents($value));
    }

    /** Config legado aceita 0.08 ou 8; municipio usa fracao assinada (-0.02 = -2pp). */
    public static function basisPoints(mixed $raw,bool $legacyPercent=false): int
    {
        if (!is_string($raw) || !preg_match('/^(-?)([0-9]{1,3})(?:\.([0-9]{1,4}))?$/D',$raw,$m)) throw new InvalidArgumentException('FIN_RATE_NOT_CANONICAL');
        $units=(int)$m[2]*10000+(int)str_pad($m[3]??'',4,'0');
        if ($legacyPercent && $units>10000) {
            if ($units%100!==0) throw new InvalidArgumentException('FIN_RATE_NOT_WHOLE_BASIS_POINTS');
            $units=intdiv($units,100);
        }
        return $m[1]==='-'?-$units:$units;
    }
}

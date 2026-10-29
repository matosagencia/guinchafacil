<?php
declare(strict_types=1);
final class ConversionPriceSnapshotService
{
    public static function persist(PDO $pdo, int $pedidoId, array $quote): string
    {
        if ($pedidoId <= 0 || !$pdo->inTransaction()) throw new LogicException('PRICE-CONVERSION-SNAPSHOT-TRANSACTION-REQUIRED');
        if (!($quote['ok'] ?? false) || !is_numeric($quote['preco_cliente'] ?? null)) throw new LogicException('PRICE-CONVERSION-SNAPSHOT-UNOFFICIAL');
        $payload=['fase'=>'reboque_complementar','service_code'=>(string)($quote['service_code']??''),'customer_total'=>round((float)$quote['preco_cliente'],2),'km_cobrado'=>round((float)($quote['km_transporte']??0),2),'detalhamento'=>$quote['detalhamento']??null,'pricing_version'=>(string)($quote['pricing_version']??'conversion-v1')];
        $json=json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);$hash=hash('sha256',$json.'|'.number_format($payload['customer_total'],2,'.',''));
        $s=$pdo->prepare('SELECT snapshot_hash FROM order_conversion_price_snapshots WHERE pedido_id=? AND fase=? LIMIT 1');$s->execute([$pedidoId,'reboque_complementar']);$old=$s->fetchColumn();
        if($old!==false){if(hash_equals((string)$old,$hash))return $hash;throw new LogicException('PRICE-CONVERSION-SNAPSHOT-CONFLICT');}
        $pdo->prepare('INSERT INTO order_conversion_price_snapshots (pedido_id,fase,customer_total,km_cobrado,pricing_version,snapshot_json,snapshot_hash) VALUES (?,?,?,?,?,?,?)')->execute([$pedidoId,'reboque_complementar',$payload['customer_total'],$payload['km_cobrado'],$payload['pricing_version'],$json,$hash]);return $hash;
    }
    public static function customerTotal(PDO $pdo,int $pedidoId):?float{$s=$pdo->prepare('SELECT customer_total FROM order_conversion_price_snapshots WHERE pedido_id=? AND fase=? LIMIT 1');$s->execute([$pedidoId,'reboque_complementar']);$v=$s->fetchColumn();return $v===false?null:round((float)$v,2);}
}

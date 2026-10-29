<?php
declare(strict_types=1);

/** Persistência canônica do resultado já calculado. Não recalcula preços. */
final class OrderPriceSnapshotWriter
{
    public static function persist(PDO $pdo, int $pedidoId, array $quote): string
    {
        if ($pedidoId <= 0 || !$pdo->inTransaction()) throw new LogicException('PRICE-SNAPSHOT-TRANSACTION-REQUIRED');
        $o = $quote['oficial'] ?? null;
        if (!is_array($o) || !($o['ok'] ?? false)
            || empty($o['regra_id']) || empty($o['regra_version'])
            || !is_array($o['detalhamento'] ?? null)) {
            throw new LogicException('PRICE-SNAPSHOT-UNOFFICIAL');
        }
        $det = $o['detalhamento'];
        $total = round((float)$o['preco_cliente'], 2);
        $base = round((float)($det['base_comissao'] ?? -1), 2);
        $pedagio = round((float)($det['pedagio'] ?? -1), 2);
        if ($total < 0 || $base < 0 || $pedagio < 0 || abs($total - $base - $pedagio) > 0.011) {
            throw new LogicException('PRICE-SNAPSHOT-BASE-MISMATCH');
        }
        if (abs((float)($o['comissao_percentual'] ?? -1) - 0.08) > 0.000001) {
            throw new LogicException('PRICE-COMMISSION-CONFIG-MISMATCH');
        }
        $comissao = round($base * 0.08, 2);
        if (abs($comissao - (float)($o['comissao_valor'] ?? -1)) > 0.011) {
            throw new LogicException('PRICE-SNAPSHOT-COMMISSION-MISMATCH');
        }
        require_once __DIR__ . '/../../Models/Configuracao.php';
        $cfg = Configuracao::getAll();
        $reservaPct = (float)($cfg['reserva_gateway_credito'] ?? 0.0498);
        if (!is_finite($reservaPct) || $reservaPct < 0 || $reservaPct > 1) {
            throw new LogicException('PRICE-SNAPSHOT-RESERVE-INVALID');
        }
        $reserva = round($total * $reservaPct, 2);
        $parceiro = round($total - $comissao - $reserva, 2);
        if ($parceiro < 0 || abs(round($parceiro + $comissao + $reserva, 2) - $total) > 0.001) {
            throw new LogicException('PRICE-SNAPSHOT-SPLIT-INVALID');
        }
        $contexto = $det;
        $contexto['service_type_id'] = (int)$o['service_type_id'];
        $contexto['service_code'] = (string)$o['service_code'];
        $contexto['pricing_zone_id'] = $o['pricing_zone_id'];
        $contexto['regra_id'] = (int)$o['regra_id'];
        $contexto['regra_version'] = (string)$o['regra_version'];
        $contexto['version'] = (int)$o['regra_version'];
        $contexto['comissao'] = $comissao;
        $contexto['comissao_percentual'] = 0.08;
        $contexto['valor_total'] = $total;
        $contexto['reserva_gateway'] = $reserva;
        $contexto['reserva_gateway_percentual'] = $reservaPct;
        $contexto['provider_total'] = $parceiro;
        $contexto['fonte_km'] = $o['fonte_km'];
        $contexto['pricing_engine'] = 'PrecificacaoService';
        $json = json_encode($contexto, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
        $hash = hash('sha256', $json . '|' . number_format($total,2,'.',''));
        $st = $pdo->prepare('SELECT snapshot_hash FROM order_price_snapshots WHERE order_id = ? LIMIT 1');
        $st->execute([$pedidoId]);
        $anterior = $st->fetchColumn();
        if ($anterior !== false) {
            if (hash_equals((string)$anterior, $hash)) return $hash;
            throw new LogicException('PRICE-SNAPSHOT-CONFLICT');
        }
        $st = $pdo->prepare('INSERT INTO order_price_snapshots (order_id, pricing_rule_id, pricing_version, calculation_context_json, customer_total, provider_total, platform_fee, snapshot_hash, created_at) VALUES (?,?,?,?,?,?,?,?,NOW())');
        $st->execute([$pedidoId,(int)$o['regra_id'],(int)$o['regra_version'],$json,$total,$parceiro,$comissao,$hash]);
        return $hash;
    }
}

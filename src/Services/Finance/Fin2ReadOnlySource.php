<?php
declare(strict_types=1);

namespace App\Services\Finance;

/** Fonte FIN.2 somente leitura; não cria fechamento nem altera saldo. */
final class Fin2ReadOnlySource
{
    public static function movimentosPlataforma(\PDO $db, ?int $guinchoId = null): array
    {
        $sql = 'SELECT pg.id, p.guincho_id, pg.valor_total, pg.valor_plataforma,
                       COALESCE(SUM(CASE WHEN le.entry_type = "reserva_gateway" THEN le.valor ELSE 0 END), 0) AS taxa_gateway,
                       COUNT(le.id) AS ledger_linhas
                  FROM pagamentos pg
                  JOIN pedidos p ON p.id = pg.pedido_id
                  JOIN payout_ledger_entries le ON le.pagamento_id = pg.id
                 WHERE pg.status = "aprovado" AND le.entry_type IN ("credito_guincho", "credito_plataforma", "reserva_gateway")';
        $params = [];
        if ($guinchoId !== null) { $sql .= ' AND p.guincho_id = ?'; $params[] = $guinchoId; }
        $sql .= ' GROUP BY pg.id, p.guincho_id, pg.valor_total, pg.valor_plataforma ORDER BY pg.id';
        $q = $db->prepare($sql); $q->execute($params);
        $rows = [];
        foreach ($q->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $gross = self::cents($row['valor_total']); $commission = self::cents($row['valor_plataforma']); $fee = self::cents($row['taxa_gateway']);
            if ($gross < 0 || $commission < 0 || $fee < 0 || $commission + $fee > $gross || (int)$row['ledger_linhas'] < 1) throw new \DomainException('FIN2_SOURCE_INVALID');
            $rows[] = ['id' => 'pagamento:' . (int)$row['id'], 'guincho_id' => (int)$row['guincho_id'], 'canal' => 'plataforma', 'bruto_centavos' => $gross, 'comissao_centavos' => $commission, 'taxa_parceiro_centavos' => $fee];
        }

        $directSql = 'SELECT e.id, e.parceiro_id, e.valor_centavos, p.valor_total
                        FROM fin_bilateral_eventos e
                        JOIN pagamentos p ON p.id = e.pagamento_id
                       WHERE e.parceiro_tipo = "guincho"
                         AND e.tipo_evento = "debito_comissao"
                         AND e.canal = "parceiro"
                         AND e.referencia_tipo = "r11ao_direct"
                         AND e.estado = "confirmado"';
        $directParams = [];
        if ($guinchoId !== null) { $directSql .= ' AND e.parceiro_id = ?'; $directParams[] = $guinchoId; }
        $directSql .= ' ORDER BY e.id';
        $q = $db->prepare($directSql); $q->execute($directParams);
        foreach ($q->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $gross = self::cents($row['valor_total']); $commission = (int)$row['valor_centavos'];
            if ($gross <= 0 || $commission <= 0 || $commission > $gross) throw new \DomainException('FIN2_DIRECT_SOURCE_INVALID');
            $rows[] = ['id' => 'evento_direto:' . (int)$row['id'], 'guincho_id' => (int)$row['parceiro_id'], 'canal' => 'parceiro', 'bruto_centavos' => $gross, 'comissao_centavos' => $commission, 'taxa_parceiro_centavos' => 0];
        }
        return $rows;
    }

    private static function cents(mixed $value): int
    {
        $text = trim((string)$value);
        if (!preg_match('/^\d+(?:\.\d{1,2})?$/D', $text)) throw new \DomainException('FIN2_MONEY_INVALID');
        [$whole, $fraction] = array_pad(explode('.', $text, 2), 2, '');
        return ((int)$whole * 100) + (int)str_pad($fraction, 2, '0');
    }
}

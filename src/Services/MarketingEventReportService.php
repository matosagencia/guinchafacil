<?php
declare(strict_types=1);

final class MarketingEventReportService
{
    public static function resumo(string $inicio, string $fim): array
    {
        $base = ['total' => 0, 'cotacoes' => 0, 'parceiros' => 0, 'pedidos' => 0, 'por_evento' => [], 'schema_disponivel' => false];
        try {
            $stmt = getPDO()->prepare("SELECT event_name, COUNT(*) total FROM marketing_events WHERE criado_em >= ? AND criado_em < DATE_ADD(?, INTERVAL 1 DAY) GROUP BY event_name ORDER BY total DESC");
            $stmt->execute([$inicio, $fim]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $name = (string)$row['event_name'];
                $count = (int)$row['total'];
                $base['por_evento'][$name] = $count;
                $base['total'] += $count;
                if (in_array($name, ['begin_quote', 'generate_lead', 'quote_started'], true)) $base['cotacoes'] += $count;
                if (in_array($name, ['partner_interest', 'partner_lead'], true)) $base['parceiros'] += $count;
                if (in_array($name, ['create_order', 'order_created', 'purchase'], true)) $base['pedidos'] += $count;
            }
            $base['schema_disponivel'] = true;
        } catch (Throwable $e) {
            // The report remains usable before the optional migration is applied.
        }
        return $base;
    }
}

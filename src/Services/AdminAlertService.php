<?php

declare(strict_types=1);

/**
 * src/Services/AdminAlertService.php
 * Alertas OPERACIONAIS para o Command Center do admin (mapa ao vivo +
 * painel de alertas), no mesmo espírito do protótipo aprovado
 * (doc/PROTOTIPO_VISUAL_PERFIS_GUINCHAFACIL.html, seção "admin"):
 * evidência de coleta/entrega falhou, GPS degradado num pedido ativo,
 * job de PIX em retentativa, CNH de guincheiro perto de vencer.
 *
 * Deliberadamente separado do HealthService: aquele cobre saúde de
 * infraestrutura/checklist de produção (cron atrasado, .env, schema),
 * este cobre gestão do dia a dia da operação — os dois são reais, só
 * respondem perguntas diferentes.
 */
class AdminAlertService
{
    /**
     * @param int $limite Corte final após ordenar por severidade — mantido
     *                     baixo (8) por padrão pro widget do Command Center.
     * @param int $limitePorCategoria Quantas linhas cada sub-consulta busca
     *                     ANTES do corte final — a tela dedicada /admin/alertas
     *                     usa um valor bem maior aqui pra listar "todos", não
     *                     só o top 8 do widget.
     */
    public static function listar(int $limite = 8, int $limitePorCategoria = 5): array
    {
        $alertas = array_merge(
            self::evidenciasFalhas($limitePorCategoria),
            self::gpsDegradado($limitePorCategoria),
            self::pixEmRetry($limitePorCategoria),
            self::pixFalhou($limitePorCategoria),
            self::conclusoesManuaisPendentes($limitePorCategoria),
            self::demandasPendentesAntigas($limitePorCategoria),
            self::cnhProximaVencimento()
        );

        usort($alertas, static function (array $a, array $b): int {
            $peso = ['erro' => 0, 'aviso' => 1, 'info' => 2];
            $cmp = ($peso[$a['nivel']] ?? 3) <=> ($peso[$b['nivel']] ?? 3);
            if ($cmp !== 0) return $cmp;
            return strcmp((string)($b['quando'] ?? ''), (string)($a['quando'] ?? ''));
        });

        return array_slice($alertas, 0, $limite);
    }

    private static function evidenciasFalhas(int $limite = 5): array
    {
        try {
            $stmt = getPDO()->prepare(
                "SELECT pe.pedido_id, pe.tipo, pe.server_timestamp
                 FROM pedido_evidencias pe
                 WHERE pe.status = 'rejected'
                   AND pe.server_timestamp >= DATE_SUB(NOW(), INTERVAL 2 DAY)
                 ORDER BY pe.server_timestamp DESC
                 LIMIT " . max(1, min(500, $limite))
            );
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return [];
        }

        return array_map(static function (array $r): array {
            $tipo = $r['tipo'] === 'coleta' ? 'Evidência de coleta falhou' : 'Evidência de entrega falhou';
            return [
                'label' => $tipo,
                'info' => 'Pedido #' . (int)$r['pedido_id'],
                'nivel' => 'erro',
                'quando' => (string)($r['server_timestamp'] ?? ''),
            ];
        }, $rows);
    }

    private static function gpsDegradado(int $limite = 5): array
    {
        try {
            $stmt = getPDO()->prepare(
                "SELECT pl.pedido_id, pl.accuracy_m, pl.server_timestamp
                 FROM pedido_localizacoes pl
                 JOIN pedidos p ON p.id = pl.pedido_id
                 WHERE p.status IN ('a_caminho','no_local','em_reboque')
                   AND pl.accuracy_m IS NOT NULL AND pl.accuracy_m > 75
                   AND pl.id IN (
                       SELECT MAX(id) FROM pedido_localizacoes GROUP BY pedido_id
                   )
                 ORDER BY pl.server_timestamp DESC
                 LIMIT " . max(1, min(500, $limite))
            );
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return [];
        }

        return array_map(static function (array $r): array {
            return [
                'label' => 'GPS degradado',
                'info' => 'Pedido #' . (int)$r['pedido_id'] . ' · precisão ' . number_format((float)$r['accuracy_m'], 0) . ' m',
                'nivel' => 'aviso',
                'quando' => (string)($r['server_timestamp'] ?? ''),
            ];
        }, $rows);
    }

    private static function pixEmRetry(int $limite = 5): array
    {
        try {
            $stmt = getPDO()->prepare(
                "SELECT id, pedido_id, attempt_count, max_attempts, updated_at
                 FROM payment_jobs
                 WHERE job_type LIKE '%pix%'
                   AND status IN ('retry', 'queued', 'processing')
                   AND attempt_count > 0
                 ORDER BY updated_at DESC
                 LIMIT " . max(1, min(500, $limite))
            );
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return [];
        }

        return array_map(static function (array $r): array {
            return [
                'label' => 'PIX em retentativa',
                'info' => 'Job #' . (int)$r['id'] . ' · tentativa ' . (int)$r['attempt_count'] . '/' . (int)$r['max_attempts'],
                'nivel' => 'aviso',
                'quando' => (string)($r['updated_at'] ?? ''),
            ];
        }, $rows);
    }

    private static function pixFalhou(int $limite = 5): array
    {
        // Achado real: um job de PIX que esgota as tentativas vira
        // status='failed' e some por completo do painel — pixEmRetry() só
        // olha 'retry'/'queued'/'processing'. Um repasse que falhou de vez é
        // MAIS urgente que um que ainda está tentando sozinho (precisa de
        // intervenção manual do admin, não vai se resolver sozinho), então
        // merece o nível 'erro', não 'aviso'.
        try {
            $stmt = getPDO()->prepare(
                "SELECT id, pedido_id, attempt_count, max_attempts, updated_at
                   FROM payment_jobs
                  WHERE job_type LIKE '%pix%'
                    AND status = 'failed'
                    AND updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                  ORDER BY updated_at DESC
                  LIMIT " . max(1, min(500, $limite))
            );
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return [];
        }

        return array_map(static function (array $r): array {
            return [
                'label' => 'PIX falhou definitivamente',
                'info' => 'Job #' . (int)$r['id'] . ' · esgotou ' . (int)$r['attempt_count'] . '/' . (int)$r['max_attempts'] . ' tentativas — requer ação manual',
                'nivel' => 'erro',
                'quando' => (string)($r['updated_at'] ?? ''),
            ];
        }, $rows);
    }

    private static function conclusoesManuaisPendentes(int $limite = 5): array
    {
        // Salvaguarda de GPS/servidor indisponível (migration_conclusao_manual_v1.sql):
        // quando o admin conclui um pedido manualmente (sem geofence/GPS confirmando),
        // o pedido nasce com revisao_manual_status='pendente' e PRECISA aparecer aqui —
        // conclusão manual sem verificação posterior é vetor conhecido de fraude
        // (courier marca entregue sem entregar). Nível 'erro' porque não é opcional.
        try {
            $stmt = getPDO()->prepare(
                "SELECT id, concluido_manual_em, concluido_manual_admin_id
                   FROM pedidos
                  WHERE concluido_manualmente = 1
                    AND revisao_manual_status = 'pendente'
                  ORDER BY concluido_manual_em DESC
                  LIMIT " . max(1, min(500, $limite))
            );
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return [];
        }

        return array_map(static function (array $r): array {
            return [
                'label' => 'Conclusão manual aguardando revisão',
                'info' => 'Pedido #' . (int)$r['id'] . ' concluído sem GPS — revisar comprovantes',
                'nivel' => 'erro',
                'quando' => (string)($r['concluido_manual_em'] ?? ''),
            ];
        }, $rows);
    }

    private static function demandasPendentesAntigas(int $limite = 5): array
    {
        // Separação de deveres (funcionário cria, gerente decide) só funciona
        // como controle real se ninguém deixar demanda parada — uma demanda
        // pendente há muito tempo é tão problema operacional quanto um PIX
        // travado. Nível 'erro' porque pode ser um cliente/guincheiro esperando
        // uma decisão de cancelamento/pagamento há mais de um dia.
        try {
            $stmt = getPDO()->prepare(
                "SELECT d.id, d.tipo, d.pedido_id, d.criado_em
                   FROM demandas d
                  WHERE d.status IN ('pendente', 'aprovada_parcial')
                    AND d.criado_em < DATE_SUB(NOW(), INTERVAL 24 HOUR)
                  ORDER BY d.criado_em ASC
                  LIMIT " . max(1, min(500, $limite))
            );
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return [];
        }

        return array_map(static function (array $r): array {
            return [
                'label' => 'Demanda pendente há +24h',
                'info' => ucfirst(str_replace('_', ' ', (string)$r['tipo'])) . ' — demanda #' . (int)$r['id'] . ($r['pedido_id'] ? ' (pedido #' . (int)$r['pedido_id'] . ')' : ''),
                'nivel' => 'erro',
                'quando' => (string)($r['criado_em'] ?? ''),
            ];
        }, $rows);
    }

    private static function cnhProximaVencimento(): array
    {
        try {
            $stmt = getPDO()->prepare(
                "SELECT COUNT(*) AS total
                 FROM guinchos
                 WHERE cnh_validade IS NOT NULL
                   AND cnh_validade BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)"
            );
            $stmt->execute();
            $total = (int)($stmt->fetchColumn() ?: 0);
        } catch (Throwable $e) {
            return [];
        }

        if ($total <= 0) {
            return [];
        }

        return [[
            'label' => 'CNH próxima do vencimento',
            'info' => $total . ' guincheiro(s) em 30 dias',
            'nivel' => 'info',
            'quando' => date('Y-m-d H:i:s'),
        ]];
    }
}

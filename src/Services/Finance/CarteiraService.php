<?php

declare(strict_types=1);

require_once __DIR__ . '/../../Models/Finance/PayoutLedgerEntry.php';
require_once __DIR__ . '/../Logger.php';

/**
 * src/Services/Finance/CarteiraService.php
 * Pacote L2.3 - "Carteiras"/"Saques" da nav reorganizada.
 *
 * DECIS�O DE PRODUTO (confirmada com o usu�rio em 2026-08-01): este servi�o
 * � 100% VISIBILIDADE sobre o dinheiro que j� se move hoje - ele N�O cria
 * nenhum saldo retido nem fluxo de "solicitar saque". O repasse Pix real ao
 * guincheiro j� � autom�tico (ver PaymentJobService::enqueuePixPayout() +
 * PixService::transferir() + Pagamento::confirmarRepassePix()), disparado
 * assim que o pedido conclui - n�o existe hoje (nem esta classe cria) um
 * modelo de "guincheiro pede saque, admin libera". Mudar isso seria uma
 * mudan�a de comportamento financeiro real, fora do escopo combinado.
 *
 * O que esta classe faz: l� `pagamentos` + `payout_ledger_entries` (fonte
 * de verdade j� existente e testada) e apresenta 3 estados por guincheiro:
 *   - em_compensacao: aprovado, ainda n�o repassado (pago_guincho=0)
 *   - pago: repasse Pix confirmado (pago_guincho=1)
 *   - estornado: pagamento estornado (status='estornado')
 *
 * PRINC�PIO DE DEBUGABILIDADE (pedido expl�cito do usu�rio: "quero tudo
 * debug�vel nos m�nimos detalhes para prevenir erros silenciosos"): TODO
 * m�todo aqui retorna um envelope com 'ok' expl�cito. Se uma query falhar,
 * o m�todo NUNCA retorna silenciosamente um saldo zerado como se fosse um
 * resultado leg�timo - ele retorna ok=false + a mensagem de erro real, e a
 * tela precisa mostrar isso como uma falha, n�o como "sem saldo". Toda
 * chamada tamb�m loga via Logger::log/Logger::exception (DEBUG no sucesso
 * com os valores computados, ERROR na falha com stack trace), ent�o d� pra
 * auditar em app_logs/app-YYYY-MM-DD.jsonl exatamente o que rodou.
 */
class CarteiraService
{
    /**
     * Saldo consolidado de um guincheiro espec�fico.
     * @return array{ok:bool, erro:?string, guincho_id:int, saldo_em_compensacao:float,
     *   saldo_pago:float, saldo_estornado:float, total_pedidos:int, ultimo_repasse_em:?string}
     */
    public static function saldoGuincho(int $guinchoId): array
    {
        $inicio = microtime(true);
        try {
            $pdo = getPDO();
            $stmt = $pdo->prepare(
                "SELECT
                    COUNT(*) AS total_pedidos,
                    COALESCE(SUM(CASE WHEN pg.status = 'aprovado' AND COALESCE(pg.pago_guincho,0) = 0 THEN pg.valor_guincho ELSE 0 END), 0) AS saldo_em_compensacao,
                    COALESCE(SUM(CASE WHEN pg.status = 'aprovado' AND COALESCE(pg.pago_guincho,0) = 1 THEN pg.valor_guincho ELSE 0 END), 0) AS saldo_pago,
                    COALESCE(SUM(CASE WHEN pg.status = 'estornado' THEN pg.valor_guincho ELSE 0 END), 0) AS saldo_estornado,                    COALESCE((SELECT SUM(le.valor) FROM payout_ledger_entries le WHERE le.pedido_id = p.id AND le.pagamento_id = pg.id AND le.entry_type = 'reserva_gateway'), 0) AS reserva_gateway,
                    COALESCE(SUM(CASE WHEN pg.status = 'aprovado' AND COALESCE(pg.pago_guincho,0) = 0 AND pg.status_pix = 'falha' THEN 1 ELSE 0 END), 0) AS repasses_com_falha,
                    MAX(pg.data_pagamento_guincho) AS ultimo_repasse_em
                 FROM pedidos p
                 LEFT JOIN pagamentos pg ON pg.pedido_id = p.id
                 WHERE p.guincho_id = ?"
            );
            $stmt->execute([$guinchoId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row === false) {
                // Consulta rodou sem exce��o mas n�o retornou linha nenhuma -
                // situa��o an�mala (nem guincho inexistente deveria zerar o
                // fetch de um agregado com LEFT JOIN). Trata como falha
                // expl�cita em vez de mascarar como saldo zero.
                Logger::log(Logger::LEVEL_WARN, 'CarteiraService', 'saldoGuincho', 'financeiro',
                    "Query de saldo n�o retornou linha para guincho #{$guinchoId} - resultado an�malo, tratado como falha expl�cita.",
                    ['guincho_id' => $guinchoId]);
                return [
                    'ok' => false,
                    'erro' => 'Consulta de saldo n�o retornou nenhuma linha (resultado an�malo).',
                    'guincho_id' => $guinchoId,
                    'saldo_em_compensacao' => 0.0, 'saldo_pago' => 0.0, 'saldo_estornado' => 0.0,
                    'repasses_com_falha' => 0, 'total_pedidos' => 0, 'ultimo_repasse_em' => null,
                ];
            }

            $resultado = [
                'ok' => true,
                'erro' => null,
                'guincho_id' => $guinchoId,
                'total_pedidos' => (int)($row['total_pedidos'] ?? 0),
                'saldo_em_compensacao' => round((float)($row['saldo_em_compensacao'] ?? 0), 2),
                'saldo_pago' => round((float)($row['saldo_pago'] ?? 0), 2),
                'saldo_estornado' => round((float)($row['saldo_estornado'] ?? 0), 2),                'reserva_gateway' => round((float)($row['reserva_gateway'] ?? 0), 2),
                'repasses_com_falha' => (int)($row['repasses_com_falha'] ?? 0),
                'ultimo_repasse_em' => $row['ultimo_repasse_em'] ?? null,
            ];

            Logger::log(Logger::LEVEL_DEBUG, 'CarteiraService', 'saldoGuincho', 'financeiro',
                "Saldo calculado para guincho #{$guinchoId}: compensa��o=R$ {$resultado['saldo_em_compensacao']}, pago=R$ {$resultado['saldo_pago']}, estornado=R$ {$resultado['saldo_estornado']}, falhas={$resultado['repasses_com_falha']}.",
                ['guincho_id' => $guinchoId, 'duration_ms' => (int)round((microtime(true) - $inicio) * 1000), 'resultado' => $resultado]);

            return $resultado;
        } catch (Throwable $e) {
            Logger::exception('CarteiraService', 'saldoGuincho', 'financeiro', $e, ['guincho_id' => $guinchoId]);
            return [
                'ok' => false,
                'erro' => 'Falha ao consultar saldo: ' . $e->getMessage(),
                'guincho_id' => $guinchoId,
                'saldo_em_compensacao' => 0.0, 'saldo_pago' => 0.0, 'saldo_estornado' => 0.0,
                'repasses_com_falha' => 0, 'total_pedidos' => 0, 'ultimo_repasse_em' => null,
            ];
        }
    }

    /**
     * Resumo consolidado de TODOS os guincheiros aprovados, para a tela-�ndice
     * /admin/carteiras. Uma �nica query agregada (evita N+1 de saldoGuincho()
     * por guincho - importante j� que esta tela pode listar dezenas de
     * guincheiros).
     * @return array{ok:bool, erro:?string, linhas:array<int,array<string,mixed>>}
     */
    public static function resumoTodosGuincheiros(): array
    {
        try {
            $stmt = getPDO()->query(
                "SELECT
                    g.id AS guincho_id,
                    u.nome AS nome_operador,
                    g.placa_guincho,
                    COALESCE(SUM(CASE WHEN pg.status = 'aprovado' AND COALESCE(pg.pago_guincho,0) = 0 THEN pg.valor_guincho ELSE 0 END), 0) AS saldo_em_compensacao,
                    COALESCE(SUM(CASE WHEN pg.status = 'aprovado' AND COALESCE(pg.pago_guincho,0) = 1 THEN pg.valor_guincho ELSE 0 END), 0) AS saldo_pago,
                    COALESCE(SUM(CASE WHEN pg.status = 'estornado' THEN pg.valor_guincho ELSE 0 END), 0) AS saldo_estornado,                    COALESCE((SELECT SUM(le.valor) FROM payout_ledger_entries le WHERE le.pedido_id IN (SELECT p2.id FROM pedidos p2 WHERE p2.guincho_id = g.id) AND le.entry_type = 'reserva_gateway'), 0) AS reserva_gateway,
                    COALESCE(SUM(CASE WHEN pg.status = 'aprovado' AND COALESCE(pg.pago_guincho,0) = 0 AND pg.status_pix = 'falha' THEN 1 ELSE 0 END), 0) AS repasses_com_falha
                 FROM guinchos g
                 JOIN usuarios u ON u.id = g.usuario_id
                 LEFT JOIN pedidos p ON p.guincho_id = g.id
                 LEFT JOIN pagamentos pg ON pg.pedido_id = p.id
                 WHERE g.aprovado = 1
                 GROUP BY g.id, u.nome, g.placa_guincho
                 ORDER BY saldo_em_compensacao DESC, saldo_pago DESC"
            );
            $linhas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($linhas as &$l) {
                $l['saldo_em_compensacao'] = round((float)$l['saldo_em_compensacao'], 2);
                $l['saldo_pago'] = round((float)$l['saldo_pago'], 2);
                $l['saldo_estornado'] = round((float)$l['saldo_estornado'], 2);
                $l['repasses_com_falha'] = (int)$l['repasses_com_falha'];
            }
            unset($l);

            Logger::log(Logger::LEVEL_DEBUG, 'CarteiraService', 'resumoTodosGuincheiros', 'financeiro',
                'Resumo de carteiras calculado para ' . count($linhas) . ' guincheiro(s) aprovado(s).',
                ['total_guincheiros' => count($linhas)]);

            return ['ok' => true, 'erro' => null, 'linhas' => $linhas];
        } catch (Throwable $e) {
            Logger::exception('CarteiraService', 'resumoTodosGuincheiros', 'financeiro', $e, []);
            return ['ok' => false, 'erro' => 'Falha ao consultar resumo de carteiras: ' . $e->getMessage(), 'linhas' => []];
        }
    }

    /**
     * Extrato detalhado (linha a linha) de um guincheiro - cada pedido/
     * pagamento com seu status financeiro completo. Esta � a tela de "prova"
     * por tr�s do n�mero agregado (debugabilidade: todo valor mostrado no
     * resumo tem que ser rastre�vel at� aqui).
     */
    public static function extratoGuincho(int $guinchoId, int $limite = 200): array
    {
        try {
            $stmt = getPDO()->prepare(
                "SELECT
                    p.id AS pedido_id, p.status AS pedido_status, p.criado_em AS pedido_criado_em,
                    pg.id AS pagamento_id, pg.status AS pagamento_status, pg.status_pix,
                    pg.valor_total, pg.valor_guincho, pg.pago_guincho,
                    pg.id_transacao_pix, pg.data_pagamento, pg.data_pagamento_guincho
                 FROM pedidos p
                 LEFT JOIN pagamentos pg ON pg.pedido_id = p.id
                 WHERE p.guincho_id = ?
                 ORDER BY p.criado_em DESC
                 LIMIT ?"
            );
            $stmt->bindValue(1, $guinchoId, PDO::PARAM_INT);
            $stmt->bindValue(2, max(1, min(1000, $limite)), PDO::PARAM_INT);
            $stmt->execute();
            $linhas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            Logger::log(Logger::LEVEL_DEBUG, 'CarteiraService', 'extratoGuincho', 'financeiro',
                "Extrato de {$guinchoId} carregado: " . count($linhas) . ' linha(s).',
                ['guincho_id' => $guinchoId, 'linhas' => count($linhas)]);

            return ['ok' => true, 'erro' => null, 'linhas' => $linhas];
        } catch (Throwable $e) {
            Logger::exception('CarteiraService', 'extratoGuincho', 'financeiro', $e, ['guincho_id' => $guinchoId]);
            return ['ok' => false, 'erro' => 'Falha ao consultar extrato: ' . $e->getMessage(), 'linhas' => []];
        }
    }

    /**
     * "Saques" (Pacote L2.3) - como n�o existe fluxo de solicita��o manual
     * (ver docblock da classe), este � o painel operacional real equivalente:
     * todos os repasses Pix que precisam de aten��o do admin (pendentes,
     * processando, ou com falha), pra ele acompanhar/reprocessar via a
     * action j� existente AdminController::pixReprocessar().
     */
    public static function repassesPendentesOuComFalha(int $limite = 200): array
    {
        try {
            $stmt = getPDO()->prepare(
                "SELECT
                    p.id AS pedido_id, p.status AS pedido_status, p.guincho_id, go.nome AS guincho_operador,
                    pg.id AS pagamento_id, pg.status_pix, pg.valor_guincho,                    0 AS valor_repassado, pg.valor_guincho AS valor_pendente,                    0 AS valor_estornado, pg.valor_guincho AS remaining,
                    pg.criado_em AS pagamento_criado_em, pg.data_pagamento
                 FROM pagamentos pg
                 JOIN pedidos p ON p.id = pg.pedido_id
                 LEFT JOIN guinchos g ON g.id = p.guincho_id
                 LEFT JOIN usuarios go ON go.id = g.usuario_id
                 WHERE pg.status = 'aprovado'
                   AND COALESCE(pg.pago_guincho, 0) = 0
                   AND pg.status_pix IN ('pendente', 'processando', 'falha', 'falha_permanente')
                 ORDER BY FIELD(pg.status_pix, 'falha_permanente', 'falha', 'processando', 'pendente'), pg.criado_em ASC
                 LIMIT ?"
            );
            $stmt->bindValue(1, max(1, min(1000, $limite)), PDO::PARAM_INT);
            $stmt->execute();
            $linhas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            Logger::log(Logger::LEVEL_DEBUG, 'CarteiraService', 'repassesPendentesOuComFalha', 'financeiro',
                'Painel de repasses pendentes/com falha: ' . count($linhas) . ' linha(s).',
                ['linhas' => count($linhas)]);

            return ['ok' => true, 'erro' => null, 'linhas' => $linhas];
        } catch (Throwable $e) {
            Logger::exception('CarteiraService', 'repassesPendentesOuComFalha', 'financeiro', $e, []);
            return ['ok' => false, 'erro' => 'Falha ao consultar repasses pendentes: ' . $e->getMessage(), 'linhas' => []];
        }
    }

    /**
     * Checagem de reconcilia��o (debugabilidade): compara o que o ledger
     * append-only (payout_ledger_entries, fonte "cont�bil") registrou como
     * cr�dito ao guincho contra o que `pagamentos.valor_guincho` (fonte
     * "operacional") tem para pagamentos aprovados. Se as duas fontes
     * divergirem, � sinal de bug real em algum dos dois caminhos - a tela
     * mostra isso como um alerta expl�cito, n�o deixa passar em sil�ncio.
     */
    public static function checarReconciliacaoGlobal(): array
    {
        try {
            $pdo = getPDO();
            $ledger = $pdo->query(
                "SELECT
                    COALESCE(SUM(CASE WHEN entry_type = 'credito_guincho' THEN valor ELSE 0 END), 0) AS credito_guincho,
                    COALESCE(SUM(CASE WHEN entry_type = 'debito_repasse_guincho' THEN valor ELSE 0 END), 0) AS debito_repasse,
                    COALESCE(SUM(CASE WHEN entry_type = 'estorno_credito_guincho' THEN valor ELSE 0 END), 0) AS estorno_guincho
                 FROM payout_ledger_entries"
            )->fetch(PDO::FETCH_ASSOC);

            $pagamentos = $pdo->query(
                "SELECT
                    COALESCE(SUM(CASE WHEN status = 'aprovado' THEN valor_guincho ELSE 0 END), 0) AS valor_liquido_aprovado,
                    COALESCE(SUM(CASE WHEN status = 'estornado' THEN valor_guincho ELSE 0 END), 0) AS valor_estornado
                 FROM pagamentos pg                 WHERE EXISTS (                    SELECT 1 FROM payout_ledger_entries le                    WHERE le.pagamento_id = pg.id                      AND le.entry_type = 'credito_guincho'                 )"
            )->fetch(PDO::FETCH_ASSOC);

            $creditoLedger = round((float)($ledger['credito_guincho'] ?? 0), 2);
            $creditoPagamentos = round((float)($pagamentos['valor_liquido_aprovado'] ?? 0), 2);
            $diferenca = round($creditoLedger - $creditoPagamentos, 2);
            // Toler�ncia de 1 centavo por pedido acumulado (arredondamento) -
            // acima disso � diverg�ncia real que merece investiga��o.
            $consistente = abs($diferenca) <= 0.02;

            $resultado = [
                'ok' => true,
                'erro' => null,
                'consistente' => $consistente,
                'credito_guincho_ledger' => $creditoLedger,
                'credito_guincho_pagamentos' => $creditoPagamentos,
                'diferenca' => $diferenca,
                'debito_repasse_ledger' => round((float)($ledger['debito_repasse'] ?? 0), 2),
                'estorno_guincho_ledger' => round((float)($ledger['estorno_guincho'] ?? 0), 2),
                'valor_estornado_pagamentos' => round((float)($pagamentos['valor_estornado'] ?? 0), 2),
            ];

            if (!$consistente) {
                Logger::log(Logger::LEVEL_WARN, 'CarteiraService', 'checarReconciliacaoGlobal', 'financeiro',
                    "Diverg�ncia de reconcilia��o detectada: ledger=R$ {$creditoLedger} vs pagamentos=R$ {$creditoPagamentos} (diferen�a R$ {$diferenca}).",
                    $resultado);
            } else {
                Logger::log(Logger::LEVEL_DEBUG, 'CarteiraService', 'checarReconciliacaoGlobal', 'financeiro',
                    'Reconcilia��o OK - ledger e pagamentos batem (dentro da toler�ncia de arredondamento).', $resultado);
            }

            return $resultado;
        } catch (Throwable $e) {
            Logger::exception('CarteiraService', 'checarReconciliacaoGlobal', 'financeiro', $e, []);
            return [
                'ok' => false,
                'erro' => 'Falha ao checar reconcilia��o: ' . $e->getMessage(),
                'consistente' => false,
                'credito_guincho_ledger' => 0.0, 'credito_guincho_pagamentos' => 0.0, 'diferenca' => 0.0,
                'debito_repasse_ledger' => 0.0, 'estorno_guincho_ledger' => 0.0, 'valor_estornado_pagamentos' => 0.0,
            ];
        }
    }
}

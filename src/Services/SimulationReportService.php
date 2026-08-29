<?php

declare(strict_types=1);

/**
 * §LIVE-SIM-01 — Formata o relatório de uma execução de simulação.
 * Usado principalmente na saída CLI do simular_fluxo_completo.php.
 */
class SimulationReportService
{
    /** Imprime o relatório no formato legível por humanos para CLI. */
    public static function printCli(array $result): void
    {
        foreach ($result['relatorio'] as $step) {
            $status = $step['ok'] ? 'OK    ' : 'FALHOU';
            echo date('[Y-m-d H:i:s]') . " [{$status}] {$step['fase']}: {$step['msg']}\n";
        }

        $ok        = $result['ok'];
        $runId     = $result['run_id'];
        $pedidoId  = $result['pedido_id'] ?? 'N/A';
        $duracaoMs = $result['duracao_ms'] ?? 0;
        $modo      = $result['dry_run'] ? 'PIX_DRY_RUN ativo' : 'Pix real';
        $resumo    = $ok ? 'SIMULAÇÃO COMPLETA COM SUCESSO' : 'SIMULAÇÃO CONCLUÍDA COM ERRO(S)';

        echo "\n";
        echo "run_id  : {$runId}\n";
        echo "pedido  : #{$pedidoId}\n";
        echo "modo    : {$modo}\n";
        echo "duração : {$duracaoMs}ms\n";
        echo "status  : {$resumo}\n";
    }
}

<?php

declare(strict_types=1);

/**
 * �LIVE-SIM-01 - Formata o relat�rio de uma execu��o de simula��o.
 * Usado principalmente na sa�da CLI do simular_fluxo_completo.php.
 */
class SimulationReportService
{
    /** Imprime o relat�rio no formato leg�vel por humanos para CLI. */
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
        $resumo    = $ok ? 'SIMULA��O COMPLETA COM SUCESSO' : 'SIMULA��O CONCLU�DA COM ERRO(S)';

        echo "\n";
        echo "run_id  : {$runId}\n";
        echo "pedido  : #{$pedidoId}\n";
        echo "modo    : {$modo}\n";
        echo "dura��o : {$duracaoMs}ms\n";
        echo "status  : {$resumo}\n";
    }
}

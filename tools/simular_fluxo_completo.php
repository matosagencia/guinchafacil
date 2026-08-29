<?php
/**
 * GuinchaFácil — Simulador ponta-a-ponta CLI (§LIVE-SIM-01)
 *
 * Thin wrapper sobre SimulationService. Toda a lógica de negócio está em:
 *   src/Services/SimulationService.php
 *
 * Uso:
 *   php tools/simular_fluxo_completo.php
 *
 * Variáveis de ambiente:
 *   SIMULATION_ENABLED=true   — obrigatório para execução
 *   PIX_DRY_RUN=true          — não faz chamada real à API Pix
 */

declare(strict_types=1);

$isCli = PHP_SAPI === 'cli';

// Proteção web: só pode ser chamado pelo AdminController
if (!$isCli && !defined('SIMULATION_CALLER')) {
    http_response_code(403);
    die('Acesso negado. Use o painel administrativo.');
}

require_once dirname(__DIR__) . '/config.php';

if (!SIMULATION_ENABLED) {
    if ($isCli) {
        echo "[ERRO] SIMULATION_ENABLED=false no .env. Habilite para usar o simulador.\n";
    } else {
        http_response_code(403);
        echo json_encode(['erro' => 'SIMULATION_ENABLED=false. Habilite no .env.']);
    }
    exit(1);
}

require_once dirname(__DIR__) . '/src/Database.php';
require_once dirname(__DIR__) . '/src/Services/SimulationService.php';
require_once dirname(__DIR__) . '/src/Services/SimulationReportService.php';

$dryRun  = env('PIX_DRY_RUN', 'false') === 'true';
$service = new SimulationService($dryRun);
$result  = $service->run();

if ($isCli) {
    SimulationReportService::printCli($result);
} else {
    header('Content-Type: application/json');
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}

exit($result['ok'] ? 0 : 1);

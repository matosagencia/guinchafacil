<?php

declare(strict_types=1);

// Worker cron: reprocessa incidentes de especialista presos após pagamento aprovado.
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/Models/Configuracao.php';
require_once __DIR__ . '/../src/Services/Logger.php';
require_once __DIR__ . '/../src/Services/IncidenteFinanceiroService.php';
require_once __DIR__ . '/../src/Services/EspecialistaDispatchService.php';

$limit = max(1, min((int)($argv[1] ?? 100), 500));
$maxTentativas = max(1, min(20, (int)Configuracao::get('especialista_dispatch_retry_max_attempts', '3')));
$minutos = max(1, min(1440, (int)Configuracao::get('especialista_dispatch_retry_after_minutes', '10')));
$pdo = getPDO();

$corte = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'

    ? "datetime('now', '-{$minutos} minutes')"

    : "DATE_SUB(NOW(), INTERVAL {$minutos} MINUTE)";

$rows = $pdo->query(

    "SELECT i.id AS incidente_id, p.id AS pedido_id, st.code AS service_code,

            COALESCE(p.custo_final, p.custo_estimado) AS total,

            (SELECT COUNT(*)

               FROM app_logs al

              WHERE al.code = 'especialista_dispatch_retry'

                AND al.pedido_id = p.id) AS tentativas

       FROM incidentes i

       JOIN pedidos p ON p.incidente_id = i.id

       LEFT JOIN service_types st ON st.id = p.service_type_id

      WHERE i.status = 'procurando_especialista'

        AND i.criado_em < {$corte}

        AND NOT EXISTS (

            SELECT 1

              FROM atendimentos_especialista ae

             WHERE ae.incidente_id = i.id

        )

      ORDER BY i.criado_em ASC

      LIMIT {$limit}"

)->fetchAll(PDO::FETCH_ASSOC);

$processados = 0;
$sucessos = 0;
$falhas = 0;
$esgotados = 0;

foreach ($rows as $row) {

    $processados++;

    $incidenteId = (int)$row['incidente_id'];
    $pedidoId = (int)$row['pedido_id'];
    $tentativas = (int)($row['tentativas'] ?? 0);

    if ($tentativas >= $maxTentativas) {

        $esgotados++;

        continue;

    }

    $tentativaAtual = $tentativas + 1;
    $total = (float)($row['total'] ?? 0);
    $repasseEspecialista = round($total * 0.75, 2);
    $taxaEspecialista = round($total - $repasseEspecialista, 2);
    $contexto = [

        'pedido_id' => $pedidoId,
        'incidente_id' => $incidenteId,
        'tentativa' => $tentativaAtual,
        'max_tentativas' => $maxTentativas,
        'service_code' => (string)($row['service_code'] ?? ''),

    ];

    try {

        $atendimentoId = EspecialistaDispatchService::disparar(

            $incidenteId,
            (string)($row['service_code'] ?? ''),
            $repasseEspecialista,
            $total,
            $taxaEspecialista

        );

        if (!$atendimentoId) {

            throw new RuntimeException('dispatch_returned_null');

        }

        IncidenteFinanceiroService::registrar(

            $incidenteId,
            'repasse_especialista',
            'atendimento_especialista',
            (int)$atendimentoId,
            $repasseEspecialista,
            'pendente'

        );

        Logger::event([

            'level' => Logger::LEVEL_INFO,
            'class' => 'EspecialistaDispatchRetryWorker',
            'function' => 'run',
            'system' => 'ESPECIALISTA_DISPATCH',
            'phase' => 'retry',
            'code' => 'especialista_dispatch_retry',
            'message' => 'Despacho de especialista reprocessado com sucesso',
            'pedido_id' => $pedidoId,
            'context' => array_merge($contexto, [
                'resultado' => 'sucesso',
                'atendimento_id' => (int)$atendimentoId,
            ]),

        ]);

        $sucessos++;

    } catch (Throwable $e) {

        $falhas++;

        Logger::event([

            'level' => Logger::LEVEL_ERROR,
            'class' => 'EspecialistaDispatchRetryWorker',
            'function' => 'run',
            'system' => 'ESPECIALISTA_DISPATCH',
            'phase' => 'retry',
            'code' => 'especialista_dispatch_retry',
            'message' => 'Falha no reprocessamento do despacho de especialista',
            'pedido_id' => $pedidoId,
            'context' => array_merge($contexto, [
                'resultado' => 'falha',
                'erro' => $e->getMessage(),
            ]),

        ]);

        error_log('[EspecialistaDispatchRetryWorker] incidente ' . $incidenteId . ': ' . $e->getMessage());

    }
}

echo sprintf(

    "processados=%d sucessos=%d falhas=%d esgotados=%d\n",

    $processados,
    $sucessos,
    $falhas,
    $esgotados

);

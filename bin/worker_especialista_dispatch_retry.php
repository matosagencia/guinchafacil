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

$financeRows = $pdo->query(

    "SELECT i.id AS incidente_id, p.id AS pedido_id, ae.id AS atendimento_id,

            COALESCE(p.custo_final, p.custo_estimado) AS total,

            (SELECT COUNT(*)

               FROM app_logs al

              WHERE al.code = 'especialista_financeiro_repair'

                AND al.pedido_id = p.id) AS tentativas

       FROM incidentes i

       JOIN pedidos p ON p.incidente_id = i.id

       JOIN atendimentos_especialista ae ON ae.incidente_id = i.id

      WHERE i.status = 'especialista_designado'

        AND i.criado_em < {$corte}

        AND NOT EXISTS (

            SELECT 1

              FROM financeiro_lancamentos fl

             WHERE fl.incidente_id = i.id

               AND fl.tipo = 'repasse_especialista'

               AND fl.referencia_tipo = 'atendimento_especialista'

               AND fl.referencia_id = ae.id

        )

      ORDER BY i.criado_em ASC

      LIMIT {$limit}"

)->fetchAll(PDO::FETCH_ASSOC);

$financeProcessados = 0;
$financeSucessos = 0;
$financeFalhas = 0;
$financeEsgotados = 0;

foreach ($financeRows as $row) {

    $financeProcessados++;

    $incidenteId = (int)$row['incidente_id'];
    $pedidoId = (int)$row['pedido_id'];
    $atendimentoId = (int)$row['atendimento_id'];
    $tentativas = (int)($row['tentativas'] ?? 0);
    $total = (float)($row['total'] ?? 0);
    $repasseEspecialista = round($total * 0.75, 2);
    $contexto = [

        'pedido_id' => $pedidoId,
        'incidente_id' => $incidenteId,
        'atendimento_id' => $atendimentoId,
        'tentativa' => min($tentativas + 1, $maxTentativas),
        'max_tentativas' => $maxTentativas,

    ];

    if ($tentativas >= $maxTentativas) {

        $financeEsgotados++;

        try {

            IncidenteFinanceiroService::registrar(

                $incidenteId,
                'repasse_especialista',
                'atendimento_especialista',
                $atendimentoId,
                $repasseEspecialista,
                'falhou'

            );

            Logger::event([

                'level' => Logger::LEVEL_ERROR,
                'class' => 'EspecialistaDispatchRetryWorker',
                'function' => 'run',
                'system' => 'ESPECIALISTA_DISPATCH',
                'phase' => 'finance_repair',
                'code' => 'especialista_financeiro_repair_exhausted',
                'message' => 'Reparo financeiro esgotou tentativas; lançamento marcado como falhou',
                'pedido_id' => $pedidoId,
                'context' => $contexto,

            ]);

        } catch (Throwable $e) {

            Logger::event([

                'level' => Logger::LEVEL_ERROR,
                'class' => 'EspecialistaDispatchRetryWorker',
                'function' => 'run',
                'system' => 'ESPECIALISTA_DISPATCH',
                'phase' => 'finance_repair',
                'code' => 'especialista_financeiro_repair_exhausted',
                'message' => 'Não foi possível registrar lançamento financeiro falhou após esgotar tentativas',
                'pedido_id' => $pedidoId,
                'context' => array_merge($contexto, ['erro' => $e->getMessage()]),

            ]);

            error_log('[EspecialistaDispatchRetryWorker] falha ao marcar repasse como falhou no incidente ' . $incidenteId . ': ' . $e->getMessage());

        }

        continue;

    }

    try {

        IncidenteFinanceiroService::registrar(

            $incidenteId,
            'repasse_especialista',
            'atendimento_especialista',
            $atendimentoId,
            $repasseEspecialista,
            'pendente'

        );

        $financeSucessos++;

        Logger::event([

            'level' => Logger::LEVEL_INFO,
            'class' => 'EspecialistaDispatchRetryWorker',
            'function' => 'run',
            'system' => 'ESPECIALISTA_DISPATCH',
            'phase' => 'finance_repair',
            'code' => 'especialista_financeiro_repair',
            'message' => 'Repasse financeiro de especialista reparado',
            'pedido_id' => $pedidoId,
            'context' => array_merge($contexto, ['resultado' => 'sucesso']),

        ]);

    } catch (Throwable $e) {

        $financeFalhas++;

        Logger::event([

            'level' => Logger::LEVEL_ERROR,
            'class' => 'EspecialistaDispatchRetryWorker',
            'function' => 'run',
            'system' => 'ESPECIALISTA_DISPATCH',
            'phase' => 'finance_repair',
            'code' => 'especialista_financeiro_repair',
            'message' => 'Falha no reparo do repasse financeiro de especialista',
            'pedido_id' => $pedidoId,
            'context' => array_merge($contexto, [
                'resultado' => 'falha',
                'erro' => $e->getMessage(),
            ]),

        ]);

        error_log('[EspecialistaDispatchRetryWorker] falha no reparo financeiro do incidente ' . $incidenteId . ': ' . $e->getMessage());

    }
}

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

    "finance_processados=%d finance_sucessos=%d finance_falhas=%d finance_esgotados=%d processados=%d sucessos=%d falhas=%d esgotados=%d\n",

    $financeProcessados,
    $financeSucessos,
    $financeFalhas,
    $financeEsgotados,
    $processados,
    $sucessos,
    $falhas,
    $esgotados

);

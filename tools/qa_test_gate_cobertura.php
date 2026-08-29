<?php

declare(strict_types=1);

// §SEC-TOOLS-01: script de diagnóstico/seed/manutenção sem autenticação —
// não pode ser alcançável via navegador em nenhuma hipótese.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die('Acesso negado. Use o terminal.');
}

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/src/Services/CoberturaService.php';

// §COBERTURA-RAIO-01 (05/08/2026): teste de backend (sem navegador) pro gate
// de cobertura usado em ClienteController::pedidoCriar(). Complementa
// qa/suites/cobertura-timeout-estorno.spec.ts (que exercita a UI real via
// Playwright) com uma checagem rápida e determinística direto na função que
// decide o bloqueio — mesmo padrão já usado em
// tools/seed_qa_niteroi_celulas.php para validarDadosGuincho().
//
// Cenário 1: coordenada em pleno meio da Floresta Amazônica — nenhum guincho
// cadastrado no sistema (São Paulo/Niterói/RJ) chega nem perto do próprio
// raio_maximo_km global (default 50km), então DEVE bloquear
// independentemente de quantos guinchos existam hoje no banco.
// Cenário 2: mesma coordenada usada pelos seeds QA de São Paulo
// (Praça da Sé) — DEVE encontrar cobertura, desde que
// tools/prepare_p1_qa_seeds.php já tenha rodado (guincho QA aprovado nessa
// região).
//
// Uso: php tools/qa_test_gate_cobertura.php

function saida(array $dados): void
{
    echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}

try {
    // Meio da Floresta Amazônica — deliberadamente arbitrário e distante de
    // qualquer célula/cidade operada pelo GuinchaFácil hoje.
    $foraDeCobertura = CoberturaService::existeGuinchoAlcancavel(-3.4653, -62.2159, 'TOWING', null);

    // Praça da Sé, São Paulo — mesma coordenada usada pelos seeds QA
    // (prepare_p1_qa_seeds.php, prepare_cancelamento_qa_seed.php etc.).
    $dentroDeCobertura = CoberturaService::existeGuinchoAlcancavel(-23.55052, -46.63331, 'TOWING', null);

    $ok = ($foraDeCobertura === false) && ($dentroDeCobertura === true);

    saida([
        'ok' => $ok,
        'fora_de_cobertura_bloqueou' => $foraDeCobertura === false,
        'dentro_de_cobertura_liberou' => $dentroDeCobertura === true,
        'mensagem' => $ok
            ? 'Gate de cobertura funcionando: bloqueia fora do raio, libera dentro dele.'
            : 'FALHOU: ' . ($foraDeCobertura !== false
                ? 'ponto fora de qualquer cobertura real NÃO foi bloqueado (verifique se algum guincho seed tem raio_cobertura_km absurdamente grande).'
                : 'ponto dentro da cobertura QA (São Paulo) foi bloqueado — rode tools/prepare_p1_qa_seeds.php antes.'),
    ]);
    exit($ok ? 0 : 1);
} catch (Throwable $e) {
    saida(['ok' => false, 'erro' => $e->getMessage()]);
    exit(1);
}

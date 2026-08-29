<?php

declare(strict_types=1);

// §SEC-TOOLS-01: script de manutenção sem autenticação — não pode ser
// alcançável via navegador em nenhuma hipótese.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die('Acesso negado. Use o terminal.');
}

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/src/Models/Pedido.php';
require_once dirname(__DIR__) . '/src/Models/Guincho.php';
require_once dirname(__DIR__) . '/src/Models/Configuracao.php';
require_once dirname(__DIR__) . '/src/Models/Catalog/ProviderCapability.php';
require_once dirname(__DIR__) . '/src/Services/GeoService.php';
require_once dirname(__DIR__) . '/src/Services/RankingService.php';
require_once dirname(__DIR__) . '/src/Services/Dispatch/ProviderVehicleCompatibilityService.php';
require_once dirname(__DIR__) . '/src/Services/Dispatch/CompatibilityRequest.php';

/**
 * Diagnóstico de matching pedido <-> guincheiro (achado real: pedido 1539 não
 * alertava um guincho Online na área). Reproduz EXATAMENTE as mesmas
 * condições de GuinchoController::montarOfertasDisponiveis() /
 * Pedido::listarAguardandoGuincho(), uma por uma, mostrando o valor bruto do
 * banco por trás de cada PASS/FAIL — nada de "lista vazia" sem explicação.
 *
 * Uso:
 *   php tools/diagnosticar_matching_pedido.php <pedido_id> <guincho_id>
 *
 * Só leitura — não grava nada no banco.
 */

function linha(string $texto = ''): void { echo $texto . PHP_EOL; }
function status(bool $ok, string $label, string $detalhe = ''): void
{
    $tag = $ok ? '[ OK  ]' : '[FALHA]';
    linha("{$tag} {$label}" . ($detalhe !== '' ? " — {$detalhe}" : ''));
}

$pedidoId  = isset($argv[1]) ? (int)$argv[1] : 0;
$guinchoId = isset($argv[2]) ? (int)$argv[2] : 0;

if ($pedidoId <= 0 || $guinchoId <= 0) {
    linha('Uso: php tools/diagnosticar_matching_pedido.php <pedido_id> <guincho_id>');
    linha('');
    linha('Não sabe o guincho_id? Rode primeiro:');
    linha("  php -r \"require 'config.php'; foreach (getPDO()->query(\\\"SELECT g.id, u.nome, g.disponivel, g.aprovado FROM guinchos g JOIN usuarios u ON u.id=g.usuario_id\\\") as \\\$r) print_r(\\\$r);\"");
    exit(1);
}

linha('==============================================================');
linha(" Diagnóstico de matching — pedido #{$pedidoId} x guincho #{$guinchoId}");
linha('==============================================================');
linha();

$pdo = getPDO();

// ── Carrega pedido e guincho ────────────────────────────────────────
$pedido = Pedido::buscarPorId($pedidoId);
if (!$pedido) {
    status(false, "Pedido #{$pedidoId} existe no banco");
    linha();
    linha('Não dá pra diagnosticar mais nada sem o pedido. Confira o ID.');
    exit(1);
}
status(true, "Pedido #{$pedidoId} encontrado", "status atual = '{$pedido['status']}'");

$guincho = Guincho::buscarPorId($guinchoId);
if (!$guincho) {
    status(false, "Guincho #{$guinchoId} existe no banco");
    exit(1);
}
status(true, "Guincho #{$guinchoId} encontrado", "operador = " . ($guincho['nome_operador'] ?? '?'));
linha();

// ── Condição 1: status do pedido ────────────────────────────────────
linha('--- 1. Status do pedido ---');
$statusOk = ($pedido['status'] === 'aguardando_guincho');
status($statusOk, "pedidos.status = 'aguardando_guincho'", "valor real: '{$pedido['status']}'");
if (!$statusOk) {
    linha('       -> Se ainda está aguardando_pagamento: o webhook do gateway não confirmou');
    linha('          (ou não chegou). Pedido NUNCA aparece pra ninguém neste estado.');
    if (in_array($pedido['status'], ['a_caminho', 'no_local', 'em_reboque'], true)) {
        linha('       -> Já foi aceito por outro guincho (guincho_id=' . ($pedido['guincho_id'] ?? '?') . ').');
    }
    if ($pedido['status'] === 'cancelado') {
        linha('       -> Pedido foi cancelado.');
    }
}

// ── Condição 2: expiração do aceite ─────────────────────────────────
linha();
linha('--- 2. Expiração do aceite ---');
$expiracao = (string)($pedido['expiracao_aceite'] ?? '');
$expiracaoOk = $expiracao !== '' && strtotime($expiracao) > time();
status($expiracaoOk, 'expiracao_aceite > NOW()', "expiracao_aceite='{$expiracao}' | agora='" . date('Y-m-d H:i:s') . "'");
if (!$expiracaoOk && $statusOk) {
    linha('       -> ACHADO: pedido está em aguardando_guincho mas EXPIRADO.');
    linha('          Não existe job/cron que renove expiracao_aceite automaticamente');
    linha('          (só é reescrito em transições explícitas). Fica invisível pra todo mundo,');
    linha('          silenciosamente, até um humano perceber o pedido parado.');
}

// ── Condição 3 e 4: disponibilidade/aprovação do guincho ────────────
linha();
linha('--- 3. Guincho disponível e aprovado ---');
$disponivelOk = (int)($guincho['disponivel'] ?? 0) === 1;
$aprovadoOk   = (int)($guincho['aprovado'] ?? 0) === 1;
status($disponivelOk, 'guinchos.disponivel = 1 (toggle Online)', 'valor real: ' . (int)($guincho['disponivel'] ?? -1));
status($aprovadoOk, 'guinchos.aprovado = 1', 'valor real: ' . (int)($guincho['aprovado'] ?? -1));

// ── Condição 5: reboque_aprovado / capacidade de serviço ────────────
linha();
linha('--- 4. Capacidade para o tipo de atendimento ---');
$attendanceMode = (string)($pedido['attendance_mode'] ?? 'TOWING');
linha("       pedido.attendance_mode = '{$attendanceMode}'");
$capacidadeOk = true;
if ($attendanceMode === 'TOWING') {
    $reboqueAprovado = (int)($guincho['reboque_aprovado'] ?? 1);
    $capacidadeOk = $reboqueAprovado === 1;
    status($capacidadeOk, 'guincho.reboque_aprovado = 1 (exigido p/ TOWING)', "valor real: {$reboqueAprovado}");
} else {
    $serviceTypeId = (int)($pedido['service_type_id'] ?? 0);
    $temCapacidade = $serviceTypeId > 0 && ProviderCapability::possuiCapacidadeAprovada($guinchoId, $serviceTypeId);
    $capacidadeOk = $temCapacidade;
    status($capacidadeOk, "ProviderCapability::possuiCapacidadeAprovada(guincho={$guinchoId}, service_type_id={$serviceTypeId})");
    if (!$capacidadeOk) {
        linha('       -> Prestador não tem capacidade aprovada pra este tipo de serviço,');
        linha('          ou o pedido não tem service_type_id definido.');
    }
}

// ── Condição 6: compatibilidade de veículo ──────────────────────────
linha();
linha('--- 5. Compatibilidade prestador x veículo ---');
$serviceTypeIdCmp = (int)($pedido['service_type_id'] ?? 0);
$compatOk = true;
if ($serviceTypeIdCmp > 0) {
    try {
        $compat = ProviderVehicleCompatibilityService::evaluate(new CompatibilityRequest(
            $pedidoId, $guinchoId, $serviceTypeIdCmp, CompatibilityRequest::OP_QUEUE_FILTER
        ));
        $compatOk = $compat->allowsOffer();
        status($compatOk, 'ProviderVehicleCompatibilityService::evaluate()->allowsOffer()', 'status=' . $compat->getStatus());
        if ($compat->getWarnings()) {
            linha('       avisos: ' . implode('; ', $compat->getWarnings()));
        }
    } catch (Throwable $e) {
        status(false, 'ProviderVehicleCompatibilityService::evaluate() lançou exceção', $e->getMessage());
        $compatOk = false;
    }
} else {
    linha('       (pulado — pedido sem service_type_id, considerado reboque puro)');
}

// ── Condição 7: distância x raio ────────────────────────────────────
linha();
linha('--- 6. Distância x raio de busca ---');
$latRaw = $guincho['lat_atual'] ?? ($guincho['lat_operacao'] ?? null);
$lngRaw = $guincho['lng_atual'] ?? ($guincho['lng_operacao'] ?? null);
$lat = is_numeric($latRaw) ? (float)$latRaw : null;
$lng = is_numeric($lngRaw) ? (float)$lngRaw : null;
$semLocalizacao = ($lat === null || $lng === null || ((float)$lat === 0.0 && (float)$lng === 0.0));

linha("       guincho.lat_atual/lng_atual = " . var_export($guincho['lat_atual'] ?? null, true) . ' / ' . var_export($guincho['lng_atual'] ?? null, true));
linha("       guincho.lat_operacao/lng_operacao (fallback cadastro) = " . var_export($guincho['lat_operacao'] ?? null, true) . ' / ' . var_export($guincho['lng_operacao'] ?? null, true));
linha('       ATENÇÃO: não existe coluna de timestamp pra saber há quanto tempo essa');
linha('       posição foi atualizada. O dashboard NÃO envia GPS pro servidor enquanto');
linha('       o guincho só está Online esperando oferta — só envia durante atendimento');
linha('       ativo (tela atendimento.php). Se esse guincho não tem corrida ativa há');
linha('       tempo, lat_atual provavelmente é de UM SERVIÇO ANTIGO ou do cadastro.');
linha();

if ($semLocalizacao) {
    status(true, 'Sem localização válida -> filtro de distância É IGNORADO (todos passam)', 'lat/lng nulos ou (0,0)');
} else {
    $cfg  = Configuracao::getAll();
    $raioGlobal = (int)($cfg['raio_maximo_km'] ?? 50);
    $raioCoberturaGuincho = $guincho['raio_cobertura_km'] ?? null;
    $latPedido = (float)($pedido['lat_origem'] ?? 0);
    $lngPedido = (float)($pedido['lng_origem'] ?? 0);
    $distancia = GeoService::haversine($lat, $lng, $latPedido, $lngPedido);

    linha("       pedido.lat_origem/lng_origem = {$latPedido} / {$lngPedido}");
    linha('       raio_maximo_km (config GLOBAL do admin, é o que REALMENTE filtra) = ' . $raioGlobal . ' km');
    linha('       guinchos.raio_cobertura_km (o "Área de cobertura" mostrado no dashboard,');
    linha('       cosmético, NÃO é lido em nenhuma query de matching) = ' . var_export($raioCoberturaGuincho, true) . ' km');
    linha('       distância calculada (haversine) = ' . round($distancia, 2) . ' km');

    $distanciaOk = $distancia <= $raioGlobal;
    status($distanciaOk, "distância <= raio_maximo_km global ({$raioGlobal} km)", round($distancia, 2) . ' km');

    if ($distanciaOk && $raioCoberturaGuincho !== null && $distancia > (float)$raioCoberturaGuincho) {
        linha('       -> Nota: a distância JÁ ULTRAPASSA a "Área de cobertura" que o guincho');
        linha('          configurou (' . $raioCoberturaGuincho . ' km), mas isso não é aplicado —');
        linha('          o pedido passa mesmo assim porque só o raio GLOBAL é checado.');
    }

    // ── Condição 8: score mínimo ─────────────────────────────────────
    linha();
    linha('--- 7. Score mínimo ---');
    $score = RankingService::calcularScore($distancia, (float)($guincho['reputacao'] ?? 0));
    $scoreMinimo = isset($pedido['score_minimo_atual']) ? (float)$pedido['score_minimo_atual'] : null;
    if ($scoreMinimo !== null) {
        $scoreOk = $score >= $scoreMinimo;
        status($scoreOk, 'score calculado >= score_minimo_atual do pedido', round($score, 4) . ' >= ' . $scoreMinimo . '?');
    } else {
        linha('       (pedido sem score_minimo_atual definido — condição não se aplica)');
    }
}

// ── Guincho já tem pedido ativo? (dashboard oculta a fila inteira) ──
linha();
linha('--- 8. Guincho já tem atendimento ativo? ---');
$stmt = $pdo->prepare(
    "SELECT id, status FROM pedidos WHERE guincho_id = ? AND status IN ('a_caminho','no_local','em_reboque') ORDER BY criado_em DESC LIMIT 1"
);
$stmt->execute([$guinchoId]);
$ativo = $stmt->fetch(PDO::FETCH_ASSOC);
if ($ativo) {
    status(false, 'Guincho está livre pra ofertas', "tem pedido ativo #{$ativo['id']} (status={$ativo['status']})");
    linha('       -> Enquanto isso, o dashboard NEM CHAMA montarOfertasDisponiveis() —');
    linha('          a fila de ofertas fica vazia de propósito.');
} else {
    status(true, 'Guincho está livre pra ofertas (sem pedido ativo)');
}

linha();
linha('==============================================================');
linha(' Resumo');
linha('==============================================================');
$condicoes = [
    'status aguardando_guincho' => $statusOk,
    'expiracao_aceite válida'   => $expiracaoOk,
    'disponivel=1'              => $disponivelOk,
    'aprovado=1'                => $aprovadoOk,
    'capacidade/reboque'        => $capacidadeOk,
    'compatibilidade veicular'  => $compatOk,
];
$falhas = array_filter($condicoes, fn($ok) => !$ok);
if (!$falhas) {
    linha('Todas as condições verificadas acima PASSARAM. Se mesmo assim o guincho não');
    linha('viu a oferta, o próximo suspeito é a distância/localização (seção 6) — rode');
    linha('de novo após o guincho atualizar o GPS em um atendimento ativo, ou confirme');
    linha('manualmente lat_atual/lng_atual no banco.');
} else {
    linha('Condições que REPROVARAM:');
    foreach ($falhas as $nome => $_) {
        linha("  - {$nome}");
    }
}
linha();

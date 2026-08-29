<?php

declare(strict_types=1);

// §SEC-TOOLS-01: script de manutenção sem autenticação — não pode ser
// alcançável via navegador em nenhuma hipótese.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die('Acesso negado. Use o terminal.');
}

// §TESTE-TARIFACAO-01 (02/08/2026): mesma bateria de coerência de
// tools/teste_tarifacao_niteroi.php, mas para a tarifa GLOBAL (cidade_id =
// null) — o preço que qualquer cidade SEM override própria realmente cobra
// hoje. Não grava nada no banco — é só leitura/cálculo direto no motor real
// (TarifaService e ServicePricingRule::calcularTotal).
//
// Grava:
//   - logs/tarifacao_global/reboque_<run>.csv
//   - logs/tarifacao_global/servicos_<run>.csv
//   - logs/tarifacao_global/alertas_<run>.txt
//   - logs/tarifacao_global/resumo_<run>.txt

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/src/Models/Feriado.php';
require_once dirname(__DIR__) . '/src/Models/Catalog/ServiceType.php';
require_once dirname(__DIR__) . '/src/Models/Catalog/ServicePricingRule.php';
require_once dirname(__DIR__) . '/src/Services/TarifaService.php';

// ─── Passo 1 — Setup: diretório de saída, cenários de tempo ─────────────────

$runId = date('Y-m-d_His');
$outDir = LOG_DIR . '/tarifacao_global';
if (!is_dir($outDir)) {
    mkdir($outDir, 0775, true);
}
$csvReboquePath = $outDir . "/reboque_{$runId}.csv";
$csvServicosPath = $outDir . "/servicos_{$runId}.csv";
$alertasPath = $outDir . "/alertas_{$runId}.txt";
$resumoPath = $outDir . "/resumo_{$runId}.txt";

// Feriado ativo real (recorrente_anual) — mesmo critério do teste de
// Niterói: se não houver nenhum cadastrado, os cenários de feriado são
// pulados.
$feriadoUsado = null;
foreach (Feriado::listarAtivos() as $f) {
    if (!empty($f['recorrente_anual'])) {
        $feriadoUsado = $f;
        break;
    }
}
if ($feriadoUsado === null) {
    $todos = Feriado::listarAtivos();
    $feriadoUsado = $todos[0] ?? null;
}

$hoje = new DateTimeImmutable('today');
$cenarios = [
    'diurno' => $hoje->setTime(14, 0),
    'noturno' => $hoje->setTime(22, 0),
];
if ($feriadoUsado !== null) {
    $mesDia = substr((string)$feriadoUsado['data'], 5, 5); // MM-DD
    $anoAtual = (int)$hoje->format('Y');
    $dataFeriado = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', "{$anoAtual}-{$mesDia} 00:00:00");
    if ($dataFeriado !== false) {
        $cenarios['feriado_diurno'] = $dataFeriado->setTime(14, 0);
        $cenarios['feriado_noturno'] = $dataFeriado->setTime(22, 0);
    }
} else {
    echo "[AVISO] Nenhum feriado ativo cadastrado em /admin/feriados — cenários de feriado serão PULADOS.\n";
}

echo "Testando tarifa GLOBAL (cidade_id = null).\n";
echo "Cenários testados: " . implode(', ', array_keys($cenarios)) . "\n";
if ($feriadoUsado !== null) {
    echo "Feriado usado como referência: {$feriadoUsado['nome']} ({$feriadoUsado['data']})\n";
}

// ─── Passo 2 — Reboque: todas as categorias x distâncias x cenários ────────

$categorias = ['popular', 'suv', 'caminhonete', 'eletrico', 'moto'];
$distanciasReboque = [3.0, 10.0, 20.0, 35.0];

$resultadosReboque = [];
foreach ($categorias as $categoria) {
    foreach ($distanciasReboque as $distancia) {
        foreach ($cenarios as $cenarioNome => $dataHora) {
            $detalhe = TarifaService::calcularDetalhado($distancia, $categoria, false, $dataHora, null);
            $resultadosReboque[] = [
                'categoria' => $categoria,
                'distancia_km' => $distancia,
                'cenario' => $cenarioNome,
                'valor' => $detalhe['valor'],
                'tarifa_km_aplicada' => $detalhe['tarifa_km_aplicada'],
                'taxa_fixa_aplicada' => $detalhe['taxa_fixa_aplicada'],
                'is_noturno' => $detalhe['is_noturno'] ? '1' : '0',
                'is_feriado' => $detalhe['is_feriado'] ? '1' : '0',
                'taxa_prioridade' => null,
                'diferenca_vs_sem_prioridade' => null,
            ];
        }
        // Checagem extra: taxa de prioridade.
        $base = TarifaService::calcularDetalhado($distancia, $categoria, false, $cenarios['diurno'], null);
        $comPrioridade = TarifaService::calcularDetalhado($distancia, $categoria, true, $cenarios['diurno'], null);
        $resultadosReboque[] = [
            'categoria' => $categoria,
            'distancia_km' => $distancia,
            'cenario' => 'diurno_prioridade',
            'valor' => $comPrioridade['valor'],
            'tarifa_km_aplicada' => $comPrioridade['tarifa_km_aplicada'],
            'taxa_fixa_aplicada' => $comPrioridade['taxa_fixa_aplicada'],
            'is_noturno' => '0',
            'is_feriado' => '0',
            'taxa_prioridade' => $comPrioridade['taxa_prioridade'],
            'diferenca_vs_sem_prioridade' => round($comPrioridade['valor'] - $base['valor'], 2),
        ];
    }
}

// ─── Passo 3 — Serviços avulsos (catálogo ON_SITE/HYBRID) x distâncias x cenários ─

$tipos = array_filter(ServiceType::listarAtivos(), static fn($t) => ($t['attendance_mode'] ?? '') !== 'TOWING');
$distanciasServico = [3.0, 10.0];

$resultadosServicos = [];
foreach ($tipos as $tipo) {
    $tipoId = (int)$tipo['id'];
    foreach ($distanciasServico as $distancia) {
        foreach ($cenarios as $cenarioNome => $dataHora) {
            $regra = ServicePricingRule::calcularTotal($tipoId, $distancia, $dataHora, null);
            $resultadosServicos[] = [
                'service_code' => $tipo['code'],
                'service_name' => $tipo['name'],
                'distancia_km' => $distancia,
                'cenario' => $cenarioNome,
                'valor' => $regra['valor'] ?? null,
                'noturno' => isset($regra['detalhe']['noturno']) ? ($regra['detalhe']['noturno'] ? '1' : '0') : '?',
                'feriado' => isset($regra['detalhe']['feriado']) ? ($regra['detalhe']['feriado'] ? '1' : '0') : '?',
                'minimo' => $regra['detalhe']['minimo'] ?? null,
                'sem_regra_ativa' => $regra === null ? '1' : '0',
            ];
        }
    }
}

// ─── Passo 4 — Gravação dos CSVs ────────────────────────────────────────────

function gravarCsv(string $path, array $linhas): void
{
    if (empty($linhas)) {
        return;
    }
    $fh = fopen($path, 'w');
    fputcsv($fh, array_keys($linhas[0]));
    foreach ($linhas as $linha) {
        fputcsv($fh, array_map(static fn($v) => $v === null ? '' : $v, $linha));
    }
    fclose($fh);
}

gravarCsv($csvReboquePath, $resultadosReboque);
gravarCsv($csvServicosPath, $resultadosServicos);

// ─── Passo 5 — Checagens de coerência ───────────────────────────────────────

$alertas = [];
$infos = [];

// 5.1 Reboque: diurno < noturno < feriado_noturno, diurno < feriado_diurno < feriado_noturno
$porGrupo = [];
foreach ($resultadosReboque as $r) {
    if (!in_array($r['cenario'], ['diurno', 'noturno', 'feriado_diurno', 'feriado_noturno'], true)) {
        continue;
    }
    $chave = $r['categoria'] . '|' . $r['distancia_km'];
    $porGrupo[$chave][$r['cenario']] = (float)$r['valor'];
}
foreach ($porGrupo as $chave => $valores) {
    [$categoria, $distancia] = explode('|', $chave);
    if (isset($valores['diurno'], $valores['noturno']) && $valores['noturno'] <= $valores['diurno']) {
        $alertas[] = "[REBOQUE] {$categoria} @ {$distancia}km: noturno (R$ {$valores['noturno']}) não é maior que diurno (R$ {$valores['diurno']}).";
    }
    if (isset($valores['diurno'], $valores['feriado_diurno']) && $valores['feriado_diurno'] <= $valores['diurno']) {
        $alertas[] = "[REBOQUE] {$categoria} @ {$distancia}km: feriado diurno (R$ {$valores['feriado_diurno']}) não é maior que diurno normal (R$ {$valores['diurno']}).";
    }
    if (isset($valores['noturno'], $valores['feriado_noturno']) && $valores['feriado_noturno'] <= $valores['noturno']) {
        $alertas[] = "[REBOQUE] {$categoria} @ {$distancia}km: feriado+noturno (R$ {$valores['feriado_noturno']}) deveria empilhar e ser maior que só noturno (R$ {$valores['noturno']}).";
    }
    if (isset($valores['feriado_diurno'], $valores['feriado_noturno']) && $valores['feriado_noturno'] <= $valores['feriado_diurno']) {
        $alertas[] = "[REBOQUE] {$categoria} @ {$distancia}km: feriado+noturno (R$ {$valores['feriado_noturno']}) deveria empilhar e ser maior que só feriado diurno (R$ {$valores['feriado_diurno']}).";
    }
}

// 5.2 Reboque: SUV/caminhonete/elétrico não deveriam cobrar MENOS que
// popular; moto (5.2b) é o oposto — deveria cobrar MENOS.
$porCenarioDistancia = [];
foreach ($resultadosReboque as $r) {
    if (!in_array($r['cenario'], ['diurno', 'noturno', 'feriado_diurno', 'feriado_noturno'], true)) {
        continue;
    }
    $chave = $r['cenario'] . '|' . $r['distancia_km'];
    $porCenarioDistancia[$chave][$r['categoria']] = (float)$r['valor'];
}
foreach ($porCenarioDistancia as $chave => $valores) {
    [$cenario, $distancia] = explode('|', $chave);
    $popular = $valores['popular'] ?? null;
    if ($popular === null) {
        continue;
    }
    foreach (['suv', 'caminhonete', 'eletrico'] as $outraCategoria) {
        if (isset($valores[$outraCategoria]) && $valores[$outraCategoria] < $popular) {
            $alertas[] = "[REBOQUE] {$cenario} @ {$distancia}km: categoria '{$outraCategoria}' (R$ {$valores[$outraCategoria]}) cobra MENOS que 'popular' (R$ {$popular}) — provável tarifa de categoria desatualizada em relação à tarifa base.";
        } elseif (isset($valores[$outraCategoria]) && $valores[$outraCategoria] == $popular) {
            $infos[] = "[REBOQUE] {$cenario} @ {$distancia}km: categoria '{$outraCategoria}' cobra IGUAL a 'popular' (R$ {$popular}) — provavelmente sem tarifa própria configurada, herdando o valor base.";
        }
    }
    if (isset($valores['moto'])) {
        if ($valores['moto'] > $popular) {
            $alertas[] = "[REBOQUE] {$cenario} @ {$distancia}km: categoria 'moto' (R$ {$valores['moto']}) cobra MAIS que 'popular' (R$ {$popular}) — esperado é moto custar menos.";
        } elseif ($valores['moto'] == $popular) {
            $infos[] = "[REBOQUE] {$cenario} @ {$distancia}km: categoria 'moto' cobra IGUAL a 'popular' (R$ {$popular}) — provavelmente sem tarifa própria configurada, herdando o valor base.";
        }
    }
}

// 5.3 Reboque: preço deve crescer (ou no mínimo não cair) com a distância.
$porCategoriaCenario = [];
foreach ($resultadosReboque as $r) {
    if (!in_array($r['cenario'], ['diurno', 'noturno', 'feriado_diurno', 'feriado_noturno'], true)) {
        continue;
    }
    $chave = $r['categoria'] . '|' . $r['cenario'];
    $porCategoriaCenario[$chave][] = ['distancia' => (float)$r['distancia_km'], 'valor' => (float)$r['valor']];
}
foreach ($porCategoriaCenario as $chave => $pontos) {
    usort($pontos, static fn($a, $b) => $a['distancia'] <=> $b['distancia']);
    for ($i = 1; $i < count($pontos); $i++) {
        if ($pontos[$i]['valor'] < $pontos[$i - 1]['valor']) {
            [$categoria, $cenario] = explode('|', $chave);
            $alertas[] = "[REBOQUE] {$categoria}/{$cenario}: preço caiu de R$ {$pontos[$i-1]['valor']} ({$pontos[$i-1]['distancia']}km) para R$ {$pontos[$i]['valor']} ({$pontos[$i]['distancia']}km) — distância maior custando menos.";
        }
    }
}

// 5.4 Reboque: prioridade deve somar exatamente taxa_prioridade.
foreach ($resultadosReboque as $r) {
    if ($r['cenario'] === 'diurno_prioridade' && isset($r['diferenca_vs_sem_prioridade'])) {
        if ((float)$r['diferenca_vs_sem_prioridade'] <= 0) {
            $alertas[] = "[REBOQUE] {$r['categoria']} @ {$r['distancia_km']}km: taxa de prioridade não aumentou o valor (diferença = R$ {$r['diferenca_vs_sem_prioridade']}).";
        }
    }
}

// 5.5 Reboque: nenhum valor pode ser zero ou negativo.
foreach ($resultadosReboque as $r) {
    if ((float)$r['valor'] <= 0) {
        $alertas[] = "[REBOQUE] {$r['categoria']} @ {$r['distancia_km']}km/{$r['cenario']}: valor calculado é zero ou negativo (R$ {$r['valor']}).";
    }
}

// 5.6 Serviços avulsos: noturno/feriado por tipo+distância.
$porGrupoServico = [];
foreach ($resultadosServicos as $r) {
    if ($r['valor'] === null || !in_array($r['cenario'], ['diurno', 'noturno', 'feriado_diurno', 'feriado_noturno'], true)) {
        continue;
    }
    $chave = $r['service_code'] . '|' . $r['distancia_km'];
    $porGrupoServico[$chave][$r['cenario']] = (float)$r['valor'];
}
foreach ($porGrupoServico as $chave => $valores) {
    [$code, $distancia] = explode('|', $chave);
    if (isset($valores['diurno'], $valores['noturno']) && $valores['noturno'] <= $valores['diurno']) {
        $alertas[] = "[SERVIÇO {$code}] @ {$distancia}km: noturno (R$ {$valores['noturno']}) não é maior que diurno (R$ {$valores['diurno']}).";
    }
    if (isset($valores['diurno'], $valores['feriado_diurno']) && $valores['feriado_diurno'] <= $valores['diurno']) {
        $alertas[] = "[SERVIÇO {$code}] @ {$distancia}km: feriado diurno (R$ {$valores['feriado_diurno']}) não é maior que diurno normal (R$ {$valores['diurno']}).";
    }
    if (isset($valores['noturno'], $valores['feriado_noturno']) && $valores['feriado_noturno'] <= $valores['noturno']) {
        $alertas[] = "[SERVIÇO {$code}] @ {$distancia}km: feriado+noturno (R$ {$valores['feriado_noturno']}) deveria ser maior que só noturno (R$ {$valores['noturno']}).";
    }
}

// 5.7 Serviços avulsos: mínimo, zero/negativo, sem regra ativa.
foreach ($resultadosServicos as $r) {
    if ($r['sem_regra_ativa'] === '1') {
        $alertas[] = "[SERVIÇO {$r['service_code']}] Sem regra de tarifa ATIVA encontrada (nem global) — pedido desse tipo não teria preço calculável.";
        continue;
    }
    if ($r['valor'] === null) {
        continue;
    }
    if ((float)$r['valor'] <= 0) {
        $alertas[] = "[SERVIÇO {$r['service_code']}] @ {$r['distancia_km']}km/{$r['cenario']}: valor calculado é zero ou negativo (R$ {$r['valor']}).";
    }
    if ($r['minimo'] !== null && (float)$r['valor'] < (float)$r['minimo']) {
        $alertas[] = "[SERVIÇO {$r['service_code']}] @ {$r['distancia_km']}km/{$r['cenario']}: valor (R$ {$r['valor']}) abaixo do mínimo configurado (R$ {$r['minimo']}).";
    }
}

// 5.8 Serviços avulsos: preço deve crescer (ou no mínimo não cair) com a distância.
$porServicoCenario = [];
foreach ($resultadosServicos as $r) {
    if ($r['valor'] === null || !in_array($r['cenario'], ['diurno', 'noturno', 'feriado_diurno', 'feriado_noturno'], true)) {
        continue;
    }
    $chave = $r['service_code'] . '|' . $r['cenario'];
    $porServicoCenario[$chave][] = ['distancia' => (float)$r['distancia_km'], 'valor' => (float)$r['valor']];
}
foreach ($porServicoCenario as $chave => $pontos) {
    usort($pontos, static fn($a, $b) => $a['distancia'] <=> $b['distancia']);
    for ($i = 1; $i < count($pontos); $i++) {
        if ($pontos[$i]['valor'] < $pontos[$i - 1]['valor']) {
            [$code, $cenario] = explode('|', $chave);
            $alertas[] = "[SERVIÇO {$code}/{$cenario}] preço caiu de R$ {$pontos[$i-1]['valor']} ({$pontos[$i-1]['distancia']}km) para R$ {$pontos[$i]['valor']} ({$pontos[$i]['distancia']}km).";
        }
    }
}

// ─── Passo 6 — Gravação dos relatórios de texto ────────────────────────────

$fh = fopen($alertasPath, 'w');
fwrite($fh, "RELATÓRIO DE COERÊNCIA DE TARIFAÇÃO — GLOBAL (sem cidade)\n");
fwrite($fh, "Gerado em: " . date('Y-m-d H:i:s') . "\n");
fwrite($fh, "Total de alertas: " . count($alertas) . "\n\n");
if (empty($alertas)) {
    fwrite($fh, "Nenhuma incoerência encontrada nas checagens automáticas.\n\n");
} else {
    foreach ($alertas as $i => $a) {
        fwrite($fh, ($i + 1) . ". {$a}\n");
    }
    fwrite($fh, "\n");
}
fwrite($fh, "OBSERVAÇÕES (não são necessariamente bugs):\n");
foreach ($infos as $i => $info) {
    fwrite($fh, ($i + 1) . ". {$info}\n");
}
fclose($fh);

$fh = fopen($resumoPath, 'w');
fwrite($fh, "RESUMO DO TESTE DE TARIFAÇÃO — GLOBAL (run {$runId})\n\n");
fwrite($fh, "Combinações de reboque testadas: " . count($resultadosReboque) . "\n");
fwrite($fh, "Combinações de serviço avulso testadas: " . count($resultadosServicos) . "\n");
fwrite($fh, "Cenários de tempo: " . implode(', ', array_keys($cenarios)) . "\n");
fwrite($fh, "Feriado de referência: " . ($feriadoUsado !== null ? "{$feriadoUsado['nome']} ({$feriadoUsado['data']})" : 'NENHUM (cenários de feriado pulados)') . "\n");
fwrite($fh, "Alertas de incoerência: " . count($alertas) . "\n");
fwrite($fh, "Observações informativas: " . count($infos) . "\n\n");
fwrite($fh, "Arquivos gerados:\n");
fwrite($fh, "  - " . basename($csvReboquePath) . "\n");
fwrite($fh, "  - " . basename($csvServicosPath) . "\n");
fwrite($fh, "  - " . basename($alertasPath) . "\n");
fclose($fh);

// ─── Passo 7 — Saída no terminal ───────────────────────────────────────────

echo "\n" . count($resultadosReboque) . " combinações de reboque testadas.\n";
echo count($resultadosServicos) . " combinações de serviço avulso testadas.\n";
echo count($alertas) . " alerta(s) de incoerência encontrado(s).\n";
echo count($infos) . " observação(ões) informativa(s).\n\n";
echo "Arquivos gravados em: {$outDir}\n";
echo "  - " . basename($csvReboquePath) . "\n";
echo "  - " . basename($csvServicosPath) . "\n";
echo "  - " . basename($alertasPath) . "\n";
echo "  - " . basename($resumoPath) . "\n";

if (!empty($alertas)) {
    echo "\n[ATENÇÃO] Alertas encontrados — veja " . basename($alertasPath) . " para detalhes.\n";
}

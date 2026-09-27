<?php
// install-diagnostico.php — raio-X completo do fluxo de pré-cotação
// APAGUE DEPOIS DE USAR

require_once __DIR__ . '/config.php';

$report = ['OK' => [], 'AVISO' => [], 'ERRO' => [], 'INFO' => []];

function secao(&$report, $titulo) { $report['INFO'][] = ""; $report['INFO'][] = "═══ $titulo ═══"; }
function ok(&$report, $msg) { $report['OK'][] = "✅ $msg"; }
function aviso(&$report, $msg) { $report['AVISO'][] = "⚠️  $msg"; }
function erro(&$report, $msg) { $report['ERRO'][] = "❌ $msg"; }
function info(&$report, $msg) { $report['INFO'][] = "ℹ️  $msg"; }

// ═════════════════════════════════════════════════════════════════════
// 1. ARQUIVOS CRÍTICOS
// ═════════════════════════════════════════════════════════════════════
secao($report, '1. ARQUIVOS CRÍTICOS');

$arquivos = [
    'src/Views/public/pre-cotacao.php',
    'src/Views/cliente/_precotacao_extras.php',
    'src/Views/cliente/_copy_precotacao.php',
    'public/assets/js/public-pre-cotacao.js',
    'public/assets/js/public-pre-cotacao-form.js',
    'public/assets/js/public-pre-cotacao-sintomas.js',
    'public/assets/css/components/precotacao-trust.css',
    'public/assets/css/pages/public-pre-cotacao.css',
    'src/Services/DecisaoAtendimentoService.php',
    'src/Services/GeoService.php',
    'src/Services/PricingService.php',
    'src/Models/OficinaOperador.php',
    'src/Controllers/PedidoController.php',
    'src/Controllers/AuthController.php',
];

foreach ($arquivos as $f) {
    $full = __DIR__ . '/' . $f;
    if (!file_exists($full)) {
        erro($report, "AUSENTE: $f");
        continue;
    }
    $size = filesize($full);
    if ($size === 0) {
        erro($report, "VAZIO: $f");
        continue;
    }
    ok($report, "$f ($size bytes)");

    // Verifica BOM / encoding
    if (substr($f, -4) === '.php') {
        $bytes = file_get_contents($full, false, null, 0, 8);
        $hex = '';
        for ($i = 0; $i < min(6, strlen($bytes)); $i++) {
            $hex .= strtoupper(str_pad(dechex(ord($bytes[$i])), 2, '0', STR_PAD_LEFT)) . ' ';
        }
        if (substr($f, -16) === '_precotacao_extras.php' || substr($f, -16) === '_copy_precotacao.php') {
            if (strpos($hex, '3C 3F 70') === 0) {
                ok($report, "  ↳ Encoding OK: $hex");
            } else {
                erro($report, "  ↳ Encoding RUIM: $hex (esperado 3C 3F 70 = <?php)");
            }
        }
    }
}

// ═════════════════════════════════════════════════════════════════════
// 2. SINTAXE PHP
// ═════════════════════════════════════════════════════════════════════
secao($report, '2. SINTAXE PHP (php -l)');

$phpFiles = [
    'src/Services/DecisaoAtendimentoService.php',
    'src/Views/public/pre-cotacao.php',
    'src/Views/cliente/_precotacao_extras.php',
    'src/Views/cliente/_copy_precotacao.php',
];

$phpBin = 'C:\\xampp\\php\\php.exe';
foreach ($phpFiles as $f) {
    $full = __DIR__ . '/' . $f;
    if (!file_exists($full)) continue;
    $out = shell_exec(escapeshellarg($phpBin) . ' -l ' . escapeshellarg($full) . ' 2>&1');
    if ($out && strpos($out, 'No syntax errors') !== false) {
        ok($report, "Sintaxe OK: $f");
    } else {
        erro($report, "SINTAXE RUIM: $f");
        info($report, "  ↳ " . trim((string)$out));
    }
}

// ═════════════════════════════════════════════════════════════════════
// 3. DECISAO SERVICE — verificar constantes e métodos
// ═════════════════════════════════════════════════════════════════════
secao($report, '3. DecisaoAtendimentoService (código)');

$svc = file_get_contents(__DIR__ . '/src/Services/DecisaoAtendimentoService.php');

if (preg_match("/TIPOS_SUPORTE\s*=\s*\[([^\]]+)\]/", $svc, $m)) {
    $lista = trim($m[1]);
    info($report, "TIPOS_SUPORTE = [$lista]");
    if (strpos($lista, "'me_orientem'") !== false) {
        erro($report, "  ↳ 'me_orientem' ESTÁ em suporte — vai cair no fallback de suporte");
    } else {
        ok($report, "  ↳ Correto (sem 'me_orientem')");
    }
} else {
    erro($report, "Não achei TIPOS_SUPORTE");
}

if (strpos($svc, 'buscarOficinasNoRaio') !== false) {
    ok($report, "Método buscarOficinasNoRaio existe (fallback para tabela oficinas)");
} else {
    aviso($report, "buscarOficinasNoRaio NÃO existe — sem fallback para tabela oficinas");
}

if (strpos($svc, "FROM oficinas o") !== false || strpos($svc, "FROM oficinas o") !== false) {
    ok($report, "Query usa tabela 'oficinas'");
} else {
    aviso($report, "Query NÃO usa tabela 'oficinas'");
}

if (strpos($svc, "oficina_servicos") !== false) {
    ok($report, "Query usa tabela 'oficina_servicos' (filtra por tipo)");
} else {
    aviso($report, "Query NÃO usa 'oficina_servicos' — não filtra por tipo de problema");
}

// ═════════════════════════════════════════════════════════════════════
// 4. BANCO DE DADOS
// ═════════════════════════════════════════════════════════════════════
secao($report, '4. BANCO DE DADOS');

try {
    $pdo = getPDO();

    // Oficinas
    $stmt = $pdo->query("SELECT id, nome, ativo, latitude, longitude, raio_atendimento_km FROM oficinas");
    $oficinas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    info($report, "Total oficinas na base: " . count($oficinas));
    foreach ($oficinas as $o) {
        $status = [];
        if ((int)$o['ativo'] !== 1) $status[] = 'INATIVA';
        if (!$o['latitude'] || !$o['longitude']) $status[] = 'SEM GEO';
        $sufixo = empty($status) ? 'pronta' : implode(', ', $status);
        info($report, "  #{$o['id']} {$o['nome']} → $sufixo");
    }

    // Oficina_servicos
    $stmt = $pdo->query("SELECT COUNT(*) FROM oficina_servicos WHERE ativo = 1");
    $servicos = (int)$stmt->fetchColumn();
    if ($servicos === 0) {
        erro($report, "oficina_servicos VAZIO — nenhuma oficina vai aparecer no comparativo");
    } else {
        ok($report, "oficina_servicos: $servicos registros ativos");
        $stmt = $pdo->query("SELECT o.id, o.nome, GROUP_CONCAT(os.tipo ORDER BY os.tipo SEPARATOR ', ') AS servicos
                              FROM oficinas o
                              LEFT JOIN oficina_servicos os ON os.oficina_id = o.id AND os.ativo = 1
                              GROUP BY o.id");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            info($report, "  #{$r['id']} {$r['nome']}: " . ($r['servicos'] ?: '(nenhum)'));
        }
    }

    // Configuracoes relevantes
    $stmt = $pdo->query("SELECT chave, valor FROM configuracoes WHERE chave IN
        ('custo_saida_profissional_padrao','comissao_assistencia_percentual',
         'taxa_fixa','tarifa_por_km','desconto_saida_oficina_percentual',
         'habilitar_comparativo_assistencia')");
    $configs = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    info($report, "Configurações relevantes:");
    foreach ($configs as $k => $v) {
        info($report, "  • $k = $v");
    }
    if (!isset($configs['habilitar_comparativo_assistencia'])) {
        aviso($report, "  ↳ 'habilitar_comparativo_assistencia' NÃO está configurado (default = 1 = habilitado)");
    }

} catch (Throwable $e) {
    erro($report, "Erro ao consultar banco: " . $e->getMessage());
}

// ═════════════════════════════════════════════════════════════════════
// 5. TESTE DO ENDPOINT (via HTTP interno)
// ═════════════════════════════════════════════════════════════════════
secao($report, '5. TESTE DO ENDPOINT /api/pre-cotacao/decisao');

// Simula a request como o JS faria
$url = 'http://localhost:8080/guinchafacil/api/pre-cotacao/decisao';
$body = json_encode(['pedido_draft' => [
    'tipo_problema' => 'pneu',
    'veiculo_pode_mover' => true,
    'lat_origem' => -22.8970,
    'lng_origem' => -43.1870,
    'categoria' => 'popular'
]]);

info($report, "URL: $url");
info($report, "Body: $body");
info($report, "Lat/Lng: -22.8970, -43.1870 (Rua da Gamboa, perto da oficina #1)");

// Usa curl se disponível
if (function_exists('curl_init')) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => false,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        erro($report, "Erro cURL: $curlErr");
    } elseif ($httpCode === 200) {
        ok($report, "HTTP 200 recebido");
        $json = json_decode($response, true);
        if (!$json) {
            erro($report, "Response não é JSON válido");
            info($report, "  Resposta bruta: " . substr($response, 0, 500));
        } else {
            info($report, "JSON decodificado:");
            info($report, "  " . json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            $data = $json['data'] ?? $json;
            if (!empty($data['acao']) && $data['acao'] === 'encaminhar_suporte') {
                erro($report, "  ↳ ação = encaminhar_suporte (deveria ser comparativo)");
            }
            if (!empty($data['opcao_assistencia']['disponivel'])) {
                ok($report, "  ↳ assistência DISPONÍVEL (comparativo vai aparecer)");
            } else {
                aviso($report, "  ↳ assistência indisponível (só reboque)");
                if (!empty($data['justificativa'])) {
                    info($report, "  ↳ Motivo: " . $data['justificativa']);
                }
            }
        }
    } else {
        erro($report, "HTTP $httpCode");
        info($report, "  Resposta: " . substr($response ?: '(vazio)', 0, 500));
    }
} else {
    aviso($report, "cURL não disponível — teste manual necessário");
}

// ═════════════════════════════════════════════════════════════════════
// 6. TESTE DIRETO DO SERVICE (sem HTTP)
// ═════════════════════════════════════════════════════════════════════
secao($report, '6. TESTE DIRETO do DecisaoAtendimentoService');

try {
    require_once __DIR__ . '/src/Services/DecisaoAtendimentoService.php';
    $svc = new DecisaoAtendimentoService();

    $casos = [
        ['tipo' => 'pneu',           'desc' => 'Pneu (deve achar borracharia)'],
        ['tipo' => 'eletrica',       'desc' => 'Elétrica (deve achar eletricista)'],
        ['tipo' => 'bateria',        'desc' => 'Bateria'],
        ['tipo' => 'mecanica',       'desc' => 'Mecânica'],
        ['tipo' => 'chaveiro',       'desc' => 'Chaveiro'],
        ['tipo' => 'me_orientem',    'desc' => 'Me orientem (NÃO deve ir p/ suporte)'],
        ['tipo' => 'resolver_local', 'desc' => 'Resolver local'],
        ['tipo' => 'orientacao',     'desc' => 'Orientação (deve ir p/ suporte)'],
    ];

    foreach ($casos as $caso) {
        info($report, "");
        info($report, "▶ {$caso['desc']} (tipo={$caso['tipo']})");

        $result = $svc->avaliar(0, $caso['tipo'], -22.8970, -43.1870, [
            'categoria' => 'popular',
            'distancia_km' => 5.0,
            'veiculo_pode_mover' => true,
        ]);

        $acao = $result['acao'] ?? '(comparativo)';
        $opcoes = $result['opcoes_disponiveis'] ?? [];
        $assist = $result['opcao_assistencia']['disponivel'] ?? false;
        $custoA = $result['opcao_assistencia']['custo_saida'] ?? 0;
        $custoT = $result['opcao_reboque']['custo_total'] ?? 0;

        info($report, "  ação: $acao");
        info($report, "  opções: " . implode(', ', $opcoes));
        info($report, "  assistência disponível: " . ($assist ? "SIM" : "NÃO"));
        if ($assist) {
            info($report, "  custo assistência: R$ $custoA");
        }
        info($report, "  custo reboque: R$ $custoT");

        if ($caso['tipo'] === 'me_orientem' && $acao === 'encaminhar_suporte') {
            erro($report, "  ↳ BUG: me_orientem foi para suporte!");
        }
        if (in_array($caso['tipo'], ['pneu','eletrica','bateria','mecanica','chaveiro']) && !$assist) {
            aviso($report, "  ↳ Não achou oficina para '{$caso['tipo']}'");
        }
    }

} catch (Throwable $e) {
    erro($report, "Erro ao instanciar service: " . $e->getMessage());
    erro($report, "  Stack: " . $e->getTraceAsString());
}

// ═════════════════════════════════════════════════════════════════════
// 7. JAVASCRIPT DA VIEW
// ═════════════════════════════════════════════════════════════════════
secao($report, '7. JAVASCRIPT carregado na view');

$view = file_get_contents(__DIR__ . '/src/Views/public/pre-cotacao.php');

$scriptsEsperados = [
    'public-quote-result.js',
    'public-pre-cotacao.js',
    'public-pre-cotacao-form.js',
    'public-pre-cotacao-sintomas.js',
];
foreach ($scriptsEsperados as $s) {
    if (strpos($view, $s) !== false) {
        ok($report, "View carrega: $s");
    } else {
        erro($report, "View NÃO carrega: $s");
    }
}

// Ordem dos scripts importa
$posForm = strpos($view, 'public-pre-cotacao-form.js');
$posSintomas = strpos($view, 'public-pre-cotacao-sintomas.js');
if ($posForm !== false && $posSintomas !== false) {
    if ($posSintomas > $posForm) {
        ok($report, "sintomas.js carregado DEPOIS de form.js (ordem correta)");
    } else {
        erro($report, "sintomas.js antes de form.js — form.js pode sobrescrever");
    }
}

// Verifica elementos
$elementos = ['situacaoStage','sintomaStage','decisionStage','fieldVeiculoPodeMover','tipo_problema','btnSituacaoAvancar'];
foreach ($elementos as $id) {
    if (strpos($view, "id=\"$id\"") !== false || strpos($view, "id='$id'") !== false) {
        ok($report, "Elemento #$id presente na view");
    } else {
        erro($report, "Elemento #$id AUSENTE na view");
    }
}

// Verifica cards
$cards = ['me_orientem','resolver_local','levar_carro'];
foreach ($cards as $c) {
    if (strpos($view, "data-choice-value=\"$c\"") !== false) {
        ok($report, "Card de situação '$c' presente");
    } else {
        erro($report, "Card de situação '$c' AUSENTE");
    }
}

// Cards de sintoma
$sintomas = ['pneu','eletrica','bateria','mecanica','chaveiro'];
foreach ($sintomas as $s) {
    if (strpos($view, "'$s'") !== false) {
        ok($report, "Card de sintoma '$s' presente");
    } else {
        erro($report, "Card de sintoma '$s' AUSENTE");
    }
}

// ═════════════════════════════════════════════════════════════════════
// 8. JS SINTOMAS — verificar handlers
// ═════════════════════════════════════════════════════════════════════
secao($report, '8. JS sintomas.js');

$jsSintomas = file_get_contents(__DIR__ . '/public/assets/js/public-pre-cotacao-sintomas.js');

if (strpos($jsSintomas, "addEventListener('click'") !== false) {
    ok($report, "sintomas.js tem event listener de click");
} else {
    erro($report, "sintomas.js NÃO tem listener de click");
}

if (strpos($jsSintomas, "situacaoStage.hidden = false") !== false) {
    ok($report, "sintomas.js manipula situacaoStage.hidden");
}

if (strpos($jsSintomas, "sintomaStage.hidden = false") !== false) {
    ok($report, "sintomas.js manipula sintomaStage.hidden");
}

if (strpos($jsSintomas, "aplicarSintoma") !== false) {
    ok($report, "sintomas.js tem função aplicarSintoma");
}

if (strpos($jsSintomas, "chamarApiDecisao") !== false || strpos($jsSintomas, "fetch(") !== false) {
    ok($report, "sintomas.js chama API de decisão");
}

if (strpos($jsSintomas, 'DOMContentLoaded') !== false || strpos($jsSintomas, 'readyState') !== false) {
    ok($report, "sintomas.js espera DOMContentLoaded");
}

// ═════════════════════════════════════════════════════════════════════
// 9. CONFLITO entre JSs
// ═════════════════════════════════════════════════════════════════════
secao($report, '9. Possíveis conflitos entre JSs');

$jsForm = file_get_contents(__DIR__ . '/public/assets/js/public-pre-cotacao-form.js');

if (strpos($jsForm, "situacaoStage.querySelectorAll") !== false) {
    aviso($report, "form.js TAMBÉM manipula situacaoStage — pode conflitar");
}
if (strpos($jsForm, "addEventListener('click'") !== false) {
    aviso($report, "form.js tem listeners de click");
}
if (strpos($jsForm, "tipo.addEventListener('change'") !== false) {
    info($report, "form.js escuta 'change' em tipo_problema (chama carregarDecisao)");
}

$jsOutro = file_get_contents(__DIR__ . '/public/assets/js/public-pre-cotacao.js');
if (strpos($jsOutro, "tipo_problema") !== false) {
    info($report, "public-pre-cotacao.js menciona tipo_problema (dispara prequote:type-change)");
}

// ═════════════════════════════════════════════════════════════════════
// RENDER
// ═════════════════════════════════════════════════════════════════════
?><!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Diagnóstico</title>
<style>
body{font-family:ui-monospace,Menlo,monospace;padding:24px;background:#0f1115;color:#e6e6e6;line-height:1.6;font-size:13px}
h1{color:#F59E0B;margin-bottom:8px}
h2{margin-top:24px;margin-bottom:8px}
pre{background:#000;padding:18px;border-radius:10px;white-space:pre-wrap;word-break:break-word;border:1px solid #2a2f3a;font-size:12px;line-height:1.4}
.resumo{background:#1a1f2a;padding:16px;border-radius:10px;margin-bottom:16px;display:flex;gap:24px;font-size:14px}
.resumo span{font-weight:700}
.resumo .ok{color:#22c55e}
.resumo .aviso{color:#f59e0b}
.resumo .erro{color:#ef4444}
.bloco{margin-bottom:16px}
.bloco-ok{border-left:3px solid #22c55e;padding-left:12px}
.bloco-aviso{border-left:3px solid #f59e0b;padding-left:12px}
.bloco-erro{border-left:3px solid #ef4444;padding-left:12px}
.del{color:#ff6b6b;font-weight:700;background:#2a1010;padding:8px 12px;border-radius:6px;display:inline-block;margin-top:16px}
</style>
</head><body>
<h1>🔬 Diagnóstico completo</h1>
<div class="resumo">
    <div>✅ <span class="ok"><?= count($report['OK']) ?></span> OK</div>
    <div>⚠️  <span class="aviso"><?= count($report['AVISO']) ?></span> avisos</div>
    <div>❌ <span class="erro"><?= count($report['ERRO']) ?></span> erros</div>
</div>

<?php if (!empty($report['ERRO'])): ?>
<h2>❌ Erros (precisa corrigir)</h2>
<div class="bloco-erro">
<?php foreach ($report['ERRO'] as $m): ?>
<div><?= htmlspecialchars($m) ?></div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (!empty($report['AVISO'])): ?>
<h2>⚠️  Avisos</h2>
<div class="bloco-aviso">
<?php foreach ($report['AVISO'] as $m): ?>
<div><?= htmlspecialchars($m) ?></div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (!empty($report['OK'])): ?>
<h2>✅ Tudo que passou</h2>
<div class="bloco-ok">
<?php foreach ($report['OK'] as $m): ?>
<div><?= htmlspecialchars($m) ?></div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<h2>ℹ️  Detalhes completos</h2>
<pre><?= htmlspecialchars(implode("\n", $report['INFO'])) ?></pre>

<p class="del">APAGUE: install-diagnostico.php</p>
</body></html>
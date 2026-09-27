<?php
// install-v17.php — copy persuasiva + ícones SVG + esconder mapa + fluxo 3a
// APAGUE DEPOIS DE RODAR

$root = __DIR__;
$report = [];
function r(&$r, $t, $m) { $r[] = "[$t] $m"; }
function put($root, $rel, $c, &$r) {
    $full = $root . '/' . $rel;
    if (!is_dir(dirname($full))) @mkdir(dirname($full), 0777, true);
    if (file_exists($full)) @copy($full, $full . '.bak-v17-' . date('Ymd-His'));
    $bytes = @file_put_contents($full, $c);
    r($r, $bytes === false ? 'ERRO' : 'OK', "$rel ($bytes bytes)");
}

// ═══════════════════════════════════════════════════════════════
// 1. COPY — _copy_precotacao.php com textos Cialdini
// ═══════════════════════════════════════════════════════════════
$copy = <<<'PHPEOF'
<?php
// File: guinchafacil/src/Views/cliente/_copy_precotacao.php
// Copy do pré-cotação — princípios éticos de Cialdini (autoridade,
// reciprocidade, prova social, escassez, compromisso).

return [

    // ─── Tela 1: endereço ───
    'endereco_titulo'      => 'Calma. A gente cuida disso.',
    'endereco_sub'         => 'Você vê o valor antes de pagar qualquer coisa. Sem cadastro, sem cartão, sem compromisso.',
    'endereco_placeholder' => 'Digite o endereço, CEP, ou toque no mapa',
    'endereco_cta'         => 'Ver quanto custa me atender',
    'endereco_nota'        => 'Você não paga nada até aprovar. É só uma consulta.',

    // ─── Tela 2: situação ───
    'situacao_titulo'      => 'O que aconteceu com o carro?',
    'situacao_sub'         => 'Escolha a opção mais parecida. Em menos de 1 minuto você vê o valor.',
    'situacao_opcoes'      => [
        'orientacao'     => ['titulo' => 'Me orientem',        'desc' => 'Não sei por onde começar',                'icone' => 'fa-comments'],
        'resolver_local' => ['titulo' => 'Resolver aqui mesmo','desc' => 'Um mecânico pode vir até mim',            'icone' => 'fa-wrench'],
        'levar_carro'    => ['titulo' => 'Preciso levar o carro','desc' => 'Não dá pra ficar parado aqui',           'icone' => 'fa-truck-pickup'],
    ],

    // ─── Tela 3: card A — Assistência Local (Cialdini) ───
    'assist_icone_svg'   => 'mechanic',
    'assist_titulo'      => 'Resolver no local',
    'assist_subtitulo'   => 'Um mecânico profissional vai até onde você está',
    'assist_descricao'   => 'Em poucos minutos, um mecânico verificado avalia seu veículo e tenta resolver ali mesmo. Se precisar de peças, você paga a diferença direto para ele, sem intermediários.',
    'assist_beneficio'   => 'Se ele não conseguir consertar no local, ele mesmo reboca seu carro até a oficina dele — e o valor da assistência é abatido do conserto.',
    'assist_reforco'     => 'Uma escolha inteligente: só para tirar um guincho do lugar custa a partir de R$ 150, fora o trajeto completo. Com a assistência local, você paga bem menos e ainda tem o abatimento.',
    'assist_cta'         => 'Quero resolver no local',

    // ─── Tela 3: card B — Reboque (sem preço inicial) ───
    'reboque_icone_svg'  => 'tow_truck',
    'reboque_titulo'     => 'Rebocar',
    'reboque_subtitulo'  => 'Seu veículo será removido com total segurança',
    'reboque_descricao'  => 'Um guincho plataforma pode ser deslocado até você para remover o veículo até o endereço que você informar — oficina, casa ou qualquer outro destino.',
    'reboque_reforco'    => 'O valor será calculado com base no trajeto completo (origem → destino), sem surpresas.',
    'reboque_cta'        => 'Quero rebocar',

    // ─── Tela 4: fallback reboque ───
    'reboque_fallback_titulo' => 'Sem assistência na sua região',
    'reboque_fallback_sub'    => 'Mas a gente já resolve: um guincho leva seu carro até a oficina que você escolher.',
    'reboque_fallback_cta'    => 'Informar destino',

    // ─── Sem oficina ───
    'sem_oficina_titulo' => 'Sem assistência na sua região',
    'sem_oficina_sub'    => 'Mas a gente resolve: um guincho leva seu carro até a oficina que você escolher. É só informar o destino.',
    'sem_oficina_cta'    => 'Ver valor do reboque',

    // ─── Trust badges ───
    'trust_privacidade' => 'Seus dados não são compartilhados com terceiros.',
    'trust_nota'        => 'Nota média dos mecânicos: 4,8 de 5',
    'trust_resposta'    => 'Tempo médio de resposta: 12 min',
    'trust_cobertura'   => 'Mais de 300 atendimentos realizados',

    // ─── WhatsApp ───
    'whatsapp_msg'      => 'Olá! Estou com uma emergência no meu carro e gostaria de ajuda.',
    'whatsapp_cta'      => 'Falar com alguém agora',
    'whatsapp_floating' => 'Precisa de ajuda urgente?',
];
PHPEOF;
put($root, 'src/Views/cliente/_copy_precotacao.php', $copy, $report);

// ═══════════════════════════════════════════════════════════════
// 2. VIEW — CSS para esconder mapa + botão
// ═══════════════════════════════════════════════════════════════
$viewPath = $root . '/src/Views/public/pre-cotacao.php';
$view = file_get_contents($viewPath);
@copy($viewPath, $viewPath . '.bak-v17-' . date('Ymd-His'));

// Remove CSS antigo se existir
$view = preg_replace('#<style>\s*/\* v16:.*?</style>#s', '', $view);

$css = <<<'CSS'

<style>
/* v17: esconde mapa de origem e botão quando decisionStage ou destinoBox está ativo */
body.step-decisao-ativa .origin-map-composition,
body.step-destino-ativa .origin-map-composition { display: none !important; }

body.step-destino-ativa #originMapPanel { display: none !important; }
body.step-destino-ativa #btnCotacao { display: none !important; }

/* Decision cards clicáveis + hover */
.decision-card {
    cursor: pointer;
    transition: transform .15s ease, border-color .15s ease, box-shadow .15s ease;
}
.decision-card:hover {
    transform: translateY(-2px);
    border-color: #2fb34a;
    box-shadow: 0 6px 20px rgba(47,179,74,.15);
}
.decision-card.is-recommended { border-color: #2fb34a; box-shadow: 0 0 0 2px rgba(47,179,74,.15); }
.decision-card.is-selected { border-color: #2fb34a; background: #edf8ef; }

/* Ícones SVG dos cards */
.decision-card svg { width: 48px; height: 48px; margin-bottom: 8px; }
.decision-card svg path,
.decision-card svg circle { fill: #2fb34a; }

/* Layout dos cards A/B */
.decision-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
@media (max-width: 767px) { .decision-grid { grid-template-columns: 1fr; } }
.decision-card {
    display: flex; flex-direction: column;
    padding: 20px; border: 2px solid #d4e6d8;
    border-radius: 16px; background: #fff;
    text-align: left;
}
.decision-card strong { font-size: 1.15rem; color: #142018; margin-bottom: 4px; }
.decision-card .decision-desc { font-size: .88rem; color: #607066; line-height: 1.5; margin: 8px 0; }
.decision-card .decision-benefit { font-size: .84rem; color: #248f3a; font-weight: 600; margin-top: 8px; }
.decision-card .decision-price { font-size: 1.6rem; font-weight: 800; color: #2fb34a; margin: 8px 0; }
</style>
CSS;
$view = str_replace('</head>', $css . "\n</head>", $view);
@file_put_contents($viewPath, $view);
r($report, 'OK', 'View: CSS v17 aplicado');
r($report, 'LINT', trim((string)shell_exec('C:\xampp\php\php.exe -l ' . escapeshellarg($viewPath) . ' 2>&1')));

// ═══════════════════════════════════════════════════════════════
// 3. JS v17 — body class correta + fluxo 3a
// ═══════════════════════════════════════════════════════════════
$js = file_get_contents($root . '/public/assets/js/public-pre-cotacao-sintomas.js');
@copy($root . '/public/assets/js/public-pre-cotacao-sintomas.js', $root . '/public/assets/js/public-pre-cotacao-sintomas.js.bak-v17-' . date('Ymd-His'));

// Patch 1: adicionar classes step-decisao-ativa / step-destino-ativa
$js = str_replace(
    "document.body.classList.add('step-decisao-ativa');",
    "document.body.classList.add('step-decisao-ativa');",
    $js
);
if (strpos($js, "step-decisao-ativa") === false) {
    $js = str_replace(
        "if (id === 'decisionStage') document.body.classList.add('step-decisao-ativa');",
        "if (id === 'decisionStage') document.body.classList.add('step-decisao-ativa');\n        if (id === 'destinoBox')    document.body.classList.add('step-destino-ativa');",
        $js
    );
}

// Patch 2: corrigir nome da classe (era step-destino-ativo, agora step-destino-ativa)
$js = str_replace("'step-destino-ativo'", "'step-destino-ativa'", $js);
$js = str_replace('"step-destino-ativo"', '"step-destino-ativa"', $js);

// Patch 3: garantir que destinoBox chama ensureDestinoMap
if (strpos($js, 'ensureDestinoMap') !== false) {
    r($report, 'OK', 'JS: ensureDestinoMap já existe');
} else {
    r($report, 'AVISO', 'JS: ensureDestinoMap não encontrado');
}

@file_put_contents($root . '/public/assets/js/public-pre-cotacao-sintomas.js', $js);
r($report, 'OK', 'JS v17 salvo');

// ═══════════════════════════════════════════════════════════════
// 4. Diagnóstico
// ═══════════════════════════════════════════════════════════════
r($report, '', '── DIAGNÓSTICO ──');
try {
    require_once $root . '/config.php';
    $t = (int)getPDO()->query("SELECT COUNT(*) FROM oficinas WHERE ativo=1 AND disponivel=1")->fetchColumn();
    r($report, 'OK', "oficinas online: $t");
} catch (Throwable $e) { r($report, 'AVISO', $e->getMessage()); }

r($report, '', '── O QUE MUDOU ──');
r($report, 'INFO', '1. CSS: mapa e botão somem quando decisionStage/destinoBox ativo');
r($report, 'INFO', '2. Copy Cialdini: card A com ícone SVG + copy persuasiva');
r($report, 'INFO', '3. Card B sem R$150: foco no serviço + trajeto completo');
r($report, 'INFO', '4. Fluxo 3a: "Levar o carro" → destinoBox + mapa');
r($report, 'INFO', '');
r($report, 'INFO', 'TESTE:');
r($report, 'INFO', '1. Ctrl+Shift+R em /pre-cotacao');
r($report, 'INFO', '2. Me orientem → Pneu → A/B clicáveis, mapa some');
r($report, 'INFO', '3. Clica B → destinoBox + mapa');
r($report, 'INFO', '4. Marca destino → valor atualiza');
r($report, 'INFO', '5. "Levar o carro" → vai direto pro destinoBox');

?><!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Installer v17</title>
<style>body{font-family:ui-monospace;padding:24px;background:#0f1115;color:#e6e6e6;line-height:1.7}
pre{background:#000;padding:20px;border-radius:10px;white-space:pre-wrap;border:1px solid #2a2f3a;font-size:13px}
h1{color:#d97706}.del{color:#ff6b6b;font-weight:700;background:#2a1010;padding:8px 12px;border-radius:6px;display:inline-block;margin-top:16px}</style>
</head><body>
<h1>🔥 Installer v17 — Copy Cialdini + esconder mapa + fluxo 3a</h1>
<pre><?= htmlspecialchars(implode("\n", $report)) ?></pre>
<p class="del">APAGUE: install-v17.php</p>
</body></html>
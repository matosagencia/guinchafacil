<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
$esc = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$fmt = static fn($v) => number_format((float)$v, ((float)$v === floor((float)$v)) ? 0 : 2, ',', '.');
$normalizar = static function ($v): string {
    $v = strtoupper(trim((string)$v));
    $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $v);
    return preg_replace('/[^A-Z0-9]+/', ' ', (string)($ascii ?: $v)) ?: '';
};
$zonaCores = ['#198754', '#0d6efd', '#fd7e14', '#6f42c1', '#dc3545', '#20c997', '#6610f2', '#6c757d'];
$zonaPorBairro = static function (string $bairro) use ($zonasTerritoriais, $normalizar): ?array {
    $alvo = $normalizar($bairro);
    if ($alvo === '') return null;
    foreach ($zonasTerritoriais as $zona) {
        $referencias = preg_split('/[,;|]+/', (string)($zona['bairros_referencia'] ?? ''), -1, PREG_SPLIT_NO_EMPTY);
        foreach ($referencias as $referencia) {
            $ref = $normalizar($referencia);
            if ($ref !== '' && (str_contains($alvo, $ref) || str_contains($ref, $alvo))) return $zona;
        }
    }
    return null;
};
include __DIR__ . '/../layouts/header.php';
?>
<main class="main-content"><div class="container-fluid py-4">
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
 <div><div class="text-uppercase small text-muted fw-semibold">Inteligência territorial · Niterói</div><h1 class="mb-1"><i class="fas fa-chess-board me-2 text-primary-custom"></i>Demanda territorial</h1><p class="text-muted mb-0">Leia o tabuleiro em três camadas: demanda observada, contexto externo e próxima jogada.</p></div>
 <a class="btn btn-outline-primary" href="<?= $esc($bp) ?>/admin/precificacao/zonas"><i class="fas fa-map me-1"></i>Ver zonas e metas</a>
</div>
<div class="alert alert-info border-0 shadow-sm"><strong>Como interpretar:</strong> pré-cotações são o sinal operacional do GuinchaFácil. RENAEST e NitTrans ajudam a priorizar investigação e aquisição. Nenhuma fonte externa foi distribuída artificialmente pelas zonas.</div>

<section class="card shadow-sm mb-4"><div class="card-body"><div class="d-flex justify-content-between gap-3 flex-wrap"><div><h2 class="h5 mb-1"><i class="fas fa-bullseye me-2 text-success"></i>Sinal real do produto</h2><p class="small text-muted mb-0">Células agregadas dos últimos 30 dias; uma célula só aparece com pelo menos 5 pré-cotações.</p></div><span class="badge text-bg-success align-self-start">Fonte: GuinchaFácil</span></div>
<div class="table-responsive mt-3"><table class="table table-sm align-middle mb-0"><thead><tr><th>Célula agregada</th><th>Pré-cotações</th><th>Aceites</th><th>Convertidos</th><th>Leitura</th></tr></thead><tbody>
<?php foreach (($prioridades ?? []) as $p): ?><tr><td><code><?= $esc($p['cell_lat'] . ', ' . $p['cell_lng']) ?></code></td><td><?= (int)$p['quote_count'] ?></td><td><?= (int)$p['accepted_count'] ?></td><td><?= (int)$p['converted_count'] ?></td><td><span class="badge text-bg-warning">Investigar cobertura</span></td></tr><?php endforeach; ?>
<?php if (empty($prioridades)): ?><tr><td colspan="5" class="text-center text-muted py-4">Ainda não há volume agregado suficiente para sinalizar uma célula.</td></tr><?php endif; ?></tbody></table></div></div></section>

<?php if (!empty($indicadoresExternos)): ?><section class="card shadow-sm mb-4"><div class="card-body"><div class="d-flex justify-content-between gap-3 flex-wrap"><div><h2 class="h5 mb-1"><i class="fas fa-database me-2 text-primary"></i>Contexto municipal oficial</h2><p class="small text-muted mb-0">Indicadores agregados; não representam pedidos nem pontos de acidente.</p></div><span class="badge text-bg-secondary align-self-start">Evidência externa</span></div><div class="row g-3 mt-1"><?php foreach ($indicadoresExternos as $i): ?><div class="col-md-4"><div class="border rounded p-3 h-100"><div class="small text-muted"><?= $esc($i['indicator_name']) ?> · <?= (int)$i['reference_year'] ?></div><strong class="fs-4"><?= $fmt($i['value_decimal']) ?></strong><div class="small mb-2"><?= $esc($i['unit']) ?></div><a class="small" target="_blank" rel="noopener" href="<?= $esc($i['source_url']) ?>"><?= $esc($i['source_name']) ?></a></div></div><?php endforeach; ?></div></div></section><?php endif; ?>

<div class="row g-4"><div class="col-xl-7"><section class="card shadow-sm h-100"><div class="card-body"><div class="d-flex justify-content-between gap-3"><div><h2 class="h5 mb-1"><i class="fas fa-road me-2 text-danger"></i>Vias com maior incidência histórica</h2><p class="small text-muted">RENAEST, Niterói, 2018–2024; nomes normalizados e variações somadas. <a href="https://nittrans.niteroi.rj.gov.br/estatisticas-e-estudos/" target="_blank" rel="noopener">Consultar estudos da NitTrans</a>.</p></div><span class="badge text-bg-danger align-self-start">Prioridade de observação</span></div><div class="row g-2 mt-2"><?php foreach (($rankingsExternos['via'] ?? []) as $idx => $r): ?><div class="col-md-6"><div class="d-flex justify-content-between align-items-center border rounded px-3 py-2"><span><span class="text-muted me-2">#<?= $idx + 1 ?></span><?= $esc($r['label']) ?></span><strong><?= (int)$r['occurrence_count'] ?></strong></div></div><?php endforeach; ?><?php if (empty($rankingsExternos['via'])): ?><div class="small text-muted">Ranking ainda não importado. Execute a migration de rankings.</div><?php endif; ?></div><p class="small text-muted mt-3 mb-0">Uso recomendado: campanhas e recrutamento direcionados para vias que coincidirem com uma zona e com sinal próprio do produto.</p></div></section></div>
<div class="col-xl-5"><section class="card shadow-sm h-100"><div class="card-body"><h2 class="h5 mb-1"><i class="fas fa-location-dot me-2 text-warning"></i>Bairros com maior incidência</h2><p class="small text-muted">Ranking histórico cruzado com <strong>bairros de referência</strong> das zonas. A cor indica apenas a associação cadastrada, não uma nova fronteira geográfica.</p><?php foreach (($rankingsExternos['bairro'] ?? []) as $r): $zona = $zonaPorBairro((string)$r['label']); $zonaIndex = $zona ? array_search((int)$zona['id'], array_map(static fn($z) => (int)$z['id'], $zonasTerritoriais), true) : false; $cor = ($zonaIndex !== false) ? $zonaCores[$zonaIndex % count($zonaCores)] : '#adb5bd'; ?><div class="d-flex justify-content-between align-items-center border-bottom py-2 gap-2"><span><i class="fas fa-circle me-2" style="color:<?= $esc($cor) ?>" title="Cor da zona"></i><?= $esc($r['label']) ?><?php if ($zona): ?> <span class="badge ms-1" style="background-color:<?= $esc($cor) ?>"><?= $esc($zona['name']) ?></span><?php else: ?> <span class="badge text-bg-light border ms-1">Sem zona associada</span><?php endif; ?></span><strong><?= (int)$r['occurrence_count'] ?></strong></div><?php endforeach; ?><?php if (empty($rankingsExternos['bairro'])): ?><div class="small text-muted">Ranking ainda não importado.</div><?php endif; ?><p class="small text-muted mt-3 mb-0">Cruzar com Pedra Viva/Pedra Morta e metas de prestadores antes de mudar o status. Para melhorar o cruzamento, mantenha os bairros separados por vírgula no cadastro da zona.</p></div></section></div></div>

<section class="card shadow-sm mt-4"><div class="card-body"><h2 class="h6 mb-3"><i class="fas fa-palette me-2"></i>Legenda das zonas</h2><div class="d-flex flex-wrap gap-3 small"><?php foreach ($zonasTerritoriais as $idx => $zona): ?><span><i class="fas fa-circle me-1" style="color:<?= $esc($zonaCores[$idx % count($zonaCores)]) ?>"></i><?= $esc($zona['name']) ?> <span class="text-muted">(<?= $esc($zona['status_expansao'] ?? 'sem status') ?>)</span></span><?php endforeach; ?><?php if (empty($zonasTerritoriais)): ?><span class="text-muted">Nenhuma zona ativa carregada.</span><?php endif; ?></div></div></section>

<section class="card border-warning shadow-sm mt-4"><div class="card-body"><h2 class="h5"><i class="fas fa-shield-halved me-2 text-warning"></i>Regra de decisão</h2><div class="row g-3 small"><div class="col-md-4"><strong>Pedra Viva</strong><br>demanda do produto + oferta mínima atingida.</div><div class="col-md-4"><strong>Pedra Morta</strong><br>há oportunidade externa, mas ainda falta validação operacional ou oferta.</div><div class="col-md-4"><strong>Próximo passo</strong><br>recrutar parceiros e medir pré-cotações antes de ampliar mídia.</div></div><hr><p class="small mb-0"><strong>Limitação:</strong> o arquivo RENAEST de Niterói não trouxe coordenadas válidas. A página não desenha pontos de sinistros e não afirma que uma via pertence a uma zona sem validação do polígono.</p></div></section>
</div></main><?php include __DIR__ . '/../layouts/footer.php'; ?>

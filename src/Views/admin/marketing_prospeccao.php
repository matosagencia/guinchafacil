<?php
$e = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$prospeccao = $prospeccao ?? [];
$regioes = $prospeccao['regioes'] ?? [];
$fila = $prospeccao['fila'] ?? [];
$historico = $prospeccao['historico'] ?? [];
$resumo = $prospeccao['resumo'] ?? [];
$zonasTerritoriais = $prospeccao['zonas_territoriais'] ?? [];
$serpApiKey = trim((string)($prospeccao['serpapi_key'] ?? ''));
$serpApiMask = $serpApiKey !== ''
    ? substr($serpApiKey, 0, 4) . str_repeat('*', max(4, strlen($serpApiKey) - 8)) . substr($serpApiKey, -4)
    : 'nao configurada';
?>
<section class="mk-card mt-4" id="prospeccao">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
        <div>
            <h2 class="mb-1"><i class="fas fa-user-plus me-2 text-success"></i>Prospecção de parceiros</h2>
            <p class="mb-0 mk-muted">Fila integrada no marketing, usando SerpApi, regiões com quota e convite manual via WhatsApp.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a class="btn btn-outline-success btn-sm" href="<?= $e($bp) ?>/admin/configuracoes"><i class="fas fa-gear me-1"></i>Abrir configurações</a>
            <a class="btn btn-outline-dark btn-sm" href="<?= $e($bp) ?>/admin/env"><i class="fas fa-shield-halved me-1"></i>Ver .env</a>
        </div>
    </div>

    <?php if (!empty($_SESSION['flash_success'])): ?>
        <div class="alert alert-success"><?= $e($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?></div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['flash_error'])): ?>
        <div class="alert alert-danger"><?= $e($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></div>
    <?php endif; ?>

    <?php if (empty($prospeccao['serpapi_configurada'])): ?>
        <div class="alert alert-warning">
            <i class="fas fa-triangle-exclamation me-2"></i>SerpApi ainda nao configurada. Preencha <strong>SERPAPI_KEY</strong> em <a href="<?= $e($bp) ?>/admin/configuracoes">Configurações</a> para buscar novos leads.
        </div>
    <?php endif; ?>
    <?php if (empty($prospeccao['schema_ok'])): ?>
        <div class="alert alert-danger">
            <i class="fas fa-database me-2"></i>O banco ainda nao tem as tabelas da prospecção. Rode a migration <code>install/migration_prospeccao_parceiros_v1.sql</code> para ativar a seção.
            <?php if (!empty($prospeccao['setup_error'])): ?>
                <div class="small mt-2">Erro detectado: <?= $e((string)$prospeccao['setup_error']) ?></div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="mk-grid">
        <div class="mk-card">
            <div class="mk-muted">Regiões ativas</div>
            <div class="mk-number"><?= (int)($resumo['regioes_ativas'] ?? 0) ?></div>
            <div class="mk-muted">com fila viva</div>
        </div>
        <div class="mk-card">
            <div class="mk-muted">Leads na fila</div>
            <div class="mk-number"><?= (int)($resumo['leads_na_fila'] ?? 0) ?></div>
            <div class="mk-muted">aguardando ação manual</div>
        </div>
        <div class="mk-card">
            <div class="mk-muted">Vagas restantes</div>
            <div class="mk-number"><?= (int)($resumo['vagas_restantes'] ?? 0) ?></div>
            <div class="mk-muted">quota aberta no território</div>
        </div>
        <div class="mk-card">
            <div class="mk-muted">SerpApi</div>
            <div class="mk-number" style="font-size:1.1rem"><?= $e($serpApiMask) ?></div>
            <div class="mk-muted">chave atual do .env</div>
        </div>
    </div>

    <div class="mt-4">
        <ul class="nav nav-tabs" id="marketingProspeccaoTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="pros-operacao-tab" data-bs-toggle="tab" data-bs-target="#pros-operacao" type="button" role="tab" aria-controls="pros-operacao" aria-selected="true">Operação</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="pros-niteroi-tab" data-bs-toggle="tab" data-bs-target="#pros-niteroi" type="button" role="tab" aria-controls="pros-niteroi" aria-selected="false">Niterói</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="pros-fila-tab" data-bs-toggle="tab" data-bs-target="#pros-fila" type="button" role="tab" aria-controls="pros-fila" aria-selected="false">Fila do dia</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="pros-historico-tab" data-bs-toggle="tab" data-bs-target="#pros-historico" type="button" role="tab" aria-controls="pros-historico" aria-selected="false">Histórico</button>
            </li>
        </ul>

        <div class="tab-content pt-3" id="marketingProspeccaoTabsContent">
            <div class="tab-pane fade show active" id="pros-operacao" role="tabpanel" aria-labelledby="pros-operacao-tab">
                <div class="row g-4">
                    <div class="col-lg-5">
                        <div class="mk-card h-100">
                            <details class="mb-3" open>
                                <summary class="fw-semibold" style="cursor:pointer;"><i class="fas fa-wand-magic-sparkles me-2 text-success"></i>Buscar leads</summary>
                                <form method="post" action="<?= $e($bp) ?>/admin/marketing/prospeccao/buscar" class="row g-3 mt-3">
                                    <input type="hidden" name="csrf_token" value="<?= $e($csrfToken ?? '') ?>">
                                    <div class="col-12">
                                        <label class="form-label">Região</label>
                                        <select class="form-select" name="regiao_id" required>
                                            <?php foreach ($regioes as $r): ?>
                                                <option value="<?= (int)$r['id'] ?>">
                                                    <?= $e($r['nome']) ?> - <?= $e($r['cidade']) ?>/<?= $e($r['uf']) ?> (<?= (int)($r['quota_atingida'] ?? 0) ?>/<?= (int)($r['quota_alvo'] ?? 0) ?>)
                                                </option>
                                            <?php endforeach; ?>
                                            <?php if (empty($regioes)): ?>
                                                <option value="">Nenhuma região ativa</option>
                                            <?php endif; ?>
                                        </select>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Páginas por categoria</label>
                                        <input type="number" class="form-control" name="paginas" min="1" max="3" value="1">
                                    </div>
                                    <div class="col-12">
                                        <button class="btn btn-success w-100" type="submit"><i class="fas fa-satellite-dish me-1"></i>Buscar na SerpApi</button>
                                    </div>
                                </form>
                            </details>

                            <details class="mb-0">
                                <summary class="fw-semibold" style="cursor:pointer;"><i class="fas fa-map-location-dot me-2 text-primary"></i>Nova região</summary>
                                <form method="post" action="<?= $e($bp) ?>/admin/marketing/prospeccao/regioes/salvar" class="row g-3 mt-3">
                                    <input type="hidden" name="csrf_token" value="<?= $e($csrfToken ?? '') ?>">
                                    <div class="col-12">
                                        <label class="form-label">Nome</label>
                                        <input class="form-control" name="nome" placeholder="Ex.: Niterói Centro" required>
                                    </div>
                                    <div class="col-md-7">
                                        <label class="form-label">Cidade</label>
                                        <input class="form-control" name="cidade" placeholder="Niterói" required>
                                    </div>
                                    <div class="col-md-5">
                                        <label class="form-label">UF</label>
                                        <input class="form-control" name="uf" maxlength="2" placeholder="RJ" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Latitude</label>
                                        <input class="form-control" name="lat" inputmode="decimal" placeholder="-22.8833" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Longitude</label>
                                        <input class="form-control" name="lng" inputmode="decimal" placeholder="-43.1033" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Raio (km)</label>
                                        <input class="form-control" type="number" min="1" max="100" name="raio_km" value="<?= (int)($prospeccao['raio_padrao'] ?? 15) ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Quota alvo</label>
                                        <input class="form-control" type="number" min="1" max="50" name="quota_alvo" value="<?= (int)($prospeccao['quota_padrao'] ?? 5) ?>">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Prioridade</label>
                                        <input class="form-control" type="number" min="1" max="999" name="prioridade_fuseki" value="<?= (int)($prospeccao['prioridade_padrao'] ?? 100) ?>">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Categorias alvo</label>
                                        <textarea class="form-control" name="categorias_alvo" rows="3"><?= $e($prospeccao['categorias_padrao'] ?? '') ?></textarea>
                                    </div>
                                    <div class="col-12">
                                        <button class="btn btn-primary w-100" type="submit"><i class="fas fa-save me-1"></i>Salvar região</button>
                                    </div>
                                </form>
                            </details>
                        </div>
                    </div>

                    <div class="col-lg-7">
                        <div class="mk-card h-100">
                            <h2><i class="fas fa-sliders me-2 text-warning"></i>Configuração efetiva</h2>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="mk-muted">WhatsApp da empresa</div>
                                    <strong><?= $e((string)($prospeccao['company_whatsapp'] ?? '')) ?></strong>
                                </div>
                                <div class="col-md-6">
                                    <div class="mk-muted">Pré-cadastro</div>
                                    <strong><?= $e((string)($prospeccao['url_pre_cadastro'] ?? '')) ?></strong>
                                </div>
                                <div class="col-md-6">
                                    <div class="mk-muted">Oferta</div>
                                    <strong><?= $e((string)($prospeccao['oferta_reciprocidade'] ?? '')) ?></strong>
                                </div>
                                <div class="col-md-6">
                                    <div class="mk-muted">Resumo de território</div>
                                    <strong><?= (int)($prospeccao['vagas_restantes'] ?? 0) ?> vagas abertas</strong>
                                </div>
                            </div>

                            <hr class="config-hr">

                            <details class="mt-3" open>
                                <summary class="fw-semibold mb-3" style="cursor:pointer;">
                                    <i class="fas fa-map me-2 text-success"></i>Regiões ativas
                                </summary>
                                <div class="table-responsive">
                                    <table class="table mk-table align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th>Região</th>
                                                <th>Quota</th>
                                                <th>Raio</th>
                                                <th>Prioridade</th>
                                                <th></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($regioes as $r): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?= $e($r['nome']) ?></strong><br>
                                                        <span class="text-muted"><?= $e($r['cidade']) ?>/<?= $e($r['uf']) ?></span>
                                                    </td>
                                                    <td><?= (int)($r['quota_atingida'] ?? 0) ?>/<?= (int)($r['quota_alvo'] ?? 0) ?></td>
                                                    <td><?= (int)($r['raio_km'] ?? 0) ?> km</td>
                                                    <td><?= (int)($r['prioridade_fuseki'] ?? 0) ?></td>
                                                    <td class="text-end">
                                                        <form method="post" action="<?= $e($bp) ?>/admin/marketing/prospeccao/buscar" class="d-inline">
                                                            <input type="hidden" name="csrf_token" value="<?= $e($csrfToken ?? '') ?>">
                                                            <input type="hidden" name="regiao_id" value="<?= (int)$r['id'] ?>">
                                                            <input type="hidden" name="paginas" value="1">
                                                            <button class="btn btn-outline-success btn-sm" type="submit">Buscar</button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                            <?php if (empty($regioes)): ?>
                                                <tr><td colspan="5" class="text-muted">Nenhuma região ativa ainda.</td></tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </details>
                        </div>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="pros-niteroi" role="tabpanel" aria-labelledby="pros-niteroi-tab">
                <div class="mk-card">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                        <div>
                            <h2 class="mb-1"><i class="fas fa-layer-group me-2 text-primary"></i>Células de Niterói detectadas</h2>
                            <div class="mk-muted">Lidas de `admin/precificacao/zonas` e sincronizáveis para a prospecção.</div>
                        </div>
                        <form method="post" action="<?= $e($bp) ?>/admin/marketing/prospeccao/sincronizar-zonas" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?= $e($csrfToken ?? '') ?>">
                            <button class="btn btn-outline-primary btn-sm" type="submit"><i class="fas fa-arrows-rotate me-1"></i>Sincronizar com prospecção</button>
                        </form>
                    </div>

                    <div class="table-responsive">
                        <table class="table mk-table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Célula</th>
                                    <th>Status</th>
                                    <th>Centro</th>
                                    <th>Raio</th>
                                    <th>Quota</th>
                                    <th>Base de referência</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($zonasTerritoriais as $zona): ?>
                                    <?php
                                    $status = (string)($zona['status_expansao'] ?? '');
                                    $badgeClass = 'text-bg-secondary';
                                    $badgeLabel = $status !== '' ? $status : 'sem status';
                                    if ($status === 'pedra_viva') {
                                        $badgeClass = 'text-bg-success';
                                        $badgeLabel = 'Pedra viva';
                                    } elseif ($status === 'pedra_morta') {
                                        $badgeClass = 'text-bg-warning';
                                        $badgeLabel = 'Pedra morta';
                                    } elseif ($status === 'nao_ativada') {
                                        $badgeClass = 'text-bg-dark';
                                        $badgeLabel = 'Não ativada';
                                    }
                                    $regiaoExistente = $zona['regiao_existente'] ?? null;
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?= $e($zona['name'] ?? '') ?></strong><br>
                                            <span class="text-muted small"><?= $e($zona['code'] ?? '') ?></span>
                                        </td>
                                        <td>
                                            <span class="badge <?= $e($badgeClass) ?>"><?= $e($badgeLabel) ?></span>
                                            <div class="small text-muted mt-1"><?= !empty($regiaoExistente) ? 'Já sincronizada em prospecção' : 'Ainda não sincronizada' ?></div>
                                        </td>
                                        <td>
                                            <?= $e(number_format((float)($zona['centro']['lat'] ?? 0), 5, ',', '.')) ?><br>
                                            <span class="text-muted small"><?= $e(number_format((float)($zona['centro']['lng'] ?? 0), 5, ',', '.')) ?></span>
                                        </td>
                                        <td><?= (int)($zona['raio_km_sugerido'] ?? 0) ?> km</td>
                                        <td><?= (int)($zona['quota_alvo_sugerida'] ?? 0) ?></td>
                                        <td>
                                            <div><?= $e((string)($zona['bairros_referencia'] ?? '')) ?></div>
                                            <?php if (!empty($zona['regiao_existente'])): ?>
                                                <div class="small text-muted">
                                                    <?= $e((string)$zona['regiao_existente']['nome']) ?> · quota <?= (int)($zona['regiao_existente']['quota_atingida'] ?? 0) ?>/<?= (int)($zona['regiao_existente']['quota_alvo'] ?? 0) ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($zonasTerritoriais)): ?>
                                    <tr><td colspan="6" class="text-muted">Nenhuma célula de Niterói foi detectada em admin/precificacao/zonas.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="pros-fila" role="tabpanel" aria-labelledby="pros-fila-tab">
                <div class="mk-card">
                    <h2><i class="fas fa-list-check me-2 text-primary"></i>Fila do dia</h2>
                    <div class="mk-muted mb-3"><?= count($fila) ?> lead(s) pronto(s) para revisão manual.</div>

                    <?php if (!empty($prospeccao['erro_fila'])): ?>
                        <div class="alert alert-warning"><?= $e($prospeccao['erro_fila']) ?></div>
                    <?php endif; ?>

                    <?php foreach ($fila as $item): ?>
                        <?php
                        $lead = $item['lead'] ?? [];
                        $regiao = $item['regiao'] ?? [];
                        $convite = $item['convite'] ?? [];
                        ?>
                        <div class="border rounded p-3 mb-3">
                            <div class="d-flex justify-content-between gap-3 flex-wrap">
                                <div>
                                    <strong><?= $e($lead['nome_negocio'] ?? '') ?></strong>
                                    <span class="badge text-bg-secondary ms-2"><?= $e($lead['categoria'] ?? '') ?></span>
                                    <div class="text-muted small">
                                        <?= $e($regiao['nome'] ?? '') ?> · score <?= $e((string)($lead['score_go'] ?? '0')) ?> ·
                                        <?= (int)($convite['vagas_restantes'] ?? 0) ?> vaga(s) restantes
                                    </div>
                                </div>
                                <div class="text-end small text-muted">
                                    <?= $e($lead['telefone'] ?? '') ?><br>
                                    <?= $e((string)($lead['rating'] ?? '—')) ?> ★ (<?= (int)($lead['reviews_count'] ?? 0) ?> avaliações)
                                </div>
                            </div>

                            <?php if (!empty($lead['endereco'])): ?>
                                <div class="text-muted small mt-2"><?= $e($lead['endereco']) ?></div>
                            <?php endif; ?>

                            <textarea class="form-control mt-3" rows="4" readonly><?= $e($convite['texto'] ?? '') ?></textarea>

                            <div class="d-flex gap-2 mt-3 flex-wrap">
                                <?php if (!empty($convite['wa_link'])): ?>
                                    <a href="<?= $e($convite['wa_link']) ?>" target="_blank" rel="noopener" class="btn btn-success btn-sm">
                                        <i class="fab fa-whatsapp me-1"></i>Abrir no WhatsApp
                                    </a>
                                <?php endif; ?>

                                <form method="post" action="<?= $e($bp) ?>/admin/marketing/prospeccao/lead/enviado/<?= (int)($lead['id'] ?? 0) ?>">
                                    <input type="hidden" name="csrf_token" value="<?= $e($csrfToken ?? '') ?>">
                                    <input type="hidden" name="mensagem_texto" value="<?= $e($convite['texto'] ?? '') ?>">
                                    <input type="hidden" name="wa_link" value="<?= $e((string)($convite['wa_link'] ?? '')) ?>">
                                    <button type="submit" class="btn btn-outline-secondary btn-sm">Marcar como enviado</button>
                                </form>

                                <form method="post" action="<?= $e($bp) ?>/admin/marketing/prospeccao/lead/cadastrado/<?= (int)($lead['id'] ?? 0) ?>">
                                    <input type="hidden" name="csrf_token" value="<?= $e($csrfToken ?? '') ?>">
                                    <button type="submit" class="btn btn-outline-success btn-sm">Confirmar cadastro</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <?php if (empty($fila)): ?>
                        <p class="text-muted mb-0">Nenhum lead na fila. Busque leads em uma região ativa acima.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="tab-pane fade" id="pros-historico" role="tabpanel" aria-labelledby="pros-historico-tab">
                <div class="mk-card">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                        <div>
                            <h2 class="mb-1"><i class="fas fa-clock-rotate-left me-2 text-warning"></i>Histórico de contatos e operações</h2>
                            <div class="mk-muted">Contatos obtidos, buscas, sincronizações e confirmações realizadas no painel.</div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table mk-table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Data</th>
                                    <th>Tipo</th>
                                    <th>Ação</th>
                                    <th>Título</th>
                                    <th>Contato / Região</th>
                                    <th>Detalhes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($historico as $item): ?>
                                    <?php
                                    $tipo = (string)($item['tipo'] ?? '');
                                    $badge = $tipo === 'contato_obtido' ? 'text-bg-success' : 'text-bg-secondary';
                                    $contato = trim((string)($item['nome_negocio'] ?? ''));
                                    if ($contato !== '' && !empty($item['telefone'])) {
                                        $contato .= ' · ' . (string)$item['telefone'];
                                    }
                                    $regiaoTxt = trim((string)($item['regiao_nome'] ?? ''));
                                    if ($regiaoTxt !== '') {
                                        $regiaoTxt .= ' · ' . (string)($item['regiao_cidade'] ?? '') . '/' . (string)($item['regiao_uf'] ?? '');
                                    }
                                    ?>
                                    <tr>
                                        <td class="text-nowrap"><?= $e((string)($item['criado_em'] ?? '')) ?></td>
                                        <td><span class="badge <?= $e($badge) ?>"><?= $e($tipo !== '' ? $tipo : 'operacao') ?></span></td>
                                        <td><?= $e((string)($item['acao'] ?? '')) ?></td>
                                        <td><?= $e((string)($item['titulo'] ?? '')) ?></td>
                                        <td>
                                            <div><?= $e($contato !== '' ? $contato : '—') ?></div>
                                            <div class="small text-muted"><?= $e($regiaoTxt !== '' ? $regiaoTxt : '—') ?></div>
                                        </td>
                                        <td>
                                            <?php if (!empty($item['detalhes_json'])): ?>
                                                <pre class="mb-0 small" style="white-space: pre-wrap;"><?= $e((string)$item['detalhes_json']) ?></pre>
                                            <?php else: ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($historico)): ?>
                                    <tr><td colspan="6" class="text-muted">Sem histórico ainda. As próximas buscas e contatos aparecerão aqui.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

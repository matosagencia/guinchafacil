<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
include __DIR__ . '/../layouts/header.php';
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars($bp); ?>/public/assets/css/pages/admin-configuracoes.css?v=20260813-2">
<div class="main-wrapper shell admin-shell">
<?php include __DIR__ . '/../layouts/sidebar_admin.php'; ?>
<main class="main-content shell-main shell-content">

    <header class="page-head mb-4">
        <div>
            <span class="eyebrow">Gestão</span>
            <h1><i class="fas fa-gear me-2 text-primary-custom"></i>Configurações do Sistema</h1>
            <p>Parâmetros de tarifas, comissões e modo de operação</p>
        </div>
    </header>

    <?php if (isset($_GET['salvo'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>Configurações salvas com sucesso!
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['erro']) && $_GET['erro'] === 'env_write'): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i>Erro ao salvar arquivo .env. Verifique as permissões.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php $cidadesAtivas = $cidadesAtivas ?? []; $cidadeIdConfig = $cidadeIdConfig ?? 0; ?>
    <?php
        $modoAtual = (string)($config['system_mode'] ?? 'production');
        $pagamentoAntecipadoAtual = (string)($config['payment_required'] ?? '1') === '1';
        $gatewayAtual = (string)($envAtual['PAYMENT_GATEWAY_ACTIVE'] ?? ($config['gateway_pagamento'] ?? 'mercadopago'));
        $mpEnvAtual = (string)($envAtual['MP_ENV'] ?? 'production');
        $psEnvAtual = (string)($envAtual['PS_ENV'] ?? 'sandbox');
        $serpApiKeyAtual = trim((string)($envAtual['SERPAPI_KEY'] ?? ''));
        $serpApiMask = $serpApiKeyAtual !== ''
            ? substr($serpApiKeyAtual, 0, 4) . str_repeat('*', max(4, strlen($serpApiKeyAtual) - 8)) . substr($serpApiKeyAtual, -4)
            : 'não configurada';
    ?>
    <div class="card mb-4 config-effective-card">
        <div class="card-header"><i class="fas fa-eye me-2"></i>Configuração efetiva carregada</div>
        <div class="card-body">
            <p class="config-info-text mb-3">Valores abaixo são somente leitura e mostram o que o sistema encontrou no arquivo <code><?php echo htmlspecialchars($envArquivoAtivo ?? '.env'); ?></code> e no banco de dados.</p>
            <div class="row g-2 config-effective-grid">
                <div class="col-md-4"><span class="config-effective-label">System Mode <small>(banco)</small></span><strong><?php echo htmlspecialchars($modoAtual); ?></strong></div>
                <div class="col-md-4"><span class="config-effective-label">Pagamento antecipado <small>(banco)</small></span><strong class="<?php echo $pagamentoAntecipadoAtual ? 'text-success' : 'text-warning'; ?>"><?php echo $pagamentoAntecipadoAtual ? 'ATIVADO' : 'DESATIVADO'; ?></strong></div>
                <div class="col-md-4"><span class="config-effective-label">Gateway ativo <small>(arquivo/banco)</small></span><strong><?php echo htmlspecialchars($gatewayAtual); ?></strong></div>
                <div class="col-md-4"><span class="config-effective-label">Mercado Pago <small>(arquivo)</small></span><strong><?php echo htmlspecialchars($mpEnvAtual); ?></strong></div>
                <div class="col-md-4"><span class="config-effective-label">PagSeguro <small>(arquivo)</small></span><strong><?php echo htmlspecialchars($psEnvAtual); ?></strong></div>
                <div class="col-md-4"><span class="config-effective-label">SMTP <small>(arquivo)</small></span><strong><?php echo htmlspecialchars((string)($envAtual['SMTP_HOST'] ?? 'não configurado')); ?>:<?php echo htmlspecialchars((string)($envAtual['SMTP_PORT'] ?? '')); ?></strong></div>
                <div class="col-md-4"><span class="config-effective-label">WhatsApp empresa <small>(arquivo)</small></span><strong><?php echo htmlspecialchars((string)($envAtual['COMPANY_WHATSAPP'] ?? 'não configurado')); ?></strong></div>
                <div class="col-md-4"><span class="config-effective-label">SerpApi <small>(arquivo)</small></span><strong><?php echo htmlspecialchars($serpApiMask); ?></strong></div>
                <div class="col-md-4"><span class="config-effective-label">Pré-cadastro <small>(arquivo)</small></span><strong><?php echo htmlspecialchars((string)($envAtual['PROSPECCAO_URL_PRE_CADASTRO'] ?? '')); ?></strong></div>
            </div>
        </div>
    </div>
    <?php if (!empty($cidadesAtivas)): ?>
    <div class="card mb-4">
        <div class="card-body d-flex flex-wrap align-items-center gap-3">
            <label class="form-label mb-0 fw-bold"><i class="fas fa-city me-1"></i>Editando tarifas de:</label>
            <select class="form-select w-auto" id="configCidadeSeletor" onchange="window.location.href = <?php echo json_encode($bp . '/admin/configuracoes'); ?> + (this.value ? '?cidade_id=' + this.value : '');">
                <option value="">Global (padrão de todas as cidades)</option>
                <?php foreach ($cidadesAtivas as $c): ?>
                <option value="<?php echo (int)$c['id']; ?>" <?php echo $cidadeIdConfig === (int)$c['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['nome'] . '/' . $c['uf']); ?></option>
                <?php endforeach; ?>
            </select>
            <?php if ($cidadeIdConfig > 0): ?>
            <span class="text-muted small"><i class="fas fa-circle-info me-1"></i>Só os valores de <strong>Tarifas e Operação</strong> (seção abaixo) são específicos desta cidade — comissão, gateway e demais configurações continuam globais.</span>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-12">
            <div class="card border-success"><div class="card-header"><i class="fas fa-chart-line me-2"></i>Marketing e conversões</div><div class="card-body">
                <p class="text-muted small">As tags só carregam quando ativadas e após o visitante aceitar cookies de marketing. Não coloque tokens secretos aqui.</p>
                <form method="POST" action="<?php echo $bp; ?>/admin/configuracoes" class="row g-3"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken ?? ''); ?>">
                    <div class="col-md-3"><label class="form-label">Rastreamento</label><select class="form-select" name="marketing_tracking_enabled"><option value="0" <?php echo ($config['marketing_tracking_enabled'] ?? '0') !== '1' ? 'selected' : ''; ?>>Desativado</option><option value="1" <?php echo ($config['marketing_tracking_enabled'] ?? '0') === '1' ? 'selected' : ''; ?>>Ativado</option></select></div>
                    <div class="col-md-3"><label class="form-label">Google Ads ID</label><input class="form-control" name="marketing_google_ads_id" placeholder="AW-123456789" value="<?php echo htmlspecialchars($config['marketing_google_ads_id'] ?? 'AW-18387802162'); ?>"></div>
                    <div class="col-md-3"><label class="form-label">Label de conversão</label><input class="form-control" name="marketing_google_ads_conversion_label" value="<?php echo htmlspecialchars($config['marketing_google_ads_conversion_label'] ?? ''); ?>"></div>
                    <div class="col-md-3"><label class="form-label">GA4 Measurement ID</label><input class="form-control" name="marketing_ga4_measurement_id" placeholder="G-XXXXXXXXXX" value="<?php echo htmlspecialchars($config['marketing_ga4_measurement_id'] ?? 'G-0FFGZ5G576'); ?>"></div>
                    <div class="col-md-3"><label class="form-label">Meta Pixel ID</label><input class="form-control" name="marketing_meta_pixel_id" placeholder="Somente números" value="<?php echo htmlspecialchars($config['marketing_meta_pixel_id'] ?? ''); ?>"></div>
                    <div class="col-12"><button class="btn btn-success"><i class="fas fa-save me-1"></i>Salvar marketing</button></div>
                </form>
            </div></div>
        </div>
        <div class="col-12">
            <div class="card border-primary">
                <div class="card-header"><i class="fas fa-user-plus me-2"></i>Prospecção de parceiros</div>
                <div class="card-body">
                    <p class="text-muted small">Esses campos alimentam a central de marketing e a busca de leads via SerpApi. A chave fica no .env e é gravada pelo próprio painel.</p>
                    <form method="POST" action="<?php echo $bp; ?>/admin/configuracoes" class="row g-3">
                        <?php if (!empty($csrfToken)): ?>
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                        <?php endif; ?>
                        <div class="col-md-4">
                            <label class="form-label">SERPAPI_KEY</label>
                            <input type="password" class="form-control font-monospace" name="SERPAPI_KEY" value="<?php echo htmlspecialchars($envAtual['SERPAPI_KEY'] ?? ''); ?>" placeholder="chave da SerpApi">
                            <small class="text-muted d-block">Atual: <?php echo htmlspecialchars($serpApiMask); ?></small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">COMPANY_WHATSAPP</label>
                            <input type="text" class="form-control font-monospace" name="COMPANY_WHATSAPP" value="<?php echo htmlspecialchars($envAtual['COMPANY_WHATSAPP'] ?? (defined('COMPANY_WHATSAPP') ? COMPANY_WHATSAPP : '')); ?>" placeholder="5500000000000">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">PROSPECCAO_URL_PRE_CADASTRO</label>
                            <input type="url" class="form-control font-monospace" name="PROSPECCAO_URL_PRE_CADASTRO" value="<?php echo htmlspecialchars($envAtual['PROSPECCAO_URL_PRE_CADASTRO'] ?? (defined('PROSPECCAO_URL_PRE_CADASTRO') ? PROSPECCAO_URL_PRE_CADASTRO : '')); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">PROSPECCAO_OFERTA_RECIPROCIDADE</label>
                            <input type="text" class="form-control" name="PROSPECCAO_OFERTA_RECIPROCIDADE" value="<?php echo htmlspecialchars($envAtual['PROSPECCAO_OFERTA_RECIPROCIDADE'] ?? (defined('PROSPECCAO_OFERTA_RECIPROCIDADE') ? PROSPECCAO_OFERTA_RECIPROCIDADE : '')); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">PROSPECCAO_CATEGORIAS_ALVO</label>
                            <input type="text" class="form-control font-monospace" name="PROSPECCAO_CATEGORIAS_ALVO" value="<?php echo htmlspecialchars($envAtual['PROSPECCAO_CATEGORIAS_ALVO'] ?? (defined('PROSPECCAO_CATEGORIAS_ALVO') ? PROSPECCAO_CATEGORIAS_ALVO : '')); ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Quota padrão</label>
                            <input type="number" class="form-control" name="PROSPECCAO_QUOTA_ALVO_PADRAO" value="<?php echo htmlspecialchars($envAtual['PROSPECCAO_QUOTA_ALVO_PADRAO'] ?? (string)(defined('PROSPECCAO_QUOTA_ALVO_PADRAO') ? PROSPECCAO_QUOTA_ALVO_PADRAO : 5)); ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Prioridade</label>
                            <input type="number" class="form-control" name="PROSPECCAO_PRIORIDADE_FUSEKI_PADRAO" value="<?php echo htmlspecialchars($envAtual['PROSPECCAO_PRIORIDADE_FUSEKI_PADRAO'] ?? (string)(defined('PROSPECCAO_PRIORIDADE_FUSEKI_PADRAO') ? PROSPECCAO_PRIORIDADE_FUSEKI_PADRAO : 100)); ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Raio padrão (km)</label>
                            <input type="number" class="form-control" name="PROSPECCAO_RAIO_PADRAO_KM" value="<?php echo htmlspecialchars($envAtual['PROSPECCAO_RAIO_PADRAO_KM'] ?? (string)(defined('PROSPECCAO_RAIO_PADRAO_KM') ? PROSPECCAO_RAIO_PADRAO_KM : 15)); ?>">
                        </div>
                        <div class="col-12">
                            <button class="btn btn-primary"><i class="fas fa-save me-1"></i>Salvar prospecção</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <!-- COLUNA ESQUERDA: Tarifas e Modo de Operação -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header"><i class="fas fa-sliders me-2"></i>Tarifas e Operação</div>
                <div class="card-body">
                    <form method="POST" action="<?php echo $bp; ?>/admin/configuracoes">
                        <?php if (!empty($csrfToken)): ?>
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                        <?php endif; ?>
                        <?php if ($cidadeIdConfig > 0): ?>
                        <input type="hidden" name="cidade_id" value="<?php echo (int)$cidadeIdConfig; ?>">
                        <?php endif; ?>

                        <!-- TARIFAS -->
                        <h6 class="mb-3 text-muted"><i class="fas fa-coins me-2"></i>Valores</h6>
                        <div class="mb-3">
                            <label class="form-label">Taxa por Km (R$)</label>
                            <input type="number" step="0.01" class="form-control" name="tarifa_por_km"
                                   value="<?php echo htmlspecialchars($config['tarifa_por_km'] ?? '5.00'); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Taxa Fixa (R$)</label>
                            <input type="number" step="0.01" class="form-control" name="taxa_fixa"
                                   value="<?php echo htmlspecialchars($config['taxa_fixa'] ?? '10.00'); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Comissão Plataforma (decimal 0 a 1) — incide sobre o valor JÁ LÍQUIDO, depois da reserva de gateway abaixo</label>
                            <input type="number" step="0.01" class="form-control" min="0.01" max="0.99" name="comissao_plataforma"
                                   value="<?php echo htmlspecialchars($config['comissao_plataforma'] ?? '0.20'); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Reserva de gateway (decimal 0 a 1) — média conservadora descontada do bruto antes de calcular comissão/repasse</label>
                            <input type="number" step="0.001" class="form-control" min="0" max="0.5" name="reserva_gateway_percentual"
                                   value="<?php echo htmlspecialchars($config['reserva_gateway_percentual'] ?? '0.045'); ?>">
                            <small class="text-muted d-block">
                                Ex.: 0.045 = 4,5%. Comissão + repasse ao prestador somam 100% do valor JÁ DESCONTADO
                                dessa reserva — evita que comissão+repasse+taxa do gateway ultrapassem o valor recebido.
                            </small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Crédito de conversão pane→reboque (decimal 0 a 1)</label>
                            <input type="number" step="0.01" class="form-control" min="0" max="1" name="credito_conversao_percentual"
                                   value="<?php echo htmlspecialchars($config['credito_conversao_percentual'] ?? '0.30'); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Crédito de conversão — limite máximo (R$)</label>
                            <input type="number" step="0.01" class="form-control" min="0" name="credito_conversao_maximo"
                                   value="<?php echo htmlspecialchars($config['credito_conversao_maximo'] ?? '40.00'); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Limite diário por gateway antes de rotacionar (R$)</label>
                            <input type="number" step="0.01" class="form-control" min="0" name="gateway_rotacao_limite_diario"
                                   value="<?php echo htmlspecialchars($config['gateway_rotacao_limite_diario'] ?? '10000'); ?>">
                            <small class="text-muted d-block">
                                Quando o gateway ativo (<?php echo htmlspecialchars(defined('PAYMENT_GATEWAY_ACTIVE') ? PAYMENT_GATEWAY_ACTIVE : ''); ?>)
                                receber mais que este valor no dia, novos checkouts passam automaticamente para o outro gateway
                                configurado. Só se aplica quando PAYMENT_GATEWAY_ACTIVE é um único gateway (não "todos").
                            </small>
                        </div>

                        <hr class="config-hr">
                        <hr class="config-hr">

                        <!-- TARIFAS ESPECIAIS -->
                        <h6 class="mb-3 text-muted"><i class="fas fa-money-bill-wave me-2"></i>Tarifas Especiais</h6>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Tarifa Noturna (R$/KM)</label>
                                <input type="number" step="0.01" class="form-control" name="tarifa_noturna_km"
                                       value="<?php echo htmlspecialchars($config['tarifa_noturna_km'] ?? '5.50'); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Taxa Fixa Noturna (R$)</label>
                                <input type="number" step="0.01" class="form-control" name="tarifa_noturna_fixa"
                                       value="<?php echo htmlspecialchars($config['tarifa_noturna_fixa'] ?? '15.00'); ?>">
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label">Taxa de Prioridade (R$)</label>
                                <input type="number" step="0.01" class="form-control" name="taxa_prioridade"
                                       value="<?php echo htmlspecialchars($config['taxa_prioridade'] ?? '20.00'); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Início Turno Noturno (HH:MM)</label>
                                <input type="time" class="form-control" name="turno_noturno_inicio"
                                       value="<?php echo htmlspecialchars($config['turno_noturno_inicio'] ?? '20:00'); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Fim Turno Noturno (HH:MM)</label>
                                <input type="time" class="form-control" name="turno_noturno_fim"
                                       value="<?php echo htmlspecialchars($config['turno_noturno_fim'] ?? '06:00'); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Adicional Feriado (R$/KM)</label>
                                <input type="number" step="0.01" class="form-control" name="tarifa_feriado_km"
                                       value="<?php echo htmlspecialchars($config['tarifa_feriado_km'] ?? '5.50'); ?>">
                                <small class="text-muted d-block">Empilha com o adicional noturno se coincidirem. Datas em <a href="<?php echo $bp; ?>/admin/feriados">Feriados</a>.</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Adicional Feriado (Taxa Fixa R$)</label>
                                <input type="number" step="0.01" class="form-control" name="tarifa_feriado_fixa"
                                       value="<?php echo htmlspecialchars($config['tarifa_feriado_fixa'] ?? '15.00'); ?>">
                            </div>
                        </div>

                        <hr class="config-hr">

                        <!-- TARIFA POR CATEGORIA DE VEÍCULO -->
                        <h6 class="mb-3 text-muted"><i class="fas fa-car-side me-2"></i>Tarifa por Categoria de Veículo</h6>
                        <p class="text-muted small">Valor base (Taxa por Km / Taxa Fixa, acima) vale para a categoria "Popular". As demais categorias usam os valores próprios abaixo.</p>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">SUV (R$/KM)</label>
                                <input type="number" step="0.01" class="form-control" name="tarifa_suv_km"
                                       value="<?php echo htmlspecialchars($config['tarifa_suv_km'] ?? '4.20'); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">SUV (Taxa Fixa R$)</label>
                                <input type="number" step="0.01" class="form-control" name="tarifa_suv_fixa"
                                       value="<?php echo htmlspecialchars($config['tarifa_suv_fixa'] ?? '12.00'); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Caminhonete/Utilitário (R$/KM)</label>
                                <input type="number" step="0.01" class="form-control" name="tarifa_caminhonete_km"
                                       value="<?php echo htmlspecialchars($config['tarifa_caminhonete_km'] ?? '4.80'); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Caminhonete/Utilitário (Taxa Fixa R$)</label>
                                <input type="number" step="0.01" class="form-control" name="tarifa_caminhonete_fixa"
                                       value="<?php echo htmlspecialchars($config['tarifa_caminhonete_fixa'] ?? '14.00'); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Elétrico (R$/KM)</label>
                                <input type="number" step="0.01" class="form-control" name="tarifa_eletrico_km"
                                       value="<?php echo htmlspecialchars($config['tarifa_eletrico_km'] ?? '3.50'); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Elétrico (Taxa Fixa R$)</label>
                                <input type="number" step="0.01" class="form-control" name="tarifa_eletrico_fixa"
                                       value="<?php echo htmlspecialchars($config['tarifa_eletrico_fixa'] ?? '10.00'); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Moto (R$/KM)</label>
                                <input type="number" step="0.01" class="form-control" name="tarifa_moto_km"
                                       value="<?php echo htmlspecialchars($config['tarifa_moto_km'] ?? '6.00'); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Moto (Taxa Fixa R$)</label>
                                <input type="number" step="0.01" class="form-control" name="tarifa_moto_fixa"
                                       value="<?php echo htmlspecialchars($config['tarifa_moto_fixa'] ?? '150.00'); ?>">
                            </div>
                        </div>

                        <hr class="config-hr">

                        <!-- MODO DE OPERAÇÃO -->
                        <h6 class="mb-3 text-muted"><i class="fas fa-server me-2"></i>Modo de Operação</h6>
                        <div class="mb-3">
                            <label class="form-label fw-bold">System Mode</label>
                            <div class="mt-2">
                                <?php 
                                    $currentMode = $config['system_mode'] ?? 'production';
                                ?>
                                <div class="form-check config-mode-option">
                                    <input class="form-check-input" type="radio" name="system_mode" id="mode_production" value="production"
                                        <?php echo $currentMode === 'production' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="mode_production">
                                        <span class="badge bg-success">Produção</span>
                                        <small class="text-muted d-block">Ambiente real com pagamentos processados</small>
                                    </label>
                                </div>
                                <div class="form-check mt-2 config-mode-option">
                                    <input class="form-check-input" type="radio" name="system_mode" id="mode_sandbox" value="sandbox"
                                        <?php echo $currentMode === 'sandbox' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="mode_sandbox">
                                        <span class="badge bg-warning text-dark">Sandbox</span>
                                        <small class="text-muted d-block">Ambiente de testes com dados simulados</small>
                                    </label>
                                </div>
                                <div class="form-check mt-2 config-mode-option">
                                    <input class="form-check-input" type="radio" name="system_mode" id="mode_freeflow" value="freeflow"
                                        <?php echo $currentMode === 'freeflow' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="mode_freeflow">
                                        <span class="badge bg-info text-dark">Fluxo Livre</span>
                                        <small class="text-muted d-block">Sem validações de pagamento, fluxo contínuo</small>
                                    </label>
                                </div>
                            </div>
                            <div class="form-text text-muted mt-2">
                                <i class="fas fa-info-circle"></i> 
                                <span class="d-block mb-1">Selecione uma opção e clique em <strong>Salvar Configurações</strong>. O System Mode controla o fluxo da plataforma; o ambiente do Mercado Pago é ajustado separadamente em <strong>Gateway de Pagamento → MP_ENV</strong>.</span>
                                <strong>Produção:</strong> Pagamentos reais via gateway<br>
                                <strong>Sandbox:</strong> Testes com cartões e PIX simulados<br>
                                <strong>Fluxo Livre:</strong> Sem exigência de pagamento antecipado
                            </div>
                        </div>

                        <hr class="config-hr">

                        <!-- PAGAMENTO OBRIGATÓRIO -->
                        <h6 class="mb-3 text-muted"><i class="fas fa-lock me-2"></i>Segurança</h6>
                        <div class="mb-3">
                            <div class="form-check form-switch config-payment-option">
                                <input class="form-check-input" type="checkbox" name="payment_required" id="payment_required" value="1"
                                    <?php echo (($config['payment_required'] ?? '1') == '1') ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="payment_required">
                                    <strong>Exigir pagamento antecipado</strong>
                                    <small class="d-block text-muted">Se desativado, clientes podem solicitar serviço sem pagamento prévio</small>
                                </label>
                            </div>
                        </div>

                        <hr class="config-hr">

                        <!-- MODO DE DEBUG GLOBAL -->
                        <h6 class="mb-3 text-muted"><i class="fas fa-bug me-2"></i>Observabilidade</h6>
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="debug_mode_ativo" id="debug_mode_ativo" value="1"
                                    <?php echo (($config['debug_mode_ativo'] ?? '0') == '1') ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="debug_mode_ativo">
                                    <strong>Modo de debug global</strong>
                                    <small class="d-block text-muted">Liga logs verbosos em todo o sistema (backend e frontend): sistema, classe, função e localização exata de cada evento/erro — nos logs do servidor e no console do navegador. Recomendado só durante diagnóstico, não em produção normal.</small>
                                </label>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 mt-3">
                            <i class="fas fa-save me-2"></i>Salvar Configurações
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- COLUNA DIREITA: Gateway e Informações -->
        <div class="col-lg-6">
            <div class="card mb-4">
                <div class="card-header"><i class="fas fa-credit-card me-2"></i>Gateway de Pagamento</div>
                <div class="card-body">
                    <form method="POST" action="<?php echo $bp; ?>/admin/configuracoes">
                        <?php if (!empty($csrfToken)): ?>
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                        <?php endif; ?>
                        <div class="mb-3">
                            <label class="form-label">Gateway ativo</label>
                            <select class="form-select" name="gateway_pagamento">
                                <option value="mercadopago" <?php echo ($envAtual["PAYMENT_GATEWAY_ACTIVE"] ?? ($config["gateway_pagamento"] ?? "mercadopago")) === "mercadopago" ? "selected" : ""; ?>>Mercado Pago</option>
                                <option value="pagseguro"   <?php echo ($envAtual["PAYMENT_GATEWAY_ACTIVE"] ?? ($config["gateway_pagamento"] ?? "")) === "pagseguro" ? "selected" : ""; ?>>PagSeguro</option>
                            </select>
                            <div class="form-text">Define qual gateway será oferecido na tela de checkout.</div>
                        </div>

                        <div class="row g-3">
                            <div class="col-12"><h6 class="mt-2 mb-0">Mercado Pago</h6></div>
                            <div class="col-md-6">
                                <label class="form-label">MP_ACCESS_TOKEN</label>
                                <input type="text" class="form-control font-monospace" name="MP_ACCESS_TOKEN" value="<?php echo htmlspecialchars($envAtual["MP_ACCESS_TOKEN"] ?? ""); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">MP_PUBLIC_KEY</label>
                                <input type="text" class="form-control font-monospace" name="MP_PUBLIC_KEY" value="<?php echo htmlspecialchars($envAtual["MP_PUBLIC_KEY"] ?? ""); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">MP_WEBHOOK_SECRET</label>
                                <input type="text" class="form-control font-monospace" name="MP_WEBHOOK_SECRET" value="<?php echo htmlspecialchars($envAtual["MP_WEBHOOK_SECRET"] ?? ""); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">MP_ENV</label>
                                <select class="form-select" name="MP_ENV">
                                    <option value="sandbox" <?php echo ($envAtual["MP_ENV"] ?? "production") === "sandbox" ? "selected" : ""; ?>>sandbox</option>
                                    <option value="production" <?php echo ($envAtual["MP_ENV"] ?? "production") === "production" ? "selected" : ""; ?>>production</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">MP_ACCESS_TOKEN_SANDBOX</label>
                                <input type="text" class="form-control font-monospace" name="MP_ACCESS_TOKEN_SANDBOX" value="<?php echo htmlspecialchars($envAtual["MP_ACCESS_TOKEN_SANDBOX"] ?? ""); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">MP_ACCESS_TOKEN_PROD</label>
                                <input type="text" class="form-control font-monospace" name="MP_ACCESS_TOKEN_PROD" value="<?php echo htmlspecialchars($envAtual["MP_ACCESS_TOKEN_PROD"] ?? ""); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">MP_PUBLIC_KEY_SANDBOX</label>
                                <input type="text" class="form-control font-monospace" name="MP_PUBLIC_KEY_SANDBOX" value="<?php echo htmlspecialchars($envAtual["MP_PUBLIC_KEY_SANDBOX"] ?? ""); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">MP_PUBLIC_KEY_PROD</label>
                                <input type="text" class="form-control font-monospace" name="MP_PUBLIC_KEY_PROD" value="<?php echo htmlspecialchars($envAtual["MP_PUBLIC_KEY_PROD"] ?? ""); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">MP_CLIENT_ID_PROD</label>
                                <input type="text" class="form-control font-monospace" name="MP_CLIENT_ID_PROD" value="<?php echo htmlspecialchars($envAtual["MP_CLIENT_ID_PROD"] ?? ""); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">MP_CLIENT_SECRET_PROD</label>
                                <input type="password" class="form-control font-monospace" name="MP_CLIENT_SECRET_PROD" value="<?php echo htmlspecialchars($envAtual["MP_CLIENT_SECRET_PROD"] ?? ""); ?>">
                            </div>

                            <div class="col-12"><h6 class="mt-3 mb-0">PagSeguro</h6></div>
                            <div class="col-md-6">
                                <label class="form-label">PS_EMAIL</label>
                                <input type="email" class="form-control font-monospace" name="PS_EMAIL" value="<?php echo htmlspecialchars($envAtual["PS_EMAIL"] ?? ""); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">PS_TOKEN</label>
                                <input type="text" class="form-control font-monospace" name="PS_TOKEN" value="<?php echo htmlspecialchars($envAtual["PS_TOKEN"] ?? ""); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">PS_ENV</label>
                                <select class="form-select" name="PS_ENV">
                                    <option value="sandbox" <?php echo ($envAtual["PS_ENV"] ?? "sandbox") === "sandbox" ? "selected" : ""; ?>>sandbox</option>
                                    <option value="production" <?php echo ($envAtual["PS_ENV"] ?? "sandbox") === "production" ? "selected" : ""; ?>>production</option>
                                </select>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 mt-3">
                            <i class="fas fa-save me-2"></i>Salvar Gateway
                        </button>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><i class="fas fa-info-circle me-2"></i>Informações</div>
                <div class="card-body">
                    <p class="config-info-text">
                        <i class="fas fa-check-circle text-success me-1"></i> 
                        Alterações nas configurações de tarifa serão aplicadas em todos os novos pedidos imediatamente.
                        Pedidos já criados não serão afetados.
                    </p>
                    <hr class="config-hr">
                    <p class="config-info-text">
                        <i class="fas fa-server me-1"></i>
                        <strong>Modo atual:</strong> 
                        <span class="badge <?php 
                            echo ($config['system_mode'] ?? 'production') === 'production' ? 'bg-success' : 
                                (($config['system_mode'] ?? 'production') === 'sandbox' ? 'bg-warning text-dark' : 'bg-info text-dark'); 
                        ?>">
                            <?php echo ucfirst($config['system_mode'] ?? 'production'); ?>
                        </span>
                    </p>
                    <p class="config-info-text">
                        <i class="fas fa-lock me-1"></i>
                        <strong>Pagamento obrigatório:</strong> 
                        <span class="badge <?php echo (($config['payment_required'] ?? '1') == '1') ? 'bg-danger' : 'bg-secondary'; ?>">
                            <?php echo (($config['payment_required'] ?? '1') == '1') ? 'Sim' : 'Não'; ?>
                        </span>
                    </p>
                    <hr class="config-hr">
                    <small class="config-info-small">Última alteração: <?php echo date('d/m/Y H:i'); ?></small>
                </div>
            </div>
        </div>
    </div>

</main>
<script<?php echo csp_script_nonce_attr(); ?> defer src="<?php echo htmlspecialchars($bp); ?>/public/assets/js/admin-configuracoes.js?v=20260813-2"></script>
<?php include __DIR__ . '/../layouts/footer.php'; ?>

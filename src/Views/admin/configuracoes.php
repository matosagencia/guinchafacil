<?php

$bp = defined('BASE_PATH') ? BASE_PATH : '';

include __DIR__ . '/../layouts/header.php';

$sensivelEnv = ['DB_PASS', 'SMTP_PASS', 'GOOGLE_CLIENT_SECRET', 'ENCRYPTION_KEY', 'SIMULATION_ADMIN_TOKEN'];
$gruposEnv = [
    'AplicaÃ§Ã£o' => ['APP_NAME', 'APP_URL', 'APP_ENV', 'APP_DEBUG', 'HTTPS_ONLY', 'FORCE_BASEPATH'],
    'Banco de Dados' => ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'],
    'Institucional' => ['COMPANY_ADDRESS', 'ADMIN_EMAIL'],
    'SMTP / Email' => ['SMTP_HOST', 'SMTP_PORT', 'SMTP_USER', 'SMTP_PASS', 'SMTP_FROM_EMAIL', 'SMTP_FROM_NAME'],
    'Google OAuth' => ['GOOGLE_CLIENT_ID', 'GOOGLE_CLIENT_SECRET', 'GOOGLE_REDIRECT_URI'],
    'Simulado / Testes' => ['SIMULATION_ENABLED', 'PIX_DRY_RUN', 'SIMULATION_ADMIN_TOKEN'],
    'Operacional' => ['MAX_PIX_TENTATIVAS', 'GEOCODING_CACHE_TTL_DAYS', 'TARIFA_BASE', 'TARIFA_KM', 'ENCRYPTION_KEY'],
    'Log do Sistema' => ['SYSTEM_LOG_ENABLED'],
];

function maskEnvValue(string $value): string {
    if ($value === '') {
        return 'nÃ£o definido';
    }
    $len = strlen($value);
    if ($len <= 6) {
        return str_repeat('*', $len);
    }
    return substr($value, 0, 3) . str_repeat('*', max(4, $len - 6)) . substr($value, -3);
}
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars($bp); ?>/public/assets/css/pages/admin-configuracoes.css?v=20260813-2">

<div class="main-wrapper shell admin-shell">

<?php include __DIR__ . '/../layouts/sidebar_admin.php'; ?>

<main class="main-content shell-main shell-content">



    <header class="page-head mb-4">

        <div>

            <span class="eyebrow">GestÃ£o</span>

            <h1><i class="fas fa-gear me-2 text-primary-custom"></i>ConfiguraÃ§Ãµes do Sistema</h1>

            <p>ParÃ¢metros de tarifas, comissÃµes e modo de operaÃ§Ã£o</p>

        </div>

    </header>



    <?php if ((isset($_GET['salvo']) && $_GET['salvo']) || (($msg ?? '') === 'salvo')): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>ConfiguraÃ§Ãµes salvas com sucesso!
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (((isset($_GET['erro']) && $_GET['erro'] === 'env_write') || (($msg ?? '') === 'erro_write'))): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i>Erro ao salvar arquivo .env. Verifique as permissÃµes.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (($msg ?? '') === 'erro_parse'): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i>Erro de validaÃ§Ã£o: arquivo .env invÃ¡lido. AlteraÃ§Ãµes revertidas.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (($msg ?? '') === 'erro_validacao'): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i>Ambiente rejeitado por validaÃ§Ã£o de seguranÃ§a.
            <?php if (!empty($envErrors)): ?>
                <div class="small mt-2"><?php echo htmlspecialchars(implode('; ', $envErrors)); ?></div>
            <?php endif; ?>
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
            : 'nÃ£o configurada';
    ?>
    <div class="card mb-4 config-effective-card">

        <div class="card-header"><i class="fas fa-eye me-2"></i>ConfiguraÃ§Ã£o efetiva carregada</div>

        <div class="card-body">

            <p class="config-info-text mb-3">Valores abaixo sÃ£o somente leitura e mostram o que o sistema encontrou no arquivo <code><?php echo htmlspecialchars($envArquivoAtivo ?? '.env'); ?></code> e no banco de dados.</p>

            <div class="row g-2 config-effective-grid">

                <div class="col-md-4"><span class="config-effective-label">System Mode <small>(banco)</small></span><strong><?php echo htmlspecialchars($modoAtual); ?></strong></div>

                <div class="col-md-4"><span class="config-effective-label">Pagamento antecipado <small>(banco)</small></span><strong class="<?php echo $pagamentoAntecipadoAtual ? 'text-success' : 'text-warning'; ?>"><?php echo $pagamentoAntecipadoAtual ? 'ATIVADO' : 'DESATIVADO'; ?></strong></div>

                <div class="col-md-4"><span class="config-effective-label">Gateway ativo <small>(arquivo/banco)</small></span><strong><?php echo htmlspecialchars($gatewayAtual); ?></strong></div>
                <div class="col-md-4"><span class="config-effective-label">Mercado Pago <small>(arquivo)</small></span><strong><?php echo htmlspecialchars($mpEnvAtual); ?></strong></div>
                <div class="col-md-4"><span class="config-effective-label">PagSeguro <small>(arquivo)</small></span><strong><?php echo htmlspecialchars($psEnvAtual); ?></strong></div>
                <div class="col-md-4"><span class="config-effective-label">SMTP <small>(arquivo)</small></span><strong><?php echo htmlspecialchars((string)($envAtual['SMTP_HOST'] ?? 'nÃ£o configurado')); ?>:<?php echo htmlspecialchars((string)($envAtual['SMTP_PORT'] ?? '')); ?></strong></div>
                <div class="col-md-4"><span class="config-effective-label">WhatsApp empresa <small>(arquivo)</small></span><strong><?php echo htmlspecialchars((string)($envAtual['COMPANY_WHATSAPP'] ?? 'nÃ£o configurado')); ?></strong></div>
                <div class="col-md-4"><span class="config-effective-label">SerpApi <small>(arquivo)</small></span><strong><?php echo htmlspecialchars($serpApiMask); ?></strong></div>
                <div class="col-md-4"><span class="config-effective-label">PrÃ©-cadastro <small>(arquivo)</small></span><strong><?php echo htmlspecialchars((string)($envAtual['PROSPECCAO_URL_PRE_CADASTRO'] ?? '')); ?></strong></div>
            </div>
        </div>
    </div>
    <?php if (!empty($cidadesAtivas)): ?>

    <div class="card mb-4">

        <div class="card-body d-flex flex-wrap align-items-center gap-3">

            <label class="form-label mb-0 fw-bold"><i class="fas fa-city me-1"></i>Editando tarifas de:</label>

            <select class="form-select w-auto" id="configCidadeSeletor" onchange="window.location.href = <?php echo json_encode($bp . '/admin/configuracoes'); ?> + (this.value ? '?cidade_id=' + this.value : '');">

                <option value="">Global (padrÃ£o de todas as cidades)</option>

                <?php foreach ($cidadesAtivas as $c): ?>

                <option value="<?php echo (int)$c['id']; ?>" <?php echo $cidadeIdConfig === (int)$c['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($c['nome'] . '/' . $c['uf']); ?></option>

                <?php endforeach; ?>

            </select>

            <?php if ($cidadeIdConfig > 0): ?>

            <span class="text-muted small"><i class="fas fa-circle-info me-1"></i>SÃ³ os valores de <strong>Tarifas e OperaÃ§Ã£o</strong> (seÃ§Ã£o abaixo) sÃ£o especÃ­ficos desta cidade â€” comissÃ£o, gateway e demais configuraÃ§Ãµes continuam globais.</span>

            <?php endif; ?>

        </div>

    </div>

    <?php endif; ?>



    <div class="row g-4">
        <div class="col-12">
            <div class="card border-success"><div class="card-header"><i class="fas fa-chart-line me-2"></i>Marketing e conversÃµes</div><div class="card-body">
                <p class="text-muted small">As tags sÃ³ carregam quando ativadas e apÃ³s o visitante aceitar cookies de marketing. NÃ£o coloque tokens secretos aqui.</p>
                <form method="POST" action="<?php echo $bp; ?>/admin/configuracoes" class="row g-3"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken ?? ''); ?>">
                    <div class="col-md-3"><label class="form-label">Rastreamento</label><select class="form-select" name="marketing_tracking_enabled"><option value="0" <?php echo ($config['marketing_tracking_enabled'] ?? '0') !== '1' ? 'selected' : ''; ?>>Desativado</option><option value="1" <?php echo ($config['marketing_tracking_enabled'] ?? '0') === '1' ? 'selected' : ''; ?>>Ativado</option></select></div>
                    <div class="col-md-3"><label class="form-label">Google Ads ID</label><input class="form-control" name="marketing_google_ads_id" placeholder="AW-123456789" value="<?php echo htmlspecialchars($config['marketing_google_ads_id'] ?? 'AW-18387802162'); ?>"></div>
                    <div class="col-md-3"><label class="form-label">Label de conversÃ£o</label><input class="form-control" name="marketing_google_ads_conversion_label" value="<?php echo htmlspecialchars($config['marketing_google_ads_conversion_label'] ?? ''); ?>"></div>
                    <div class="col-md-3"><label class="form-label">GA4 Measurement ID</label><input class="form-control" name="marketing_ga4_measurement_id" placeholder="G-XXXXXXXXXX" value="<?php echo htmlspecialchars($config['marketing_ga4_measurement_id'] ?? 'G-0FFGZ5G576'); ?>"></div>
                    <div class="col-md-3"><label class="form-label">Meta Pixel ID</label><input class="form-control" name="marketing_meta_pixel_id" placeholder="Somente nÃºmeros" value="<?php echo htmlspecialchars($config['marketing_meta_pixel_id'] ?? ''); ?>"></div>
                    <div class="col-12"><button class="btn btn-success"><i class="fas fa-save me-1"></i>Salvar marketing</button></div>
                </form>
            </div></div>
        </div>
        <div class="col-12">
            <div class="card border-primary">
                <div class="card-header"><i class="fas fa-user-plus me-2"></i>ProspecÃ§Ã£o de parceiros</div>
                <div class="card-body">
                    <p class="text-muted small">Esses campos alimentam a central de marketing e a busca de leads via SerpApi. A chave fica no .env e Ã© gravada pelo prÃ³prio painel.</p>
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
                            <label class="form-label">Quota padrÃ£o</label>
                            <input type="number" class="form-control" name="PROSPECCAO_QUOTA_ALVO_PADRAO" value="<?php echo htmlspecialchars($envAtual['PROSPECCAO_QUOTA_ALVO_PADRAO'] ?? (string)(defined('PROSPECCAO_QUOTA_ALVO_PADRAO') ? PROSPECCAO_QUOTA_ALVO_PADRAO : 5)); ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Prioridade</label>
                            <input type="number" class="form-control" name="PROSPECCAO_PRIORIDADE_FUSEKI_PADRAO" value="<?php echo htmlspecialchars($envAtual['PROSPECCAO_PRIORIDADE_FUSEKI_PADRAO'] ?? (string)(defined('PROSPECCAO_PRIORIDADE_FUSEKI_PADRAO') ? PROSPECCAO_PRIORIDADE_FUSEKI_PADRAO : 100)); ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Raio padrÃ£o (km)</label>
                            <input type="number" class="form-control" name="PROSPECCAO_RAIO_PADRAO_KM" value="<?php echo htmlspecialchars($envAtual['PROSPECCAO_RAIO_PADRAO_KM'] ?? (string)(defined('PROSPECCAO_RAIO_PADRAO_KM') ? PROSPECCAO_RAIO_PADRAO_KM : 15)); ?>">
                        </div>
                        <div class="col-12">
                            <button class="btn btn-primary"><i class="fas fa-save me-1"></i>Salvar prospecÃ§Ã£o</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <!-- COLUNA ESQUERDA: Tarifas e Modo de OperaÃ§Ã£o -->
        <div class="col-12">
            <div class="card">
                <div class="card-header"><i class="fas fa-sliders me-2"></i>Tarifas e OperaÃ§Ã£o</div>

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

                            <label class="form-label">Taxa Fixa / Bandeirada do Reboque (R$)</label>
                            <input type="number" step="0.01" class="form-control" name="taxa_fixa"

                                   value="<?php echo htmlspecialchars($config['taxa_fixa'] ?? '10.00'); ?>">

                        </div>

                        <div class="mb-3">

                            <label class="form-label">ComissÃ£o Plataforma (decimal 0 a 1) â€” incide sobre o valor JÃ LÃQUIDO, depois da reserva de gateway abaixo</label>

                            <input type="number" step="0.01" class="form-control" min="0.01" max="0.99" name="comissao_plataforma"

                                   value="<?php echo htmlspecialchars($config['comissao_plataforma'] ?? '0.20'); ?>">

                        </div>

                        <div class="mb-3">

                            <label class="form-label">Custo Saída Profissional Padrão (R$)</label>

                            <input type="number" step="0.01" class="form-control" name="custo_saida_profissional_padrao"

                                   value="<?php echo htmlspecialchars($config['custo_saida_profissional_padrao'] ?? '80.00'); ?>">

                        </div>

                        <div class="mb-3">

                            <label class="form-label">Comissão Assistência (decimal 0 a 1)</label>

                            <input type="number" step="0.01" class="form-control" min="0.01" max="0.99" name="comissao_assistencia_percentual"

                                   value="<?php echo htmlspecialchars($config['comissao_assistencia_percentual'] ?? '0.21'); ?>">

                        </div>

                        <div class="mb-3">

                            <label class="form-label">Reserva de gateway (decimal 0 a 1) — média conservadora descontada do bruto antes de calcular comissão/repasse</label>

                            <input type="number" step="0.001" class="form-control" min="0" max="0.5" name="reserva_gateway_percentual"

                                   value="<?php echo htmlspecialchars($config['reserva_gateway_percentual'] ?? '0.045'); ?>">

                            <small class="text-muted d-block">

                                Ex.: 0.045 = 4,5%. ComissÃ£o + repasse ao prestador somam 100% do valor JÃ DESCONTADO

                                dessa reserva â€” evita que comissÃ£o+repasse+taxa do gateway ultrapassem o valor recebido.

                            </small>

                        </div>

                        <div class="mb-3">

                            <label class="form-label">CrÃ©dito de conversÃ£o paneâ†’reboque (decimal 0 a 1)</label>

                            <input type="number" step="0.01" class="form-control" min="0" max="1" name="credito_conversao_percentual"

                                   value="<?php echo htmlspecialchars($config['credito_conversao_percentual'] ?? '0.30'); ?>">

                        </div>

                        <div class="mb-3">

                            <label class="form-label">CrÃ©dito de conversÃ£o â€” limite mÃ¡ximo (R$)</label>

                            <input type="number" step="0.01" class="form-control" min="0" name="credito_conversao_maximo"

                                   value="<?php echo htmlspecialchars($config['credito_conversao_maximo'] ?? '40.00'); ?>">

                        </div>

                        <div class="mb-3">

                            <label class="form-label">Limite diÃ¡rio por gateway antes de rotacionar (R$)</label>

                            <input type="number" step="0.01" class="form-control" min="0" name="gateway_rotacao_limite_diario"

                                   value="<?php echo htmlspecialchars($config['gateway_rotacao_limite_diario'] ?? '10000'); ?>">

                            <small class="text-muted d-block">

                                Quando o gateway ativo (<?php echo htmlspecialchars(defined('PAYMENT_GATEWAY_ACTIVE') ? PAYMENT_GATEWAY_ACTIVE : ''); ?>)

                                receber mais que este valor no dia, novos checkouts passam automaticamente para o outro gateway

                                configurado. SÃ³ se aplica quando PAYMENT_GATEWAY_ACTIVE Ã© um Ãºnico gateway (nÃ£o "todos").

                            </small>

                        </div>



                        <hr class="config-hr">

                        <hr class="config-hr">



                        <!-- TARIFAS ESPECIAIS -->

                        <h6 class="mb-3 text-muted"><i class="fas fa-money-bill-wave me-2"></i>Tarifas Especiais</h6>

                        <div class="row">

                            <div class="col-12 mb-3">
                                <label class="form-label">Tarifa Noturna (R$/KM)</label>
                                <input type="number" step="0.01" class="form-control" name="tarifa_noturna_km"
                                       value="<?php echo htmlspecialchars($config['tarifa_noturna_km'] ?? '5.50'); ?>">
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label">Taxa Fixa Noturna (R$)</label>
                                <input type="number" step="0.01" class="form-control" name="tarifa_noturna_fixa"
                                       value="<?php echo htmlspecialchars($config['tarifa_noturna_fixa'] ?? '15.00'); ?>">
                            </div>
                            <div class="col-12 mb-3">

                                <label class="form-label">Taxa de Prioridade (R$)</label>

                                <input type="number" step="0.01" class="form-control" name="taxa_prioridade"

                                       value="<?php echo htmlspecialchars($config['taxa_prioridade'] ?? '20.00'); ?>">

                            </div>

                            <div class="col-12 mb-3">
                                <label class="form-label">InÃ­cio Turno Noturno (HH:MM)</label>
                                <input type="time" class="form-control" name="turno_noturno_inicio"
                                       value="<?php echo htmlspecialchars($config['turno_noturno_inicio'] ?? '20:00'); ?>">
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label">Fim Turno Noturno (HH:MM)</label>
                                <input type="time" class="form-control" name="turno_noturno_fim"
                                       value="<?php echo htmlspecialchars($config['turno_noturno_fim'] ?? '06:00'); ?>">
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label">Adicional Feriado (R$/KM)</label>
                                <input type="number" step="0.01" class="form-control" name="tarifa_feriado_km"
                                       value="<?php echo htmlspecialchars($config['tarifa_feriado_km'] ?? '5.50'); ?>">
                                <small class="text-muted d-block">Empilha com o adicional noturno se coincidirem. Datas em <a href="<?php echo $bp; ?>/admin/feriados">Feriados</a>.</small>
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label">Adicional Feriado (Taxa Fixa R$)</label>
                                <input type="number" step="0.01" class="form-control" name="tarifa_feriado_fixa"
                                       value="<?php echo htmlspecialchars($config['tarifa_feriado_fixa'] ?? '15.00'); ?>">
                            </div>
                        </div>



                        <hr class="config-hr">



                        <!-- TARIFA POR CATEGORIA DE VEÃCULO -->

                        <h6 class="mb-3 text-muted"><i class="fas fa-car-side me-2"></i>Tarifa por Categoria de VeÃ­culo</h6>

                        <p class="text-muted small">Valor base (Taxa por Km / Taxa Fixa, acima) vale para a categoria "Popular". As demais categorias usam os valores prÃ³prios abaixo.</p>

                        <div class="row">

                            <div class="col-12 mb-3">
                                <label class="form-label">SUV (R$/KM)</label>
                                <input type="number" step="0.01" class="form-control" name="tarifa_suv_km"
                                       value="<?php echo htmlspecialchars($config['tarifa_suv_km'] ?? '4.20'); ?>">
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label">SUV (Taxa Fixa R$)</label>
                                <input type="number" step="0.01" class="form-control" name="tarifa_suv_fixa"
                                       value="<?php echo htmlspecialchars($config['tarifa_suv_fixa'] ?? '12.00'); ?>">
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label">Caminhonete/UtilitÃ¡rio (R$/KM)</label>
                                <input type="number" step="0.01" class="form-control" name="tarifa_caminhonete_km"
                                       value="<?php echo htmlspecialchars($config['tarifa_caminhonete_km'] ?? '4.80'); ?>">
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label">Caminhonete/UtilitÃ¡rio (Taxa Fixa R$)</label>
                                <input type="number" step="0.01" class="form-control" name="tarifa_caminhonete_fixa"
                                       value="<?php echo htmlspecialchars($config['tarifa_caminhonete_fixa'] ?? '14.00'); ?>">
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label">ElÃ©trico (R$/KM)</label>
                                <input type="number" step="0.01" class="form-control" name="tarifa_eletrico_km"
                                       value="<?php echo htmlspecialchars($config['tarifa_eletrico_km'] ?? '3.50'); ?>">
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label">ElÃ©trico (Taxa Fixa R$)</label>
                                <input type="number" step="0.01" class="form-control" name="tarifa_eletrico_fixa"
                                       value="<?php echo htmlspecialchars($config['tarifa_eletrico_fixa'] ?? '10.00'); ?>">
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label">Moto (R$/KM)</label>
                                <input type="number" step="0.01" class="form-control" name="tarifa_moto_km"
                                       value="<?php echo htmlspecialchars($config['tarifa_moto_km'] ?? '6.00'); ?>">
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label">Moto (Taxa Fixa R$)</label>
                                <input type="number" step="0.01" class="form-control" name="tarifa_moto_fixa"
                                       value="<?php echo htmlspecialchars($config['tarifa_moto_fixa'] ?? '150.00'); ?>">
                            </div>
                        </div>



                        <hr class="config-hr">



                        <!-- MODO DE OPERAÃ‡ÃƒO -->

                        <h6 class="mb-3 text-muted"><i class="fas fa-server me-2"></i>Modo de OperaÃ§Ã£o</h6>

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

                                        <span class="badge bg-success">ProduÃ§Ã£o</span>

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

                                        <small class="text-muted d-block">Sem validaÃ§Ãµes de pagamento, fluxo contÃ­nuo</small>

                                    </label>

                                </div>

                            </div>

                            <div class="form-text text-muted mt-2">

                                <i class="fas fa-info-circle"></i> 

                                <span class="d-block mb-1">Selecione uma opÃ§Ã£o e clique em <strong>Salvar ConfiguraÃ§Ãµes</strong>. O System Mode controla o fluxo da plataforma; o ambiente do Mercado Pago Ã© ajustado separadamente em <strong>Gateway de Pagamento â†’ MP_ENV</strong>.</span>

                                <strong>ProduÃ§Ã£o:</strong> Pagamentos reais via gateway<br>

                                <strong>Sandbox:</strong> Testes com cartÃµes e PIX simulados<br>

                                <strong>Fluxo Livre:</strong> Sem exigÃªncia de pagamento antecipado

                            </div>

                        </div>



                        <hr class="config-hr">



                        <!-- PAGAMENTO OBRIGATÃ“RIO -->

                        <h6 class="mb-3 text-muted"><i class="fas fa-lock me-2"></i>SeguranÃ§a</h6>

                        <div class="mb-3">

                            <div class="form-check form-switch config-payment-option">

                                <input class="form-check-input" type="checkbox" name="payment_required" id="payment_required" value="1"

                                    <?php echo (($config['payment_required'] ?? '1') == '1') ? 'checked' : ''; ?>>

                                <label class="form-check-label" for="payment_required">

                                    <strong>Exigir pagamento antecipado</strong>

                                    <small class="d-block text-muted">Se desativado, clientes podem solicitar serviÃ§o sem pagamento prÃ©vio</small>

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

                                    <small class="d-block text-muted">Liga logs verbosos em todo o sistema (backend e frontend): sistema, classe, funÃ§Ã£o e localizaÃ§Ã£o exata de cada evento/erro â€” nos logs do servidor e no console do navegador. Recomendado sÃ³ durante diagnÃ³stico, nÃ£o em produÃ§Ã£o normal.</small>

                                </label>

                            </div>

                        </div>



                        <button type="submit" class="btn btn-primary w-100 mt-3">

                            <i class="fas fa-save me-2"></i>Salvar ConfiguraÃ§Ãµes

                        </button>

                    </form>

                </div>

            </div>

        </div>



        <!-- COLUNA DIREITA: Gateway e InformaÃ§Ãµes -->

        <div class="col-12">
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

                            <div class="form-text">Define qual gateway serÃ¡ oferecido na tela de checkout.</div>

                        </div>



                        <div class="row g-3">

                            <div class="col-12"><h6 class="mt-2 mb-0">Mercado Pago</h6></div>

                            <div class="col-12">
                                <label class="form-label">MP_ACCESS_TOKEN</label>
                                <input type="text" class="form-control font-monospace" name="MP_ACCESS_TOKEN" value="<?php echo htmlspecialchars($envAtual["MP_ACCESS_TOKEN"] ?? ""); ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">MP_PUBLIC_KEY</label>
                                <input type="text" class="form-control font-monospace" name="MP_PUBLIC_KEY" value="<?php echo htmlspecialchars($envAtual["MP_PUBLIC_KEY"] ?? ""); ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">MP_WEBHOOK_SECRET</label>
                                <input type="text" class="form-control font-monospace" name="MP_WEBHOOK_SECRET" value="<?php echo htmlspecialchars($envAtual["MP_WEBHOOK_SECRET"] ?? ""); ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">MP_ENV</label>
                                <select class="form-select" name="MP_ENV">
                                    <option value="sandbox" <?php echo ($envAtual["MP_ENV"] ?? "production") === "sandbox" ? "selected" : ""; ?>>sandbox</option>
                                    <option value="production" <?php echo ($envAtual["MP_ENV"] ?? "production") === "production" ? "selected" : ""; ?>>production</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">MP_ACCESS_TOKEN_SANDBOX</label>
                                <input type="text" class="form-control font-monospace" name="MP_ACCESS_TOKEN_SANDBOX" value="<?php echo htmlspecialchars($envAtual["MP_ACCESS_TOKEN_SANDBOX"] ?? ""); ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">MP_ACCESS_TOKEN_PROD</label>
                                <input type="text" class="form-control font-monospace" name="MP_ACCESS_TOKEN_PROD" value="<?php echo htmlspecialchars($envAtual["MP_ACCESS_TOKEN_PROD"] ?? ""); ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">MP_PUBLIC_KEY_SANDBOX</label>
                                <input type="text" class="form-control font-monospace" name="MP_PUBLIC_KEY_SANDBOX" value="<?php echo htmlspecialchars($envAtual["MP_PUBLIC_KEY_SANDBOX"] ?? ""); ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">MP_PUBLIC_KEY_PROD</label>
                                <input type="text" class="form-control font-monospace" name="MP_PUBLIC_KEY_PROD" value="<?php echo htmlspecialchars($envAtual["MP_PUBLIC_KEY_PROD"] ?? ""); ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">MP_CLIENT_ID_PROD</label>
                                <input type="text" class="form-control font-monospace" name="MP_CLIENT_ID_PROD" value="<?php echo htmlspecialchars($envAtual["MP_CLIENT_ID_PROD"] ?? ""); ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">MP_CLIENT_SECRET_PROD</label>
                                <input type="password" class="form-control font-monospace" name="MP_CLIENT_SECRET_PROD" value="<?php echo htmlspecialchars($envAtual["MP_CLIENT_SECRET_PROD"] ?? ""); ?>">
                            </div>


                            <div class="col-12"><h6 class="mt-3 mb-0">PagSeguro</h6></div>

                            <div class="col-12">
                                <label class="form-label">PS_EMAIL</label>
                                <input type="email" class="form-control font-monospace" name="PS_EMAIL" value="<?php echo htmlspecialchars($envAtual["PS_EMAIL"] ?? ""); ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">PS_TOKEN</label>
                                <input type="text" class="form-control font-monospace" name="PS_TOKEN" value="<?php echo htmlspecialchars($envAtual["PS_TOKEN"] ?? ""); ?>">
                            </div>
                            <div class="col-12">
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

            <div class="card mb-4">
                <div class="card-header"><i class="fas fa-bell me-2"></i>NotificaÃ§Ãµes</div>
                <div class="card-body">
                    <p class="text-muted small">Centralize aqui as chaves de alerta do navegador e os canais de fallback. O campo principal Ã© <code>PUSH_VAPID_PUBLIC_KEY</code>, que precisa receber a chave pÃºblica gerada para o Web Push.</p>
                    <p class="text-muted small mb-3">Se <code>WHATSAPP_FALLBACK_TO</code> e <code>WHATSAPP_DEFAULT_TO</code> estiverem vazios, o sistema usa <code>COMPANY_WHATSAPP</code> como telefone padrÃ£o.</p>
                    <div class="alert alert-info small py-2 mb-3">
                        <strong>Como configurar:</strong> gere o par VAPID, cole a chave pÃºblica em <code>PUSH_VAPID_PUBLIC_KEY</code>, a chave privada em <code>PUSH_VAPID_PRIVATE_KEY</code> e mantenha o <code>PUSH_VAPID_SUBJECT</code> apontando para a URL do sistema.
                    </div>
                    <form method="POST" action="<?php echo $bp; ?>/admin/configuracoes">
                        <?php if (!empty($csrfToken)): ?>
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                        <?php endif; ?>
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">PUSH_VAPID_PUBLIC_KEY</label>
                                <input type="text" class="form-control font-monospace" name="PUSH_VAPID_PUBLIC_KEY" value="<?php echo htmlspecialchars($envAtual["PUSH_VAPID_PUBLIC_KEY"] ?? ""); ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">PUSH_VAPID_PRIVATE_KEY</label>
                                <input type="password" class="form-control font-monospace" name="PUSH_VAPID_PRIVATE_KEY" value="<?php echo htmlspecialchars($envAtual["PUSH_VAPID_PRIVATE_KEY"] ?? ""); ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">PUSH_VAPID_SUBJECT</label>
                                <input type="url" class="form-control font-monospace" name="PUSH_VAPID_SUBJECT" value="<?php echo htmlspecialchars($envAtual["PUSH_VAPID_SUBJECT"] ?? (defined('PUSH_VAPID_SUBJECT') ? PUSH_VAPID_SUBJECT : '')); ?>" placeholder="https://seu-dominio.com">
                            </div>
                            <div class="col-12"><h6 class="mt-2 mb-0">Telegram fallback</h6></div>
                            <div class="col-12">
                                <label class="form-label">TELEGRAM_BOT_TOKEN</label>
                                <input type="password" class="form-control font-monospace" name="TELEGRAM_BOT_TOKEN" value="<?php echo htmlspecialchars($envAtual["TELEGRAM_BOT_TOKEN"] ?? ""); ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">TELEGRAM_CHAT_ID</label>
                                <input type="text" class="form-control font-monospace" name="TELEGRAM_CHAT_ID" value="<?php echo htmlspecialchars($envAtual["TELEGRAM_CHAT_ID"] ?? ""); ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">TELEGRAM_DEFAULT_CHAT_ID</label>
                                <input type="text" class="form-control font-monospace" name="TELEGRAM_DEFAULT_CHAT_ID" value="<?php echo htmlspecialchars($envAtual["TELEGRAM_DEFAULT_CHAT_ID"] ?? ""); ?>">
                            </div>
                            <div class="col-12"><h6 class="mt-2 mb-0">WhatsApp fallback</h6></div>
                            <div class="col-12">
                                <div class="alert alert-secondary small py-2 mb-0">
                                    No WAHA, use a URL da instÃ¢ncia ou diretamente <code>/api/sendText</code>. O token Ã© o <code>WAHA_API_KEY</code> da sua instÃ¢ncia.
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">WHATSAPP_FALLBACK_URL</label>
                                <input type="url" class="form-control font-monospace" name="WHATSAPP_FALLBACK_URL" value="<?php echo htmlspecialchars($envAtual["WHATSAPP_FALLBACK_URL"] ?? ""); ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">WHATSAPP_FALLBACK_TOKEN</label>
                                <input type="password" class="form-control font-monospace" name="WHATSAPP_FALLBACK_TOKEN" value="<?php echo htmlspecialchars($envAtual["WHATSAPP_FALLBACK_TOKEN"] ?? ""); ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">WHATSAPP_FALLBACK_METHOD</label>
                                <select class="form-select" name="WHATSAPP_FALLBACK_METHOD">
                                    <?php $whatsMethod = strtoupper(trim((string)($envAtual["WHATSAPP_FALLBACK_METHOD"] ?? 'POST'))); ?>
                                    <option value="POST" <?php echo $whatsMethod === 'POST' ? 'selected' : ''; ?>>POST</option>
                                    <option value="GET" <?php echo $whatsMethod === 'GET' ? 'selected' : ''; ?>>GET</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">WHATSAPP_FALLBACK_TO</label>
                                <input type="text" class="form-control font-monospace" name="WHATSAPP_FALLBACK_TO" value="<?php echo htmlspecialchars($envAtual["WHATSAPP_FALLBACK_TO"] ?? (defined('COMPANY_WHATSAPP') ? COMPANY_WHATSAPP : ($envAtual["COMPANY_WHATSAPP"] ?? ""))); ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">WHATSAPP_DEFAULT_TO</label>
                                <input type="text" class="form-control font-monospace" name="WHATSAPP_DEFAULT_TO" value="<?php echo htmlspecialchars($envAtual["WHATSAPP_DEFAULT_TO"] ?? (defined('COMPANY_WHATSAPP') ? COMPANY_WHATSAPP : ($envAtual["COMPANY_WHATSAPP"] ?? ""))); ?>">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 mt-3">
                            <i class="fas fa-save me-2"></i>Salvar NotificaÃ§Ãµes
                        </button>
                    </form>
                </div>
            </div>

            <div class="card mb-4" id="ambiente">
                <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <span><i class="fas fa-shield-halved me-2"></i>Ambiente (.env)</span>
                    <a href="<?php echo htmlspecialchars($bp); ?>/admin/env/auditoria" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-clock-rotate-left me-1"></i>Auditoria
                    </a>
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-3">
                        Este bloco concentra o restante das variÃ¡veis do arquivo <code><?php echo htmlspecialchars($envArquivoAtivo ?? '.env'); ?></code>.
                        As Ã¡reas de gateway, prospecÃ§Ã£o e notificaÃ§Ãµes jÃ¡ ficam nesta mesma tela, nos cards acima.
                    </p>
                    <?php if (!empty($runtimeAudit['critical'] ?? [])): ?>
                        <div class="alert alert-warning small">
                            <strong>VerificaÃ§Ã£o atual:</strong> hÃ¡ pontos crÃ­ticos de ambiente que devem ser corrigidos antes de publicar.
                        </div>
                    <?php endif; ?>
                    <form method="POST" action="<?php echo $bp; ?>/admin/env/salvar" class="row g-3">
                        <?php if (!empty($csrfToken)): ?>
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                        <?php endif; ?>
                        <?php foreach ($gruposEnv as $nomeGrupo => $chaves): ?>
                        <div class="col-12">
                            <h6 class="mb-2 mt-1 text-uppercase text-muted small fw-bold"><?php echo htmlspecialchars($nomeGrupo); ?></h6>
                            <div class="row g-3">
                                <?php foreach ($chaves as $chave): ?>
                                <?php
                                    $valorAtual = (string)($envAtual[$chave] ?? '');
                                    $ehSensivel = in_array($chave, $sensivelEnv, true);
                                ?>
                                <div class="col-12">
                                    <label class="form-label fw-semibold"><?php echo htmlspecialchars($chave); ?></label>
                                    <?php if ($ehSensivel): ?>
                                        <input
                                            type="password"
                                            class="form-control font-monospace"
                                            name="env[<?php echo htmlspecialchars($chave); ?>]"
                                            value=""
                                            placeholder="<?php echo htmlspecialchars(maskEnvValue($valorAtual)); ?>"
                                            autocomplete="new-password">
                                        <small class="text-muted d-block">Deixe em branco para manter o valor atual.</small>
                                    <?php elseif ($chave === 'APP_DEBUG' || $chave === 'HTTPS_ONLY' || $chave === 'SIMULATION_ENABLED' || $chave === 'PIX_DRY_RUN' || $chave === 'SYSTEM_LOG_ENABLED'): ?>
                                        <select class="form-select" name="env[<?php echo htmlspecialchars($chave); ?>]">
                                            <option value="true" <?php echo $valorAtual === 'true' ? 'selected' : ''; ?>>true</option>
                                            <option value="false" <?php echo $valorAtual === 'false' ? 'selected' : ''; ?>>false</option>
                                        </select>
                                    <?php else: ?>
                                        <input
                                            type="<?php echo in_array($chave, ['DB_HOST','DB_NAME','DB_USER','APP_NAME','APP_URL','APP_ENV','FORCE_BASEPATH','COMPANY_ADDRESS','ADMIN_EMAIL','SMTP_HOST','SMTP_PORT','SMTP_USER','SMTP_FROM_EMAIL','SMTP_FROM_NAME','MAX_PIX_TENTATIVAS','GEOCODING_CACHE_TTL_DAYS','TARIFA_BASE','TARIFA_KM'], true) ? 'text' : 'text'; ?>"
                                            class="form-control font-monospace"
                                            name="env[<?php echo htmlspecialchars($chave); ?>]"
                                            value="<?php echo htmlspecialchars($valorAtual); ?>">
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <div class="col-12">
                            <button type="submit" class="btn btn-warning w-100">
                                <i class="fas fa-floppy-disk me-2"></i>Salvar Ambiente
                            </button>
                        </div>
                    </form>
                </div>
            </div>

                        <div class="card border-warning mb-4" id="bancoAtualizacao">
                <div class="card-header bg-warning-subtle">
                    <i class="fas fa-database me-2"></i>Atualiza&ccedil;&atilde;o do banco de dados
                </div>
                <div class="card-body">
                    <p class="mb-3">Use este bot&atilde;o ap&oacute;s publicar uma nova vers&atilde;o. O sistema executar&aacute; somente as migrations SQL pendentes e registrar&aacute; cada resultado.</p>
                    <form id="adminMigrationForm" method="post" action="<?php echo htmlspecialchars($bp); ?>/admin/configuracoes/atualizar-banco">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken ?? ''); ?>">
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-arrows-rotate me-2"></i>Verificar e atualizar banco
                        </button>
                    </form>
                    <div id="adminMigrationResult" class="mt-3" hidden></div>
                </div>
            </div>
<div class="card">
                <div class="card-header"><i class="fas fa-info-circle me-2"></i>InformaÃ§Ãµes</div>
                <div class="card-body">
                    <p class="config-info-text">

                        <i class="fas fa-check-circle text-success me-1"></i> 

                        AlteraÃ§Ãµes nas configuraÃ§Ãµes de tarifa serÃ£o aplicadas em todos os novos pedidos imediatamente.

                        Pedidos jÃ¡ criados nÃ£o serÃ£o afetados.

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

                        <strong>Pagamento obrigatÃ³rio:</strong> 

                        <span class="badge <?php echo (($config['payment_required'] ?? '1') == '1') ? 'bg-danger' : 'bg-secondary'; ?>">

                            <?php echo (($config['payment_required'] ?? '1') == '1') ? 'Sim' : 'NÃ£o'; ?>

                        </span>

                    </p>

                    <hr class="config-hr">

                    <small class="config-info-small">Ãšltima alteraÃ§Ã£o: <?php echo date('d/m/Y H:i'); ?></small>

                </div>

            </div>

        </div>

    </div>



</main>

<script<?php echo csp_script_nonce_attr(); ?>>
(function () {
    const form = document.getElementById('adminMigrationForm');
    const result = document.getElementById('adminMigrationResult');
    if (!form || !result) return;
    form.addEventListener('submit', async function (event) {
        event.preventDefault();
        if (!window.confirm('Executar as migrations pendentes do banco agora?')) return;
        const button = form.querySelector('button[type="submit"]');
        button.disabled = true;
        result.hidden = false;
        result.className = 'alert alert-info mt-3';
        result.textContent = 'Executando migrations pendentes...';
        try {
            const response = await fetch(form.action, { method: 'POST', body: new FormData(form), credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
            const data = await response.json();
            const lines = (data.results || []).map(function (item) { return (item.status || 'unknown').toUpperCase() + ' - ' + (item.filename || '') + (item.message ? ': ' + item.message : ''); });
            result.className = 'alert mt-3 ' + (data.success ? 'alert-success' : 'alert-warning');
            result.innerHTML = '<strong>' + String(data.message || 'Resultado recebido.').replace(/[&<>]/g, function (c) { return ({'&':'&amp;','<':'&lt;','>':'&gt;'}[c]); }) + '</strong>' + (lines.length ? '<pre class="small mt-2 mb-0">' + lines.join('\n').replace(/[&<>]/g, function (c) { return ({'&':'&amp;','<':'&lt;','>':'&gt;'}[c]); }) + '</pre>' : '');
        } catch (error) {
            result.className = 'alert alert-danger mt-3';
            result.textContent = 'Falha de comunicacao com o servidor: ' + error.message;
        } finally {
            button.disabled = false;
        }
    });
})();
</script><script<?php echo csp_script_nonce_attr(); ?> defer src="<?php echo htmlspecialchars($bp); ?>/public/assets/js/admin-configuracoes.js?v=20260813-2"></script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>


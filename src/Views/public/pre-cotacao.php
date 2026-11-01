<?php
// src/Views/public/pre-cotacao.php
// §23 · Layout Lovable + wizard progressivo.
$bp = defined('BASE_PATH') ? BASE_PATH : '';
$e = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
$flash = $flash ?? null;
$resultado = ($_GET['resultado'] ?? '') === '1' && !empty($cotacao) && (int)($cotacao['expira_em'] ?? 0) > time();
$nonce = function_exists('csp_script_nonce_attr') ? csp_script_nonce_attr() : '';
$prefillLat = $prefillLat ?? '';
$prefillLng = $prefillLng ?? '';
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<?php require __DIR__ . '/../components/marketing_tracking.php'; ?>
<title>Socorro e guincho — GuinchaFácil</title>
<meta name="description" content="Informe sua localização e receba uma cotação de guincho ou assistência antes do cadastro.">
<link rel="stylesheet" href="<?= $e($bp) ?>/public/assets/vendor/bootstrap/css/bootstrap.min.css">
<link rel="stylesheet" href="<?= $e($bp) ?>/public/assets/vendor/fontawesome/css/all.min.css">
<link rel="stylesheet" href="<?= $e($bp) ?>/public/assets/vendor/leaflet/leaflet.css">
<link rel="stylesheet" href="<?= $e($bp) ?>/public/assets/css/pages/pre-cotacao.css?v=20261101-1">
<link rel="stylesheet" href="<?= $e($bp) ?>/public/assets/css/gf-layout.css?v=20261101-1">
<script<?= $nonce ?> src="<?= $e($bp) ?>/public/assets/vendor/leaflet/leaflet.js"></script>
</head>
<body class="gf-preq-body">

<div class="gf-utility-bar">
    <div class="gf-container">
        <span><span class="gf-status-dot"></span> Socorro 24h. Todos os dias. Em qualquer imprevisto.</span>
        <a href="<?= $e($bp) ?>/guincho/niteroi-rj"><i class="fas fa-map-marker-alt"></i> Niterói, RJ</a>
    </div>
</div>
<header class="gf-site-header"><div class="gf-container"><a class="gf-brand" href="<?= $e($bp) ?>/">Guincha<span>Fácil</span>.</a><nav class="gf-nav" aria-label="Navegação principal"><a href="<?= $e($bp) ?>/pre-cotacao" class="is-active">Como funciona</a><a href="<?= $e($bp) ?>/guincho/niteroi-rj">Onde atendemos</a><a href="<?= $e($bp) ?>/parceiros">Seja um parceiro</a></nav><a class="gf-btn gf-btn--primary gf-btn--sm" href="<?= $e($bp) ?>/pre-cotacao">Pedir guincho</a></div></header>

<main class="gf-preq">
    <div class="gf-container">

    <?php if (!$resultado): ?>

        <div class="gf-preq__intro">
            <div>
                <h1>Seu socorro, sem complicação<em>.</em></h1>
                <p>Conte com a GuinchaFácil para seguir em frente.</p>
            </div>
        </div>

        <nav class="gf-steps" aria-label="Etapas da solicitação">
            <ol id="gfStepsList">
                <li class="is-current" data-step-indicator="1"><span>01</span>Localização</li>
                <li data-step-indicator="2"><span>02</span>Como ajudar</li>
                <li data-step-indicator="3"><span>03</span>O que houve</li>
                <li data-step-indicator="4"><span>04</span>Veículo</li>
                <li data-step-indicator="5"><span>05</span>Destino</li>
                <li data-step-indicator="6"><span>06</span>Confirmar</li>
            </ol>
        </nav>

        <div class="gf-preq__grid">
            <!-- ════════ FORM WIZARD ════════ -->
            <form method="post" action="<?= $e($bp) ?>/pre-cotacao" id="gfPreqForm" data-marketing-event="generate_lead" novalidate>
                <input type="hidden" name="csrf_token" value="<?= $e((string)($csrf_token ?? '')) ?>">
                <input type="hidden" name="lat_origem" id="lat_origem" value="<?= $e((string)$prefillLat) ?>">
                <input type="hidden" name="lng_origem" id="lng_origem" value="<?= $e((string)$prefillLng) ?>">
                <input type="hidden" name="lat_destino" id="lat_destino" value="">
                <input type="hidden" name="lng_destino" id="lng_destino" value="">
                <input type="hidden" name="numero_origem" id="numero_origem" value="">
                <input type="hidden" name="numero_destino" id="numero_destino" value="">
                <input type="hidden" name="origem_seo" value="">

                <?php if (!empty($flash)): ?>
                    <div class="gf-alert"><?= $e($flash['message'] ?? '') ?></div>
                <?php endif; ?>

                <!-- ETAPA 1 — Localização -->
                <fieldset class="gf-step is-active" data-step="1">
                    <div class="gf-form__heading">
                        <span class="eyebrow">Etapa 01 / 06</span>
                        <h2>Onde está o veículo?</h2>
                        <p>Digite o endereço ou use sua localização atual.</p>
                    </div>

                    <div class="gf-field gf-field--icon">
                        <label for="localizacao">Endereço de retirada</label>
                        <i class="fas fa-map-pin gf-field__icon" aria-hidden="true"></i>
                        <input type="text" id="localizacao" name="localizacao" maxlength="220"
                               placeholder="Rua, bairro e cidade" autocomplete="street-address" required>
                    </div>

                    <div class="gf-location-row">
                        <button type="button" id="btnGps"><i class="fas fa-location-crosshairs"></i> Usar minha localização</button>
                        <span id="mapLabelHint">Niterói, RJ</span>
                    </div>

                    <div class="gf-form__footer">
                        <button class="gf-btn gf-btn--primary" type="button" data-next="2">
                            Continuar <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </fieldset>

                <!-- ETAPA 2 — Como ajudar (bifurcação) -->
                <fieldset class="gf-step" data-step="2">
                    <button type="button" class="gf-step__back" data-back="1"><i class="fas fa-chevron-left"></i> Voltar</button>
                    <div class="gf-form__heading">
                        <span class="eyebrow">Etapa 02 / 06</span>
                        <h2>Como podemos ajudar?</h2>
                        <p id="gfModeSubtitle">Escolha o caminho que faz sentido para você.</p>
                    </div>

                    <input type="hidden" name="tipo_problema" id="tipo_problema" value="outro">
                    <input type="hidden" name="decisao_atendimento" id="decisao_atendimento" value="">

                    <div class="gf-modes" id="gfModes">
                        <button type="button" class="gf-mode" data-mode="me_orientem" data-mode-label="Me orientem">
                            <span class="gf-mode__icon"><i class="fas fa-comments"></i></span>
                            <strong>Me orientem</strong>
                            <small>Não sei qual caminho escolher. Me digam o que fazer.</small>
                        </button>
                        <button type="button" class="gf-mode" data-mode="resolver_local" data-mode-label="Resolver no local">
                            <span class="gf-mode__icon"><i class="fas fa-user-cog"></i></span>
                            <strong>Resolver no local</strong>
                            <small>Quero uma avaliação técnica onde estou.</small>
                        </button>
                        <button type="button" class="gf-mode" data-mode="levar_carro" data-mode-label="Levar o carro">
                            <span class="gf-mode__icon"><i class="fas fa-truck-pickup"></i></span>
                            <strong>Levar o carro</strong>
                            <small>Preciso de uma oficina ou destino específico.</small>
                        </button>
                    </div>
                </fieldset>

                <!-- ETAPA 3 — O que aconteceu? -->
                <fieldset class="gf-step" data-step="3">
                    <button type="button" class="gf-step__back" data-back="2"><i class="fas fa-chevron-left"></i> Voltar</button>
                    <div class="gf-form__heading">
                        <span class="eyebrow">Etapa 03 / 06</span>
                        <h2>O que aconteceu?</h2>
                        <p>Selecione o tipo de problema para calcularmos o valor.</p>
                    </div>
                    <div class="gf-services" id="gfSintomas">
                        <?php
                        $sintomas = [
                            ['pneu',        'fa-circle-notch', 'Pneu',      'Furado, murcho ou danificado'],
                            ['eletrica',    'fa-bolt',         'Elétrica',  'Não liga, painel apagado'],
                            ['bateria',     'fa-car-battery',  'Bateria',   'Não pega partida'],
                            ['mecanica',    'fa-gears',        'Mecânica',  'Motor, freio ou suspensão'],
                            ['chaveiro',    'fa-key',          'Chaveiro',  'Chave presa, perdida ou travada'],
                            ['combustivel', 'fa-gas-pump',     'Combustível','Pane seca na estrada'],
                        ];
                        foreach ($sintomas as $s): ?>
                        <button type="button" class="gf-service" data-sintoma="<?= $e($s[0]) ?>">
                            <i class="fas <?= $e($s[1]) ?>"></i>
                            <span class="gf-service__txt">
                                <strong><?= $e($s[2]) ?></strong>
                                <small><?= $e($s[3]) ?></small>
                            </span>
                        </button>
                        <?php endforeach; ?>
                    </div>
                </fieldset>

                <!-- ETAPA 4 — Veículo -->
                <fieldset class="gf-step" data-step="4">
                    <button type="button" class="gf-step__back" data-back="3"><i class="fas fa-chevron-left"></i> Voltar</button>
                    <div class="gf-form__heading">
                        <span class="eyebrow">Etapa 04 / 06</span>
                        <h2>Qual é o veículo?</h2>
                        <p>O tipo influencia no valor do atendimento.</p>
                    </div>
                    <div class="gf-services" id="gfVeiculos">
                        <?php
                        $veiculos = [
                            ['popular',    'fa-car',              'Carro',       'Veículo de passeio comum.'],
                            ['moto',       'fa-motorcycle',       'Moto',        'Motocicleta ou scooter.'],
                            ['suv',        'fa-car-side',         'SUV',         'Veículo alto ou utilitário.'],
                            ['utilitario', 'fa-truck-pickup',     'Utilitário',  'Van, picape ou caminhonete.'],
                        ];
                        foreach ($veiculos as $v): ?>
                        <button type="button" class="gf-service" data-categoria="<?= $e($v[0]) ?>">
                            <i class="fas <?= $e($v[1]) ?>"></i>
                            <span class="gf-service__txt">
                                <strong><?= $e($v[2]) ?></strong>
                                <small><?= $e($v[3]) ?></small>
                            </span>
                        </button>
                        <?php endforeach; ?>
                    </div>
                    <input type="hidden" name="categoria" id="categoria" value="popular">
                </fieldset>

                <!-- ETAPA 5 — Destino -->
                <fieldset class="gf-step" data-step="5">
                    <button type="button" class="gf-step__back" data-back="4"><i class="fas fa-chevron-left"></i> Voltar</button>
                    <div class="gf-form__heading">
                        <span class="eyebrow">Etapa 05 / 06</span>
                        <h2>Para onde levamos?</h2>
                        <p id="gfDestinoSubtitle">Informe o destino para calcularmos a rota e o valor do reboque.</p>
                    </div>
                    <div class="gf-field gf-field--icon">
                        <label for="destino">Endereço de destino</label>
                        <i class="fas fa-map-pin gf-field__icon" aria-hidden="true"></i>
                        <input type="text" id="destino" name="destino" maxlength="220"
                               placeholder="Oficina ou endereço completo" autocomplete="street-address">
                    </div>
                    <div class="gf-field">
                        <label for="numero_destino_vis">Número</label>
                        <input type="text" id="numero_destino_vis" maxlength="20" inputmode="numeric" placeholder="Ex.: 280">
                    </div>
                    <div class="gf-form__footer">
                        <button class="gf-btn gf-btn--primary" type="button" data-next="6">
                            Calcular <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </fieldset>

                <!-- ETAPA 6 — Comparativo + Confirmar -->
                <fieldset class="gf-step" data-step="6">
                    <button type="button" class="gf-step__back" data-back="5"><i class="fas fa-chevron-left"></i> Voltar</button>
                    <div class="gf-form__heading">
                        <span class="eyebrow">Etapa 06 / 06</span>
                        <h2 id="gfCompareTitle">Escolha como seguir</h2>
                        <p id="gfCompareSubtitle">Comparamos as opções disponíveis para você.</p>
                    </div>

                    <div class="gf-recommendation" id="gfRecommendation" hidden></div>

                    <div class="gf-compare" id="gfCompare">
                        <button type="button" class="gf-compare__card" data-decision="assistencia" hidden>
                            <span class="gf-compare__badge">A</span>
                            <strong>Resolver no local</strong>
                            <span class="gf-compare__price" data-price="assistencia">R$ --</span>
                            <span class="gf-compare__hint">Um profissional vai até você. Se não resolver, você pode pedir reboque com desconto.</span>
                        </button>
                        <button type="button" class="gf-compare__card" data-decision="reboque" hidden>
                            <span class="gf-compare__badge">B</span>
                            <strong>Rebocar</strong>
                            <span class="gf-compare__price" data-price="reboque">R$ --</span>
                            <span class="gf-compare__hint">Você paga esse valor apenas para acionar o reboque até você. Deseja rebocar? Se quiser, na próxima fase informe o destino e passamos o cálculo completo.</span>
                        </button>
                    </div>

                    <div class="gf-form__footer">
                        <button class="gf-btn gf-btn--primary" type="submit" id="btnSubmit" disabled>
                            Ver minha cotação <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                    <p class="gf-form__note">
                        <i class="fas fa-shield-halved"></i> Sem compromisso. Nenhuma cobrança agora.
                    </p>
                </fieldset>
            </form>

            <!-- ════════ ASIDE — MAPA + RESUMO ════════ -->
            <aside class="gf-aside">
                <div class="gf-map-wrap">
                    <div id="gfMap" class="gf-map" role="region" aria-label="Mapa de cobertura"></div>
                </div>
                <div class="gf-map-label">
                    <i class="fas fa-map-pin"></i>
                    <span id="mapLabel">Aguardando endereço…</span>
                    <span class="gf-map-label__pill">Área ilustrativa</span>
                </div>

                <div class="gf-summary">
                    <div class="gf-summary__title">
                        <h3>Sua solicitação</h3>
                        <i class="fas fa-car" style="color:#6b7a70"></i>
                    </div>
                    <div class="gf-summary__service">
                        <span class="gf-summary__icon"><i class="fas fa-route" id="summaryIcon"></i></span>
                        <div>
                            <strong id="summaryService">Aguardando…</strong>
                            <p id="summaryServiceDesc">Comece informando o endereço</p>
                        </div>
                    </div>
                    <div class="gf-summary__path">
                        <span><i></i> <em id="summaryOrigem" style="font-style:normal">Ponto de retirada</em></span>
                        <span><i></i> <em id="summaryDestino" style="font-style:normal">Oficina ou destino</em></span>
                    </div>
                    <div class="gf-summary__total">
                        <span>Total estimado<small>Calculado ao final</small></span>
                        <strong id="summaryTotal">R$ --</strong>
                    </div>
                </div>

                <div class="gf-assurance">
                    <i class="fas fa-shield-halved"></i>
                    <p>Transparência em cada etapa.<br><strong>Você decide antes de continuar.</strong></p>
                </div>
            </aside>

        </div>

    <?php else: ?>

        <div class="gf-preq__intro">
            <div>
                <h1>Sua cotação está pronta<em>.</em></h1>
                <p>Confira o valor antes de se cadastrar.</p>
            </div>
        </div>
        <div class="gf-preq__grid gf-preq__grid--result">
        <section class="gf-result-form">
        <div class="gf-summary gf-summary--result-form">
            <div class="gf-summary__title">
                <h3><?= $e((string)($cotacao['service_code'] ?? 'TOW_CAR')) ?></h3>
                <span><?= number_format((float)$cotacao['distancia_km'], 1, ',', '.') ?> km</span>
            </div>
            <div class="gf-summary__total">
                <span>Total<small>Válida por 15 minutos</small></span>
                <strong>R$ <?= number_format((float)$cotacao['valor'], 2, ',', '.') ?></strong>
            </div>
            <form method="post" action="<?= $e($bp) ?>/pre-cotacao/aceitar" style="margin-top:22px;">
			    
                <input type="hidden" name="service_type_id" id="service_type_id" value="">
                <input type="hidden" name="csrf_token" value="<?= $e((string)($csrf_token ?? '')) ?>">
                <fieldset style="border:0;padding:0;margin:0 0 18px;text-align:left;">
                    <legend style="font-size:13px;font-weight:700;color:#183b29;margin-bottom:8px;">Como prefere pagar?</legend>
                    <label style="display:block;margin:7px 0;font-size:13px;cursor:pointer;">
                        <input type="radio" name="forma_pagamento" value="online" checked>
                        Pagar online agora <small>(cartão, Pix ou boleto)</small>
                        <span class="gf-payment-brands" aria-label="Bandeiras e meios aceitos">
                            <svg viewBox="0 0 54 22" role="img" aria-label="Visa"><rect width="54" height="22" rx="4" fill="#1434CB"/><text x="27" y="15" text-anchor="middle" fill="#fff" font-size="11" font-family="Arial" font-weight="700">VISA</text></svg>
                            <svg viewBox="0 0 54 22" role="img" aria-label="Mastercard"><rect width="54" height="22" rx="4" fill="#111827"/><circle cx="23" cy="11" r="6" fill="#EB001B"/><circle cx="31" cy="11" r="6" fill="#F79E1B" fill-opacity=".95"/><text x="27" y="19" text-anchor="middle" fill="#fff" font-size="4" font-family="Arial">MASTERCARD</text></svg>
                            <svg viewBox="0 0 54 22" role="img" aria-label="Pix"><rect width="54" height="22" rx="4" fill="#e8f6ec"/><path d="M11 11 17 5l6 6-6 6-6-6Zm12 0 6-6 6 6-6 6-6-6Z" fill="#0d5a2e"/><text x="42" y="14" fill="#0d5a2e" font-size="7" font-family="Arial" font-weight="700">PIX</text></svg>
                            <svg viewBox="0 0 54 22" role="img" aria-label="Boleto"><rect width="54" height="22" rx="4" fill="#f3f4f6"/><path d="M10 6v10M14 6v10M18 6v10M23 6v10M28 6v10M34 6v10M39 6v10M44 6v10" stroke="#374151" stroke-width="1.4"/><text x="27" y="20" text-anchor="middle" fill="#374151" font-size="4" font-family="Arial">BOLETO</text></svg>
                        </span>
                    </label>
                    <label style="display:block;margin:7px 0;font-size:13px;cursor:pointer;">
                        <input type="radio" name="forma_pagamento" value="pago_na_chegada">
                        Pagar na chegada <small>(em dinheiro ao parceiro)</small>
                    </label>
                    <small style="display:block;color:#66756b;margin-top:8px;">A pré-cotação não gera cobrança. A opção será aplicada ao criar o pedido.</small>
                </fieldset>
                <button class="gf-btn gf-btn--primary" type="submit" style="width:100%">
                    Aceitar e criar cadastro <i class="fas fa-arrow-right"></i>
                </button>
            </form>
            <a href="<?= $e($bp) ?>/registro/funil" style="display:block;text-align:center;margin-top:14px;color:#0d5a2e;font-weight:700;font-size:12px;">
                Ir direto para o cadastro
            </a>
        </div>
        </section>
        <aside class="gf-aside gf-aside--result">
            <div class="gf-summary gf-summary--result-side">
                <div class="gf-summary__title">
                    <h3>Sua solicitaÃ§Ã£o</h3>
                    <i class="fas fa-shield-halved" style="color:#0d5a2e"></i>
                </div>
                <div class="gf-summary__path">
                    <span><i></i> <em><?= $e((string)($cotacao['endereco_origem'] ?? 'Ponto de retirada')) ?></em></span>
                    <span><i></i> <em><?= $e((string)($cotacao['endereco_destino'] ?? 'Destino informado')) ?></em></span>
                </div>
                <div class="gf-summary__total">
                    <span>Total<small>VÃ¡lida por 15 minutos</small></span>
                    <strong>R$ <?= number_format((float)$cotacao['valor'], 2, ',', '.') ?></strong>
                </div>
            </div>
            <div class="gf-assurance">
                <i class="fas fa-lock"></i>
                <p>Sem cobranÃ§a nesta etapa.<br><strong>VocÃª decide antes de continuar.</strong></p>
            </div>
        </aside>
        </div>

    <?php endif; ?>

    </div>
</main>
<footer class="gf-site-footer"><div class="gf-container"><div class="gf-footer-bottom"><span>&copy; <?= date('Y') ?> GuinchaFácil. Todos os direitos reservados.</span><span>Assistência 24 horas · Niterói, RJ</span></div></div></footer>
<script<?= $nonce ?> src="<?= $e($bp) ?>/public/assets/js/pre-cotacao-flow.js?v=20261101-2"></script>
<script<?= $nonce ?> src="<?= $e($bp) ?>/public/assets/js/pre-cotacao-map.js?v=20261030-6"></script>
</body>
</html>

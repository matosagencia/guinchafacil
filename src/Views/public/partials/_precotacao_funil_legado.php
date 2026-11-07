<?php
$prefillLat = $prefillLat ?? '';
$prefillLng = $prefillLng ?? '';
/**
 * Funil legado compartilhado pela pre-cotacao publica, cliente e admin.
 * Apenas acao e campos de contexto variam; etapas e comportamento sao unicos.
 */
$legacyAction = $legacyAction ?? ($bp . '/pre-cotacao');
$legacyHidden = $legacyHidden ?? [];
$fixedVehicle = !empty($legacyHidden['veiculo_id']);
$fixedVehicleCategory = (string)($legacyHidden['veiculo_categoria'] ?? 'popular');
?>
<form method="post" action="<?= $e((string)($legacyAction ?? ($bp . '/pre-cotacao'))) ?>" id="gfPreqForm" data-fixed-vehicle="<?= $fixedVehicle ? '1' : '0' ?>" data-marketing-event="generate_lead" novalidate>
                <input type="hidden" name="csrf_token" value="<?= $e((string)($csrf_token ?? '')) ?>">
                <?php foreach (($legacyHidden ?? []) as $hiddenName => $hiddenValue): ?>
                    <input type="hidden" name="<?= $e((string)$hiddenName) ?>" value="<?= $e((string)$hiddenValue) ?>">
                <?php endforeach; ?>
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

                <!-- ETAPA 1 - Localização -->
                <fieldset class="gf-step is-active" data-step="1">
                    <div class="gf-form__heading">
                        <span class="eyebrow">Etapa 01 / 06</span>
                        <h2>Onde está o veículo?</h2>
                        <p>Digite o endereço ou use sua localização atual.</p>
                    </div>

                    <div class="gf-field gf-field--icon gf-field--location">
                        <label for="localizacao">Endereço de retirada</label>
                        <button type="button" class="gf-field__gps" id="btnGps" aria-label="Usar minha localização" title="Usar minha localização"><i class="fas fa-location-crosshairs" aria-hidden="true"></i></button>
                        <input type="text" id="localizacao" name="localizacao" maxlength="220"
                               placeholder="Rua, bairro e cidade" autocomplete="street-address" required>
                    </div>

                    <div class="gf-form__footer">
                        <button class="gf-btn gf-btn--primary" type="button" data-next="2">
                            Continuar <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </fieldset>

                <!-- ETAPA 2 - Como ajudar (bifurcação) -->
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

                <!-- ETAPA 3 - O que aconteceu? -->
                <fieldset class="gf-step" data-step="3">
                    <button type="button" class="gf-step__back" data-back="2"><i class="fas fa-chevron-left"></i> Voltar</button>
                    <div class="gf-form__heading">
                        <span class="eyebrow">Etapa 03 / 06</span>
                        <h2>O que aconteceu?</h2>
                        <p>Selecione o tipo de problema para calcularmos o valor.</p>
                    </div>
                    <div class="gf-services" id="gfSintomas">
                        <?php
                        require_once __DIR__ . '/../../../Models/Catalog/ServiceType.php';
                        $sintomas = [];
                        foreach (ServiceType::listarAtivos() as $tipo) {
                            $slug = strtolower(trim((string)($tipo['public_slug'] ?? $tipo['code'] ?? '')));
                            if ($slug === '') continue;
                            $sintomas[] = [$slug, 'fa-wrench', (string)$tipo['name'], (string)($tipo['description'] ?? '')];
                        }
                        /* legacy fallback only when no approved capability exists */
                        if (false && !$sintomas) $sintomas = [
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

                <!-- ETAPA 4 - Veículo -->
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
                    <input type="hidden" name="categoria" id="categoria" value="<?= $e($fixedVehicleCategory ?: 'popular') ?>">
                </fieldset>

                <!-- ETAPA 5 - Destino -->
                <fieldset class="gf-step" data-step="5">
                    <button type="button" class="gf-step__back" data-back="4"><i class="fas fa-chevron-left"></i> Voltar</button>
                    <div class="gf-form__heading">
                        <span class="eyebrow">Etapa 05 / 06</span>
                        <h2>Para onde levamos?</h2>
                        <p id="gfDestinoSubtitle">Informe o destino para calcularmos a rota e o valor do reboque.</p>
                    </div>
                    <div class="gf-field gf-field--icon">
                        <label for="destino">Endereço de destino</label>
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

                <!-- ETAPA 6 - Comparativo + Confirmar -->
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

                    <?php if (strpos((string)$legacyAction, '/admin/') !== false): ?>
                    <div class="gf-admin-payment-choice" aria-labelledby="gfAdminPaymentTitle">
                        <h3 id="gfAdminPaymentTitle">Como este pedido será pago?</h3>
                        <label><input type="radio" name="destino_pagamento" value="pago_na_chegada" required> Dinheiro — pagar no local</label>
                        <label><input type="radio" name="destino_pagamento" value="online" required> Cartão — cobrar agora no Payment Brick</label>
                        <label><input type="radio" name="destino_pagamento" value="pago_agora" required> Já pago — faturar parceiro</label>
                    </div>
                    <?php endif; ?>

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


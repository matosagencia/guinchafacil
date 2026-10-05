<?php
/**
 * Partial: funil cliente v2  clone do publico _precotacao_funil.php
 * com action=/cliente/pedido/criar.
 */
$bp = $bp ?? (defined('BASE_PATH') ? BASE_PATH : '');
$csrf_token = $csrf_token ?? '';
$veiculo = $veiculo ?? null;
$triagemServiceType = $triagemServiceType ?? null;
$veiculoCategoria = $veiculoCategoria ?? 'popular';
$e = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
?>
<form method="post" action="<?= $e($bp) ?>/cliente/pedido/criar" class="row g-3" data-precotacao-form data-marketing-event="create_order">
    <input type="hidden" name="csrf_token" value="<?= $e($csrf_token) ?>">
    <?php if ($veiculo): ?><input type="hidden" name="veiculo_id" value="<?= (int)$veiculo['id'] ?>"><?php endif; ?>
    <?php if (!empty($triagemServiceType)): ?><input type="hidden" name="service_type_id" value="<?= (int)$triagemServiceType['id'] ?>"><?php endif; ?>

    <div class="col-12" id="stage-endereco">
        <fieldset class="choice-fieldset">
            <legend class="label">Onde esta o veiculo?</legend>
            <div class="gf-ap" data-role="origem" data-ap-init-lat="-22.9068" data-ap-init-lng="-43.1729">
                <div class="gf-ap__map" data-ap-map></div>
                <div class="gf-ap__pin-overlay"><i class="fas fa-location-dot"></i></div>
                <div class="gf-ap__map-title"><strong>Confirme o ponto no mapa</strong><span>Arraste o pin ate o local exato.</span></div>
                <div class="gf-ap__panel" data-ap-panel>
                    <button type="button" class="gf-ap__panel-toggle" data-ap-toggle><i class="fas fa-chevron-up"></i> Recolher busca</button>
                    <label class="gf-ap__label">Onde esta o veiculo?</label>
                    <button type="button" class="gf-ap__gps" data-ap-gps><i class="fas fa-location-crosshairs" aria-hidden="true"></i> Usar minha localizacao</button>
                    <div class="gf-ap__row">
                        <div class="gf-ap__field"><label class="gf-ap__field-label">Rua e cidade</label><input type="text" class="gf-ap__input" data-ap-input placeholder="Rua, bairro e cidade" autocomplete="street-address"></div>
                        <div class="gf-ap__field"><label class="gf-ap__field-label">Numero</label><input type="text" class="gf-ap__number" data-ap-number inputmode="numeric" placeholder="Ex.: 280" autocomplete="address-line2"></div>
                    </div>
                    <small class="gf-ap__status" data-ap-status></small>
                    <div class="gf-ap__confirm" data-ap-confirm hidden><div class="gf-ap__confirm-text" data-ap-confirm-text></div><button type="button" class="gf-ap__confirm-btn" data-ap-confirm-btn>Confirmar</button></div>
                </div>
            </div>
        </fieldset>
    </div>

    <div class="col-12" id="stage-modo" hidden>
        <fieldset class="choice-fieldset">
            <legend class="label">O que aconteceu?</legend>
            <div class="choice-grid choice-grid-help">
                <button type="button" class="choice-card" data-mode="me_orientem" aria-pressed="false"><i class="fas fa-comments" aria-hidden="true"></i><strong>Me orientem</strong><small>Nao sei qual caminho escolher.</small></button>
                <button type="button" class="choice-card" data-mode="local" aria-pressed="false"><i class="fas fa-user-cog" aria-hidden="true"></i><strong>Resolver no local</strong><small>Quero uma avaliacao onde estou.</small></button>
                <button type="button" class="choice-card" data-mode="levar_carro" aria-pressed="false"><i class="fas fa-truck-pickup" aria-hidden="true"></i><strong>Levar o carro</strong><small>Preciso de uma oficina ou destino.</small></button>
            </div>
        </fieldset>
    </div>

    <div class="col-12" id="stage-veiculo" hidden>
        <fieldset class="choice-fieldset">
            <legend class="label">Qual e o tipo de veiculo?</legend>
            <div class="choice-grid choice-grid-vehicle">
                <button type="button" class="choice-card is-selected" data-categoria="popular" aria-pressed="true"><i class="fas fa-car" aria-hidden="true"></i><strong>Carro</strong><small>Veiculo de passeio comum.</small></button>
                <button type="button" class="choice-card" data-categoria="moto" aria-pressed="false"><i class="fas fa-motorcycle" aria-hidden="true"></i><strong>Moto</strong><small>Motocicleta ou scooter.</small></button>
                <button type="button" class="choice-card" data-categoria="suv" aria-pressed="false"><i class="fas fa-car-side" aria-hidden="true"></i><strong>SUV</strong><small>Veiculo alto ou utilitario esportivo.</small></button>
                <button type="button" class="choice-card" data-categoria="caminhonete" aria-pressed="false"><i class="fas fa-truck-pickup" aria-hidden="true"></i><strong>Caminhonete</strong><small>Picape ou veiculo de carga leve.</small></button>
                <button type="button" class="choice-card" data-categoria="eletrico" aria-pressed="false"><i class="fas fa-charging-station" aria-hidden="true"></i><strong>Eletrico</strong><small>Veiculo 100% eletrico.</small></button>
            </div>
        </fieldset>
    </div>

    <div class="col-12" id="stage-sintoma" hidden>
        <fieldset class="choice-fieldset">
            <legend class="label">Qual e o problema?</legend>
            <div class="choice-grid choice-grid-sintoma" id="triagem-servicos" aria-live="polite"></div>
            <div class="mt-3"><button type="button" class="btn btn-outline-secondary" data-action="voltar">Voltar</button></div>
        </fieldset>
    </div>

    <div class="col-12" id="stage-opcoes" hidden>
        <fieldset class="choice-fieldset">
            <legend class="label">Escolha como quer seguir</legend>
            <div id="opcoes-wrap" aria-live="polite"></div>
        </fieldset>
    </div>

    <div class="col-12" id="stage-destino" hidden>
        <fieldset class="choice-fieldset">
            <legend class="label">Para onde deve levar o veiculo?</legend>
            <div class="gf-ap" data-role="destino">
                <div class="gf-ap__map" data-ap-map></div>
                <div class="gf-ap__pin-overlay"><i class="fas fa-location-dot"></i></div>
                <div class="gf-ap__map-title"><strong>Para onde levar o veiculo?</strong><span>Arraste o pin ate o destino exato.</span></div>
                <div class="gf-ap__panel" data-ap-panel>
                    <div class="gf-ap__row">
                        <div class="gf-ap__field"><label class="gf-ap__field-label">Rua e cidade</label><input type="text" class="gf-ap__input" data-ap-input placeholder="Rua, bairro e cidade" autocomplete="street-address"></div>
                        <div class="gf-ap__field"><label class="gf-ap__field-label">Numero</label><input type="text" class="gf-ap__number" data-ap-number inputmode="numeric" placeholder="Ex.: 247" autocomplete="address-line2"></div>
                    </div>
                    <small class="gf-ap__status" data-ap-status></small>
                    <div class="gf-ap__confirm" data-ap-confirm hidden><div class="gf-ap__confirm-text" data-ap-confirm-text></div><button type="button" class="gf-ap__confirm-btn" data-ap-confirm-btn>Confirmar</button></div>
                </div>
            </div>
            <div class="mt-3"><button type="button" class="btn btn-outline-secondary" data-action="voltar">Voltar</button></div>
        </fieldset>
    </div>

    <div class="col-12" id="stage-cotacao" hidden>
        <button type="submit" class="btn-main w-100" id="gfBtnFinal">Confirmar pedido de socorro</button>
    </div>

    <input type="hidden" name="lat_origem" value="">
    <input type="hidden" name="lng_origem" value="">
    <input type="hidden" name="localizacao" value="">
    <input type="hidden" name="numero_origem" value="">
    <input type="hidden" name="lat_destino" value="">
    <input type="hidden" name="lng_destino" value="">
    <input type="hidden" name="destino" value="">
    <input type="hidden" name="numero_destino" value="">
    <input type="hidden" name="categoria" value="<?= $e($veiculoCategoria) ?>">
    <input type="hidden" name="tipo_problema" value="">
    <input type="hidden" name="decisao_atendimento" value="">
</form>
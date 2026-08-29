<?php
require_once __DIR__ . '/../../Services/POR/PorThresholds.php';
$bp    = defined('BASE_PATH') ? BASE_PATH : '';
$osrmBaseUrl = PorThresholds::routingFrontendBaseUrl();
$erro  = $_GET['erro'] ?? '';
$pedidoRascunho = $pedidoRascunho ?? ($_SESSION['pedido_rascunho'] ?? null);
$triagemServiceType = $triagemServiceType ?? null;

// Serializa oficinas com coordenadas para JS
$oficinasJs = array_values(array_map(fn($o) => [
    'id'       => (int)($o['id'] ?? 0),
    'nome'     => (string)($o['nome'] ?? ''),
    'endereco' => (string)($o['endereco'] ?? ''),
    'lat'      => isset($o['latitude'])  && $o['latitude']  !== null ? (float)$o['latitude']  : null,
    'lng'      => isset($o['longitude']) && $o['longitude'] !== null ? (float)$o['longitude'] : null,
], $oficinas ?? []));

$veiculosJs = array_values(array_map(fn($v) => [
    'id' => (int)$v['id'],
    'label' => trim(($v['marca'] ?? '') . ' ' . ($v['modelo'] ?? '') . ' — ' . ($v['placa'] ?? '')),
], $veiculos ?? []));

include __DIR__ . '/../layouts/header.php';
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars($bp); ?>/public/assets/css/pages/client-pedido-novo.css?v=20260812-1">
<div class="main-wrapper">
<?php include __DIR__ . '/../layouts/sidebar_cliente.php'; ?>
<main class="main-content">

<?php if ($erro): ?>
<div class="alert alert-danger mb-3" role="alert">
    <?php
    $msgs = [
        'coordenadas_origem'  => 'Confirme sua localização de origem no mapa antes de enviar.',
        'coordenadas_destino' => 'Confirme o endereço de destino no mapa antes de enviar.',
        'veiculo'             => 'Veículo inválido. Selecione um dos seus veículos cadastrados.',
        'sem_cobertura'       => 'No momento não há nenhum prestador que alcance essa localização — o GuinchaFácil ainda não opera nessa região. Assim que houver cobertura, você poderá solicitar normalmente.',
    ];
    echo htmlspecialchars($msgs[$erro] ?? 'Corrija os campos indicados e tente novamente.');
    ?>
</div>
<?php endif; ?>

<?php if (empty($veiculos)): ?>
<div class="alert alert-warning mb-3">
    <i class="fas fa-triangle-exclamation me-2"></i>Você ainda não tem veículo cadastrado.
    <a href="<?php echo $bp; ?>/cliente/veiculo/novo" class="alert-link">Cadastrar veículo</a> antes de pedir socorro.
</div>
<?php endif; ?>

<div class="socorro-shell" id="socorroShell">
    <div id="map" class="socorro-map"></div>

    <!-- ── BUSCA DE ORIGEM (topo, sobre o mapa) ─────────────────────── -->
    <div class="socorro-topbar">
        <div class="socorro-search-pill">
            <i class="fas fa-circle-dot text-danger"></i>
            <input type="text" id="inputOrigem" placeholder="Onde você está agora?"
                   value="<?php echo htmlspecialchars((string)($pedidoRascunho['endereco_origem'] ?? '')); ?>"
                   autocomplete="off">
            <button type="button" id="btnGps" title="Usar minha localização atual">
                <i class="fas fa-location-crosshairs"></i>
            </button>
            <button type="button" id="btnBuscarOrigem" title="Buscar endereço">
                <i class="fas fa-search"></i>
            </button>
        </div>
        <div id="origemFeedback" class="socorro-origem-feedback"></div>
    </div>

    <!-- ── WIZARD (bottom sheet sobre o mapa) ───────────────────────── -->
    <form method="POST" action="<?php echo $bp; ?>/cliente/pedido/criar" id="formPedido" class="socorro-sheet" data-marketing-event="create_order">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken ?? ''); ?>">
        <input type="hidden" name="service_type_id" id="service_type_id" value="<?php echo !empty($triagemServiceType) ? (int)$triagemServiceType['id'] : ''; ?>">
        <input type="hidden" name="session_token" id="session_token" value="<?php echo htmlspecialchars(bin2hex(random_bytes(16))); ?>">
        <!-- Origem -->
        <input type="hidden" name="lat_origem"        id="lat_origem" value="<?php echo htmlspecialchars((string)($pedidoRascunho['lat_origem'] ?? '')); ?>">
        <input type="hidden" name="lng_origem"        id="lng_origem" value="<?php echo htmlspecialchars((string)($pedidoRascunho['lng_origem'] ?? '')); ?>">
        <input type="hidden" name="endereco_origem"   id="endereco_origem" value="<?php echo htmlspecialchars((string)($pedidoRascunho['endereco_origem'] ?? '')); ?>">
        <!-- Destino -->
        <input type="hidden" name="lat_destino"       id="lat_destino">
        <input type="hidden" name="lng_destino"       id="lng_destino">
        <input type="hidden" name="endereco_destino"  id="endereco_destino">
        <!-- Distância / tipo legado -->
        <input type="hidden" name="distancia_km"      id="distancia_km" value="0">
        <input type="hidden" name="tipo_problema"     id="tipo_problema" value="">
        <?php if (count($veiculosJs) === 1): ?>
        <input type="hidden" name="veiculo_id" id="veiculo_id" value="<?php echo (int)$veiculosJs[0]['id']; ?>">
        <?php endif; ?>

        <div class="socorro-sheet-handle"></div>
        <div class="socorro-progress" id="socorroProgress" aria-label="Progresso do pedido">
            <div class="socorro-progress-head"><strong id="socorroProgressTitle">Passo 1 de 3</strong><span id="socorroProgressState">Localiza&ccedil;&atilde;o</span></div>
            <div class="socorro-progress-track" role="progressbar" aria-valuemin="1" aria-valuemax="3" aria-valuenow="1" aria-label="Passo 1 de 3"><span id="socorroProgressBar"></span></div>
            <div class="socorro-progress-labels"><span class="is-current" data-progress-label="1">Localiza&ccedil;&atilde;o</span><span data-progress-label="2">Situa&ccedil;&atilde;o</span><span data-progress-label="3">Confirmar</span></div>
        </div>

        <!-- STEP 1: sintoma -->
        <div class="socorro-step" data-step="sintoma">
            <h1 class="socorro-title">O que aconteceu?</h1>
            <p class="socorro-subtitle">Toque na opção mais parecida — vamos te ajudar rapidinho.</p>
            <div class="socorro-chips">
                <button type="button" class="socorro-chip" data-symptom="NAO_LIGA"><i class="fas fa-car-battery"></i><span>Não liga</span></button>
                <button type="button" class="socorro-chip" data-symptom="PNEU"><i class="fas fa-circle-notch"></i><span>Pneu furado</span></button>
                <button type="button" class="socorro-chip" data-symptom="PAROU_TRAJETO"><i class="fas fa-car-burst"></i><span>Parou no trajeto</span></button>
                <button type="button" class="socorro-chip" data-symptom="CHAVE"><i class="fas fa-key"></i><span>Chave presa/perdida</span></button>
                <button type="button" class="socorro-chip" data-symptom="SEM_COMBUSTIVEL"><i class="fas fa-gas-pump"></i><span>Sem combustível</span></button>
                <button type="button" class="socorro-chip socorro-chip-risco" data-symptom="COLISAO"><i class="fas fa-triangle-exclamation"></i><span>Sofri colisão</span></button>
                <button type="button" class="socorro-chip" data-symptom="PRECISA_TRANSPORTAR"><i class="fas fa-truck-pickup"></i><span>Preciso transportar</span></button>
                <button type="button" class="socorro-chip" data-symptom="NAO_SEI"><i class="fas fa-circle-question"></i><span>Não sei dizer</span></button>
            </div>
        </div>

        <!-- STEP 2: perguntas rápidas (dinâmico por sintoma) -->
        <div class="socorro-step d-none" data-step="detalhes">
            <button type="button" class="socorro-back" data-back="sintoma"><i class="fas fa-arrow-left"></i> Voltar</button>
            <h1 class="socorro-title">Mais um detalhe</h1>

            <div class="socorro-q2" data-symptom-group="NAO_LIGA">
                <div class="socorro-toggle-row">
                    <span>O painel do carro acende?</span>
                    <div class="socorro-toggle" data-resposta="painel_acende"><button type="button" data-val="sim">Sim</button><button type="button" data-val="nao">Não</button></div>
                </div>
                <div class="socorro-toggle-row">
                    <span>O motor tenta girar?</span>
                    <div class="socorro-toggle" data-resposta="motor_gira"><button type="button" data-val="sim">Sim</button><button type="button" data-val="nao">Não</button></div>
                </div>
                <div class="socorro-toggle-row">
                    <span>As luzes estão fracas?</span>
                    <div class="socorro-toggle" data-resposta="luzes_fracas"><button type="button" data-val="sim">Sim</button><button type="button" data-val="nao">Não</button></div>
                </div>
                <div class="socorro-toggle-row">
                    <span>Apagou enquanto você dirigia?</span>
                    <div class="socorro-toggle" data-resposta="apagou_rodando"><button type="button" data-val="sim">Sim</button><button type="button" data-val="nao">Não</button></div>
                </div>
            </div>

            <div class="socorro-q2" data-symptom-group="PNEU">
                <div class="socorro-toggle-row">
                    <span>Você tem estepe em boas condições?</span>
                    <div class="socorro-toggle" data-resposta="estepe_existe"><button type="button" data-val="sim">Sim</button><button type="button" data-val="nao">Não</button></div>
                </div>
                <div class="socorro-toggle-row">
                    <span>Tem parafuso antifurto na roda?</span>
                    <div class="socorro-toggle" data-resposta="parafuso_antifurto"><button type="button" data-val="sim">Sim</button><button type="button" data-val="nao">Não</button></div>
                </div>
                <div class="socorro-toggle-row">
                    <span>A roda parece amassada/danificada?</span>
                    <div class="socorro-toggle" data-resposta="roda_danificada"><button type="button" data-val="sim">Sim</button><button type="button" data-val="nao">Não</button></div>
                </div>
                <div class="socorro-toggle-row">
                    <span>Quantos pneus foram afetados?</span>
                    <div class="socorro-toggle" data-resposta="pneus_afetados"><button type="button" data-val="1">1</button><button type="button" data-val="2">2 ou mais</button></div>
                </div>
            </div>

            <div class="socorro-q2" data-symptom-group="PAROU_TRAJETO">
                <div class="socorro-toggle-row">
                    <span>Sentiu cheiro de queimado ou viu fumaça?</span>
                    <div class="socorro-toggle" data-resposta="cheiro_queimado"><button type="button" data-val="sim">Sim</button><button type="button" data-val="nao">Não</button></div>
                </div>
                <div class="socorro-toggle-row">
                    <span>O veículo apagou em movimento?</span>
                    <div class="socorro-toggle" data-resposta="apagou_em_movimento"><button type="button" data-val="sim">Sim</button><button type="button" data-val="nao">Não</button></div>
                </div>
                <div class="socorro-toggle-row">
                    <span>Trocou a bateria recentemente?</span>
                    <div class="socorro-toggle" data-resposta="bateria_trocada_recente"><button type="button" data-val="sim">Sim</button><button type="button" data-val="nao">Não</button></div>
                </div>
            </div>

            <button type="button" class="socorro-continue" id="btnContinuarDetalhes">Continuar <i class="fas fa-arrow-right ms-1"></i></button>
        </div>

        <!-- STEP 3: resultado + confirmação -->
        <div class="socorro-step d-none" data-step="confirmar">
            <button type="button" class="socorro-back" data-back="auto"><i class="fas fa-arrow-left"></i> Voltar</button>

            <div id="resultadoTriagem"></div>

            <?php if (count($veiculosJs) > 1): ?>
            <div class="mb-3">
                <label class="form-label">Veículo</label>
                <select class="form-select" name="veiculo_id" id="veiculo_id_select" required>
                    <option value="">Selecione seu veículo…</option>
                    <?php foreach ($veiculosJs as $v): ?>
                    <option value="<?php echo (int)$v['id']; ?>"><?php echo htmlspecialchars($v['label']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <!-- Bloco de destino: só aparece quando o serviço exige (ex.: reboque) -->
            <div id="destinoBlock" class="d-none">
                <label class="form-label">
                    <i class="fas fa-flag-checkered text-primary me-1"></i>Para onde vai o veículo?
                    <span id="badgeDest" class="badge bg-danger ms-1">Não definido</span>
                </label>

                <?php if (!empty($oficinas)): ?>
                <ul class="nav nav-tabs nav-fill mb-2" id="destTabs" role="tablist" style="font-size:.83rem;">
                    <li class="nav-item"><button class="nav-link active" id="tabOficina" type="button" data-bs-toggle="tab" data-bs-target="#paneOficina"><i class="fas fa-wrench me-1"></i>Oficina Cadastrada</button></li>
                    <li class="nav-item"><button class="nav-link" id="tabOutro" type="button" data-bs-toggle="tab" data-bs-target="#paneOutro"><i class="fas fa-location-dot me-1"></i>Outro Endereço</button></li>
                </ul>
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="paneOficina">
                        <select class="form-select" id="selectOficina">
                            <option value="">Selecione uma oficina…</option>
                            <?php foreach ($oficinas as $o): ?>
                            <option value="<?php echo (int)$o['id']; ?>"
                                    data-lat="<?php echo htmlspecialchars((string)($o['latitude']  ?? '')); ?>"
                                    data-lng="<?php echo htmlspecialchars((string)($o['longitude'] ?? '')); ?>"
                                    data-end="<?php echo htmlspecialchars($o['endereco'] ?? $o['nome']); ?>"
                                    data-nome="<?php echo htmlspecialchars($o['nome']); ?>">
                                <?php echo htmlspecialchars($o['nome']); ?>
                                <?php if (!empty($o['endereco'])): ?> — <?php echo htmlspecialchars($o['endereco']); ?><?php endif; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="tab-pane fade" id="paneOutro">
                        <div class="input-group">
                            <input type="text" class="form-control" id="inputDest" placeholder="Rua, número, bairro, cidade…" autocomplete="off">
                            <button type="button" class="btn btn-outline-secondary" id="btnBuscarDestinoTab"><i class="fas fa-search"></i></button>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="input-group">
                    <input type="text" class="form-control" id="inputDest" placeholder="Rua, número, bairro, cidade…" autocomplete="off">
                    <button type="button" class="btn btn-outline-secondary" id="btnBuscarDestinoLivre"><i class="fas fa-search"></i></button>
                </div>
                <div class="form-text">Ou toque no mapa para marcar o ponto exato.</div>
                <?php endif; ?>
                <div id="destFeedback" class="form-text text-muted mt-1">Selecione uma oficina, busque um endereço ou toque no mapa.</div>
            </div>
            <div id="destinoLocalNota" class="alert alert-secondary small d-none">
                <i class="fas fa-location-dot me-1"></i>Atendimento será feito no local marcado acima — sem necessidade de destino.
            </div>

            <div class="row g-2 mb-3 d-none" id="custoRow">
                <div class="col-6">
                    <div class="card bg-dark bg-opacity-25 text-center py-2">
                        <div class="small text-muted mb-1"><i class="fas fa-route me-1"></i>Distância</div>
                        <div class="fw-bold" id="custoDistDisplay">— km</div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="card bg-dark bg-opacity-25 text-center py-2">
                        <div class="small text-muted mb-1"><i class="fas fa-coins me-1"></i>Custo Estimado</div>
                        <div class="fw-bold text-success" id="custoValDisplay">R$ —</div>
                    </div>
                </div>
            </div>
            <div class="small text-muted mb-3 d-none" id="custoDetalhe"></div>
            <div class="socorro-trust-note" role="status">
                <i class="fas fa-shield-heart me-1" aria-hidden="true"></i>
                <span><strong>Confira antes de confirmar.</strong> A cota&ccedil;&atilde;o oficial aparece nesta tela e o pagamento s&oacute; acontece no pr&oacute;ximo passo.</span>
            </div>

            <!-- Etapa 14: perguntas situacionais — repetidas a cada pedido porque
                 o estado do veículo muda (hoje normal, amanhã com roda travada).
                 Não bloqueiam o envio; ajudam a escolher/preparar o prestador certo. -->
            <div class="mb-3">
                <label class="form-label small text-muted mb-2">Sobre a situação agora (ajuda a mandar o prestador certo):</label>
                <div class="row g-2">
                    <div class="col-6 form-check ms-1">
                        <input type="checkbox" class="form-check-input" id="veiculo_esta_batido" name="veiculo_esta_batido" value="1">
                        <label class="form-check-label small" for="veiculo_esta_batido">Veículo está batido</label>
                    </div>
                    <div class="col-6 form-check ms-1">
                        <input type="checkbox" class="form-check-input" id="rodas_travadas" name="rodas_travadas" value="1">
                        <label class="form-check-label small" for="rodas_travadas">Rodas travadas</label>
                    </div>
                    <div class="col-6 form-check ms-1">
                        <input type="checkbox" class="form-check-input" id="local_dificil_acesso" name="local_dificil_acesso" value="1">
                        <label class="form-check-label small" for="local_dificil_acesso">Local de difícil acesso</label>
                    </div>
                    <div class="col-6 form-check ms-1">
                        <input type="checkbox" class="form-check-input" id="em_garagem_subsolo" name="em_garagem_subsolo" value="1">
                        <label class="form-check-label small" for="em_garagem_subsolo">Em garagem/subsolo</label>
                    </div>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Descrição (opcional)</label>
                <textarea class="form-control" name="descricao" rows="2" placeholder="Algo mais que devemos saber?" maxlength="500"></textarea>
            </div>

            <button type="submit" class="btn-socorro w-100 d-block text-center" id="btnSubmit" disabled>
                <i class="fas fa-circle-exclamation me-2"></i>Confirmar Pedido de Socorro
            </button>
            <p class="text-muted text-center mt-2 small" id="msgValidacao">Confirme sua localização para continuar.</p>
        </div>

        <div class="text-center mt-2">
            <button type="button" class="socorro-pular" id="btnPularTriagem">Prefiro escolher manualmente</button>
        </div>
    </form>
</div>

</main>
</div><!-- /main-wrapper -->

<style>
.socorro-shell {
    position: relative;
    height: calc(100vh - 64px - 4rem);
    min-height: 560px;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: var(--shadow, 0 4px 16px rgba(0,0,0,.25));
}
@media (max-width: 767.98px) {
    .socorro-progress-labels { font-size: .62rem; }
    #btnSubmit { position: sticky; bottom: 0; z-index: 2; min-height: 52px; box-shadow: 0 -8px 18px rgba(255,255,255,.9); }
    .socorro-shell { height: calc(100vh - 64px - 2rem); min-height: 480px; border-radius: 10px; }
}
.socorro-map { position: absolute; inset: 0; z-index: 1; }

.socorro-topbar { position: absolute; top: 12px; left: 12px; right: 12px; z-index: 950; pointer-events: none; }
.socorro-search-pill {
    pointer-events: auto;
    background: var(--theme-surf, #fff);
    border-radius: 999px;
    box-shadow: 0 4px 14px rgba(0,0,0,.28);
    display: flex; align-items: center; gap: .5rem;
    padding: .55rem .9rem;
    max-width: 460px;
}
.socorro-search-pill input {
    border: none; outline: none; background: transparent; flex: 1; font-size: .95rem; color: inherit;
}
.socorro-search-pill button {
    border: none; background: transparent; color: inherit; opacity: .75; padding: .25rem .4rem;
}
.socorro-search-pill button:hover { opacity: 1; }
.socorro-origem-feedback { pointer-events: none; margin-top: 6px; font-size: .78rem; color: #fff; text-shadow: 0 1px 3px rgba(0,0,0,.6); max-width: 460px; }

.socorro-sheet {
    pointer-events: auto;
    position: absolute; left: 0; right: 0; bottom: 0; z-index: 960;
    background: var(--theme-surf, #fff);
    border-radius: 18px 18px 0 0;
    box-shadow: 0 -6px 24px rgba(0,0,0,.3);
    padding: .75rem 1.25rem 1.25rem;
    max-height: 78%;
    overflow-y: auto;
}
.socorro-sheet-handle { width: 42px; height: 4px; border-radius: 2px; background: var(--theme-border, #ccc); margin: 0 auto .75rem; }
.socorro-progress { margin: 0 auto 1rem; max-width: 680px; }
.socorro-progress-head { display: flex; justify-content: space-between; gap: 1rem; font-size: .78rem; color: var(--theme-muted, #777); margin-bottom: .45rem; }
.socorro-progress-head strong { color: var(--theme-text, #172018); }
.socorro-progress-track { height: 6px; background: var(--theme-border, #ddd); border-radius: 99px; overflow: hidden; }
.socorro-progress-track span { display: block; width: 33.333%; height: 100%; background: var(--primary, #2fb34a); border-radius: inherit; transition: width .2s ease; }
.socorro-progress-labels { display: flex; justify-content: space-between; gap: .5rem; margin-top: .4rem; font-size: .67rem; color: var(--theme-muted, #888); }
.socorro-progress-labels span.is-current { color: var(--primary, #2fb34a); font-weight: 700; }
.socorro-trust-note { display: flex; gap: .45rem; align-items: flex-start; padding: .7rem .8rem; margin: 0 0 1rem; border: 1px solid rgba(47,179,74,.28); border-radius: 10px; background: rgba(47,179,74,.07); color: var(--theme-muted, #55705b); font-size: .78rem; }
.socorro-trust-note i { color: var(--primary, #2fb34a); margin-top: .1rem; }
.socorro-trust-note strong { color: var(--theme-text, #172018); }
.socorro-title { font-size: 1.25rem; font-weight: 700; margin-bottom: .15rem; }
.socorro-subtitle { color: var(--theme-muted, #888); font-size: .88rem; margin-bottom: 1rem; }

.socorro-chips { display: grid; grid-template-columns: repeat(2, 1fr); gap: .6rem; }
@media (min-width: 576px) { .socorro-chips { grid-template-columns: repeat(4, 1fr); } }
.socorro-chip {
    display: flex; flex-direction: column; align-items: center; gap: .4rem;
    border: 1px solid var(--theme-border, #ddd); border-radius: 12px; background: transparent;
    padding: 1rem .5rem; font-size: .82rem; color: inherit; min-height: 84px; justify-content: center;
}
.socorro-chip i { font-size: 1.4rem; }
.socorro-chip:active, .socorro-chip.is-selected { border-color: #f97316; background: rgba(249,115,22,.12); }
.socorro-chip-risco { border-color: rgba(220,53,69,.4); }

.socorro-back { border: none; background: transparent; color: var(--theme-muted, #888); padding: 0; margin-bottom: .5rem; font-size: .85rem; }
.socorro-q2 { display: none; }
.socorro-q2.is-active { display: block; }
.socorro-toggle-row { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: .6rem 0; border-bottom: 1px solid var(--theme-border, #eee); }
.socorro-toggle { display: flex; gap: .4rem; }
.socorro-toggle button { border: 1px solid var(--theme-border, #ddd); background: transparent; color: inherit; border-radius: 8px; padding: .3rem .8rem; font-size: .85rem; }
.socorro-toggle button.is-selected { background: #f97316; border-color: #f97316; color: #fff; }
.socorro-continue { width: 100%; margin-top: 1rem; border: none; border-radius: 10px; background: #f97316; color: #fff; padding: .75rem; font-weight: 600; }

.socorro-pular { border: none; background: transparent; color: var(--theme-muted, #888); font-size: .8rem; text-decoration: underline; padding: .5rem; }

#resultadoTriagem:not(:empty) { margin-bottom: 1rem; }
.socorro-reco { border: 1px solid var(--theme-border, #ddd); border-radius: 12px; padding: .85rem 1rem; margin-bottom: .5rem; }
.socorro-reco.is-risco { border-color: #dc3545; background: rgba(220,53,69,.08); }
</style>

<!-- Leaflet -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script<?php echo csp_script_nonce_attr(); ?> src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script<?php echo csp_script_nonce_attr(); ?> src="<?php echo htmlspecialchars($bp ?? ''); ?>/public/assets/js/core/gps-resilience.js"></script>

<script<?php echo csp_script_nonce_attr(); ?>>
(function () {
'use strict';

// ── Configuração injetada pelo PHP ──────────────────────────────────────────
const BASE_PATH = <?php echo json_encode($bp); ?>;
const OFICINAS  = <?php echo json_encode($oficinasJs, JSON_UNESCAPED_UNICODE); ?>;
const PEDIDO_RASCUNHO = <?php echo json_encode($pedidoRascunho ?? null, JSON_UNESCAPED_UNICODE); ?>;
const CSRF_TOKEN = <?php echo json_encode($csrfToken ?? ''); ?>;

// Mapa de sintoma → tipo_problema legado (coluna ENUM que continua existindo)
const TIPO_LEGADO = {
    NAO_LIGA: 'eletrica', PAROU_TRAJETO: 'eletrica', PNEU: 'pneu',
    CHAVE: 'outro', SEM_COMBUSTIVEL: 'combustivel', COLISAO: 'colisao',
    PRECISA_TRANSPORTAR: 'outro', NAO_SEI: 'outro'
};

// ── Estado ──────────────────────────────────────────────────────────────────
let map, markerOrigem, markerDest, routeLine;
let origemOk = false, destOk = false, destinoNecessario = true;
let mapMode  = 'origem';
let sintomaAtual = null;
const respostas = {};

// ── Ícones ──────────────────────────────────────────────────────────────────
const ICON_ORIGEM = L.divIcon({
    html: '<i class="fas fa-map-pin" style="color:#ef4444;font-size:30px;filter:drop-shadow(0 1px 2px rgba(0,0,0,.6))"></i>',
    iconSize: [30, 36], iconAnchor: [15, 36], className: ''
});
const ICON_DEST = L.divIcon({
    html: '<i class="fas fa-flag-checkered" style="color:#3b82f6;font-size:26px;filter:drop-shadow(0 1px 2px rgba(0,0,0,.6))"></i>',
    iconSize: [26, 32], iconAnchor: [13, 32], className: ''
});

// ── Rota de carro via OSRM ──────────────────────────────────────────────────
// URL centralizada via config 'por_road_match_base_url' (item #37 — antes
// hardcoded pro demo público router.project-osrm.org, que tem rate-limit/ToS
// que proíbem produção).
const OSRM_BASE_URL = <?php echo json_encode($osrmBaseUrl, JSON_UNESCAPED_SLASHES); ?>;

async function calcularRotaCarro(lat1, lng1, lat2, lng2) {
    const url = `${OSRM_BASE_URL}/route/v1/driving/${lng1},${lat1};${lng2},${lat2}`
              + `?overview=full&geometries=geojson`;
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 6000);
    try {
        const r = await fetch(url, { signal: controller.signal });
        if (!r.ok) return null;
        const d = await r.json();
        if (d.code !== 'Ok' || !d.routes || !d.routes.length) return null;
        const route  = d.routes[0];
        const distKm = route.distance / 1000;
        const coords = route.geometry.coordinates.map(c => [c[1], c[0]]);
        return { distKm, coords };
    } catch { return null; }
    finally { clearTimeout(timeoutId); }
}

// ── Inicializa mapa ─────────────────────────────────────────────────────────
function initMap() {
    map = L.map('map', { zoomControl: true }).setView([-23.55052, -46.633308], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19, attribution: '© OpenStreetMap'
    }).addTo(map);

    map.on('click', function (e) {
        const { lat, lng } = e.latlng;
        if (mapMode === 'origem') {
            setOrigem(lat, lng, null);
            reverseGeocode(lat, lng).then(end => {
                document.getElementById('endereco_origem').value = end;
                document.getElementById('inputOrigem').value     = end;
            });
        } else {
            setDest(lat, lng, null);
            reverseGeocode(lat, lng).then(end => {
                document.getElementById('endereco_destino').value = end;
                const inp = document.getElementById('inputDest');
                if (inp) inp.value = end;
            });
        }
    });

    tentarGPSsilencioso();
}

function setMapMode(modo) { mapMode = modo; }

// ── Set Origem ──────────────────────────────────────────────────────────────
function setOrigem(lat, lng, endereco) {
    document.getElementById('lat_origem').value = lat;
    document.getElementById('lng_origem').value = lng;
    if (endereco) {
        document.getElementById('endereco_origem').value = endereco;
        document.getElementById('inputOrigem').value     = endereco;
    }

    if (markerOrigem) markerOrigem.setLatLng([lat, lng]);
    else markerOrigem = L.marker([lat, lng], { icon: ICON_ORIGEM, draggable: true }).addTo(map)
            .on('dragend', e => {
                const p = e.target.getLatLng();
                setOrigem(p.lat, p.lng, null);
                reverseGeocode(p.lat, p.lng).then(end => {
                    document.getElementById('endereco_origem').value = end;
                    document.getElementById('inputOrigem').value     = end;
                });
                recalcularOuCopiar();
            });

    origemOk = true;
    map.setView([lat, lng], Math.max(map.getZoom(), 14));
    recalcularOuCopiar();
    checkSubmit();
}

// ── Set Destino ─────────────────────────────────────────────────────────────
function setDest(lat, lng, endereco) {
    document.getElementById('lat_destino').value = lat;
    document.getElementById('lng_destino').value = lng;
    if (endereco) {
        document.getElementById('endereco_destino').value = endereco;
        const inp = document.getElementById('inputDest');
        if (inp) inp.value = endereco;
    }

    if (markerDest) markerDest.setLatLng([lat, lng]);
    else markerDest = L.marker([lat, lng], { icon: ICON_DEST, draggable: true }).addTo(map)
            .on('dragend', e => {
                const p = e.target.getLatLng();
                setDest(p.lat, p.lng, null);
                reverseGeocode(p.lat, p.lng).then(end => {
                    document.getElementById('endereco_destino').value = end;
                    const inp = document.getElementById('inputDest');
                    if (inp) inp.value = end;
                });
                recalcularOuCopiar();
            });

    destOk = true;
    const badge = document.getElementById('badgeDest');
    if (badge) { badge.textContent = 'Definido ✓'; badge.className = 'badge bg-success ms-1'; }
    recalcularOuCopiar();
    checkSubmit();
}

// Quando o serviço não exige destino (ex.: bateria/pneu no local), o
// "destino" do pedido é o próprio local do atendimento — evita pedir ao
// usuário uma informação que ele não tem por que dar duas vezes.
function recalcularOuCopiar() {
    if (!destinoNecessario) {
        if (origemOk) {
            const lat = document.getElementById('lat_origem').value;
            const lng = document.getElementById('lng_origem').value;
            const end = document.getElementById('endereco_origem').value;
            document.getElementById('lat_destino').value = lat;
            document.getElementById('lng_destino').value = lng;
            document.getElementById('endereco_destino').value = end;
            destOk = true;
        }
        checkSubmit();
        return;
    }
    recalcular();
}

// ── Recalcula rota de carro e custo ─────────────────────────────────────────
async function recalcular() {
    if (!origemOk || !destOk) { checkSubmit(); return; }

    const lat1 = parseFloat(document.getElementById('lat_origem').value);
    const lng1 = parseFloat(document.getElementById('lng_origem').value);
    const lat2 = parseFloat(document.getElementById('lat_destino').value);
    const lng2 = parseFloat(document.getElementById('lng_destino').value);
    if ([lat1, lng1, lat2, lng2].some(Number.isNaN)) { checkSubmit(); return; }

    const custoRow = document.getElementById('custoRow');
    document.getElementById('custoDistDisplay').textContent = '…';
    document.getElementById('custoValDisplay').textContent  = 'calculando…';
    if (custoRow) custoRow.classList.remove('d-none');

    if (routeLine) { routeLine.remove(); routeLine = null; }

    const rota = (lat1 === lat2 && lng1 === lng2) ? null : await calcularRotaCarro(lat1, lng1, lat2, lng2);

    let dist;
    if (lat1 === lat2 && lng1 === lng2) {
        dist = 0;
    } else if (rota) {
        dist = rota.distKm;
        routeLine = L.polyline(rota.coords, { color: '#f97316', weight: 4, opacity: .85 }).addTo(map);
        map.fitBounds(routeLine.getBounds(), { padding: [40, 120] });
    } else {
        const R = 6371, rad = Math.PI / 180;
        const dLat = (lat2 - lat1) * rad, dLng = (lng2 - lng1) * rad;
        const a = Math.sin(dLat/2)**2 + Math.cos(lat1*rad)*Math.cos(lat2*rad)*Math.sin(dLng/2)**2;
        dist = R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
        routeLine = L.polyline([[lat1, lng1], [lat2, lng2]], { color: '#94a3b8', weight: 3, dashArray: '8,5', opacity: .7 }).addTo(map);
        map.fitBounds(routeLine.getBounds(), { padding: [40, 120] });
    }

    document.getElementById('distancia_km').value = dist.toFixed(2);
    document.getElementById('custoDistDisplay').textContent = dist.toFixed(1) + ' km';
    await atualizarEstimativaCusto(dist);
    checkSubmit();
}

async function atualizarEstimativaCusto(distanciaKm) {
    const veiculoId = document.getElementById('veiculo_id')?.value || document.getElementById('veiculo_id_select')?.value || '';
    const custoValDisplay = document.getElementById('custoValDisplay');
    const custoDetalhe = document.getElementById('custoDetalhe');
    if (!custoValDisplay) return;
    if (!veiculoId) {
        custoValDisplay.textContent = 'Selecione o veículo';
        custoDetalhe.classList.remove('d-none');
        custoDetalhe.textContent = 'A estimativa oficial depende da categoria tarifária do veículo.';
        return;
    }
    try {
        const url = new URL(BASE_PATH + '/cliente/pedido/custo', window.location.origin);
        url.searchParams.set('distancia_km', distanciaKm.toFixed(2));
        url.searchParams.set('veiculo_id', veiculoId);
        // §DESLOCAMENTO-01: sem service_type_id/lat/lng, o servidor não tem
        // como saber se este é um serviço ON_SITE (com tarifa própria de
        // deslocamento) nem se há zona de precificação aplicável — sempre
        // caía na tarifa de reboque, mesmo pra partida auxiliar/pneu/etc.
        const serviceTypeId = document.getElementById('service_type_id')?.value || '';
        if (serviceTypeId) url.searchParams.set('service_type_id', serviceTypeId);
        const latOrigem = document.getElementById('lat_origem')?.value || '';
        const lngOrigem = document.getElementById('lng_origem')?.value || '';
        if (latOrigem && lngOrigem) {
            url.searchParams.set('lat_origem', latOrigem);
            url.searchParams.set('lng_origem', lngOrigem);
        }
        const response = await fetch(url.toString(), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
        const payload = await response.json();
        if (!response.ok || !payload.ok) throw new Error(payload.erro || 'Falha ao calcular o custo');

        const tarifa = payload.tarifa || {};
        custoValDisplay.textContent = 'R$ ' + Number(payload.custo || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const detalhes = [];
        if (payload.origem === 'zona') {
            detalhes.push(`preço da zona "${tarifa.zona_nome || ''}"`);
        } else if (payload.origem === 'servico') {
            detalhes.push(`taxa de deslocamento R$ ${Number(tarifa.deslocamento_preco_km || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}/km`);
            detalhes.push(`base R$ ${Number(tarifa.base_fee || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`);
        } else {
            detalhes.push(`${Number(tarifa.tarifa_km_aplicada || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}/km`);
            detalhes.push(`taxa fixa R$ ${Number(tarifa.taxa_fixa_aplicada || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`);
            detalhes.push(`categoria ${String(tarifa.categoria || 'popular').replace('_', ' ')}`);
            if (Number(tarifa.taxa_prioridade || 0) > 0) detalhes.push(`prioridade R$ ${Number(tarifa.taxa_prioridade || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`);
        }
        if (tarifa.noturno || tarifa.is_noturno) detalhes.push('adicional noturno aplicado');
        if (tarifa.feriado) detalhes.push('adicional de feriado aplicado');
        custoDetalhe.classList.remove('d-none');
        custoDetalhe.textContent = detalhes.join(' • ');
    } catch (error) {
        custoValDisplay.textContent = 'indisponível';
        custoDetalhe.classList.remove('d-none');
        custoDetalhe.textContent = error instanceof Error ? error.message : 'Não foi possível calcular a tarifa oficial agora.';
    }
}

function checkSubmit() {
    const veiculoOk = !!(document.getElementById('veiculo_id')?.value || document.getElementById('veiculo_id_select')?.value);
    const ok = origemOk && destOk && veiculoOk;
    const btn = document.getElementById('btnSubmit');
    if (!btn) return;
    btn.disabled = !ok;
    document.getElementById('msgValidacao').hidden = ok;
}

// ── Geocodificação ───────────────────────────────────────────────────────────
async function nominatim(query) {
    const url = new URL(BASE_PATH + '/geocode', window.location.origin);
    url.searchParams.set('q', query);
    try {
        const r = await fetch(url.toString(), { headers: { 'Accept': 'application/json' } });
        const d = await r.json();
        if (!d || !d.ok || !d.result) return null;
        return { lat: parseFloat(d.result.lat), lng: parseFloat(d.result.lng), display: d.result.display_name || query };
    } catch { return null; }
}

async function reverseGeocode(lat, lng) {
    const url = new URL(BASE_PATH + '/geocode/reverse', window.location.origin);
    url.searchParams.set('lat', lat);
    url.searchParams.set('lng', lng);
    try {
        const r = await fetch(url.toString(), { headers: { 'Accept': 'application/json' } });
        const d = await r.json();
        return d && d.ok && d.result && d.result.display_name ? d.result.display_name : `${lat.toFixed(5)}, ${lng.toFixed(5)}`;
    } catch { return `${lat.toFixed(5)}, ${lng.toFixed(5)}`; }
}

async function geocodeOrigem() {
    const q = document.getElementById('inputOrigem').value.trim();
    if (!q) return;
    const btn = document.getElementById('btnBuscarOrigem');
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>'; btn.disabled = true;
    const res = await nominatim(q);
    btn.innerHTML = '<i class="fas fa-search"></i>'; btn.disabled = false;
    if (res) {
        setOrigem(res.lat, res.lng, res.display);
        document.getElementById('origemFeedback').innerHTML = '<i class="fas fa-check-circle"></i> Localização encontrada.';
    } else {
        document.getElementById('origemFeedback').innerHTML = '<i class="fas fa-triangle-exclamation"></i> Não encontramos esse endereço — toque no mapa para marcar.';
    }
}

async function geocodeDest() {
    const inp = document.getElementById('inputDest');
    const q = inp ? inp.value.trim() : '';
    if (!q) return;
    const search = inp.nextElementSibling;
    if (search) { search.innerHTML = '<i class="fas fa-spinner fa-spin"></i>'; search.disabled = true; }
    const res = await nominatim(q);
    if (search) { search.innerHTML = '<i class="fas fa-search"></i>'; search.disabled = false; }
    if (res) {
        setDest(res.lat, res.lng, res.display);
        document.getElementById('destFeedback').innerHTML = '<i class="fas fa-check-circle text-success"></i> Destino encontrado.';
    } else {
        document.getElementById('destFeedback').innerHTML = '<i class="fas fa-triangle-exclamation text-warning"></i> Endereço não encontrado — toque no mapa.';
        setMapMode('destino');
    }
}

// Gate de precisão (gap real: um fix ruim — 1-2 km de erro — virava origem
// silenciosamente e o guincho ia pro lugar errado). Acima de 500m avisamos e
// pedimos confirmação/ajuste manual no mapa (arrastar o pino já é suportado).
function avaliarPrecisaoOrigem(accuracy) {
    const nivel = window.GpsResilience ? window.GpsResilience.accuracyLevel(accuracy) : { key: 'unknown' };
    const fb = document.getElementById('origemFeedback');
    if (!fb) return nivel;
    if (nivel.key === 'poor') {
        fb.innerHTML = '<i class="fas fa-triangle-exclamation text-warning"></i> GPS impreciso (~' + Math.round(accuracy) + 'm de erro). Confira o pino no mapa ou arraste-o até o local certo.';
    } else if (nivel.key === 'fair') {
        fb.innerHTML = '<i class="fas fa-circle-exclamation text-warning"></i> Localização aproximada (~' + Math.round(accuracy) + 'm). Ajuste o pino se necessário.';
    }
    return nivel;
}

function usarGPS() {
    if (!navigator.geolocation) { alert('Seu navegador não suporta geolocalização.'); return; }
    const btn = document.getElementById('btnGps');
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>'; btn.disabled = true;
    navigator.geolocation.getCurrentPosition(
        pos => {
            btn.innerHTML = '<i class="fas fa-location-crosshairs"></i>'; btn.disabled = false;
            const { latitude: lat, longitude: lng, accuracy } = pos.coords;
            setOrigem(lat, lng, null);
            const nivel = avaliarPrecisaoOrigem(accuracy);
            reverseGeocode(lat, lng).then(end => {
                document.getElementById('endereco_origem').value = end;
                document.getElementById('inputOrigem').value     = end;
                if (nivel.key !== 'poor' && nivel.key !== 'fair') {
                    document.getElementById('origemFeedback').innerHTML = '<i class="fas fa-check-circle"></i> Localização via GPS.';
                }
            });
        },
        (err) => {
            btn.innerHTML = '<i class="fas fa-location-crosshairs"></i>'; btn.disabled = false;
            const msg = window.GpsResilience ? window.GpsResilience.geoErrorMessage(err) : 'GPS indisponível.';
            document.getElementById('origemFeedback').innerHTML = '<i class="fas fa-triangle-exclamation"></i> ' + msg + ' Digite o endereço ou toque no mapa.';
        },
        { enableHighAccuracy: true, timeout: 10000 }
    );
}

function tentarGPSsilencioso() {
    if (!navigator.geolocation || origemOk) return;
    navigator.geolocation.getCurrentPosition(
        pos => {
            if (origemOk) return;
            const { latitude: lat, longitude: lng, accuracy } = pos.coords;
            setOrigem(lat, lng, null);
            const nivel = avaliarPrecisaoOrigem(accuracy);
            reverseGeocode(lat, lng).then(end => {
                document.getElementById('endereco_origem').value = end;
                document.getElementById('inputOrigem').value     = end;
                if (nivel.key !== 'poor' && nivel.key !== 'fair') {
                    document.getElementById('origemFeedback').innerHTML = '<i class="fas fa-check-circle"></i> Localização detectada automaticamente.';
                }
            });
        },
        () => {},
        { enableHighAccuracy: false, timeout: 5000 }
    );
}

async function selecionarOficina(sel) {
    const opt = sel.options[sel.selectedIndex];
    if (!opt || !opt.value) return;
    const lat = parseFloat(opt.dataset.lat || ''), lng = parseFloat(opt.dataset.lng || '');
    const end = opt.dataset.end || opt.dataset.nome || opt.text, nome = opt.dataset.nome || opt.text;
    if (lat && lng && !isNaN(lat) && !isNaN(lng)) {
        setDest(lat, lng, nome + (end ? ' — ' + end : ''));
        document.getElementById('destFeedback').innerHTML = '<i class="fas fa-check-circle text-success"></i> Oficina selecionada.';
    } else {
        document.getElementById('destFeedback').innerHTML = '<i class="fas fa-spinner fa-spin"></i> Buscando localização da oficina…';
        const res = await nominatim((end || nome) + ', Brasil');
        if (res) {
            setDest(res.lat, res.lng, nome);
            document.getElementById('destFeedback').innerHTML = '<i class="fas fa-check-circle text-success"></i> Localização encontrada.';
        } else {
            document.getElementById('destFeedback').innerHTML = '<i class="fas fa-triangle-exclamation text-warning"></i> Não localizamos a oficina — use "Outro Endereço".';
        }
    }
}

// ── WIZARD ────────────────────────────────────────────────────────────────
function atualizarProgresso(nome) {
    const mapa = { sintoma: 1, detalhes: 2, confirmar: 3 };
    const titulos = { sintoma: 'Localiza&ccedil;&atilde;o', detalhes: 'Situa&ccedil;&atilde;o', confirmar: 'Confirmar' };
    const passo = mapa[nome] || 1;
    const title = document.getElementById('socorroProgressTitle');
    const state = document.getElementById('socorroProgressState');
    const bar = document.getElementById('socorroProgressBar');
    const track = document.querySelector('.socorro-progress-track');
    if (title) title.textContent = 'Passo ' + passo + ' de 3';
    if (state) state.innerHTML = titulos[nome] || titulos.sintoma;
    if (bar) bar.style.width = ((passo / 3) * 100) + '%';
    if (track) track.setAttribute('aria-valuenow', String(passo));
    document.querySelectorAll('[data-progress-label]').forEach(label => {
        label.classList.toggle('is-current', Number(label.dataset.progressLabel) === passo);
        label.classList.toggle('is-done', Number(label.dataset.progressLabel) < passo);
    });
}

function mostrarStep(nome) {
    document.querySelectorAll('.socorro-step').forEach(el => {
        el.classList.toggle('d-none', el.dataset.step !== nome);
    });
    atualizarProgresso(nome);
    document.getElementById('socorroShell').scrollIntoView({ block: 'start', behavior: 'smooth' });
}

function temPerguntasExtras(sintoma) {
    return ['NAO_LIGA', 'PNEU', 'PAROU_TRAJETO'].includes(sintoma);
}

async function avaliarTriagemEIrParaConfirmar() {
    document.getElementById('resultadoTriagem').innerHTML = '<p class="text-muted small"><i class="fas fa-spinner fa-spin me-1"></i>Analisando…</p>';
    mostrarStep('confirmar');

    const form = new FormData();
    form.set('csrf_token', CSRF_TOKEN);
    form.set('symptom_code', sintomaAtual || 'NAO_SEI');
    form.set('session_token', document.getElementById('session_token').value);
    Object.keys(respostas).forEach(k => form.set('resposta[' + k + ']', respostas[k]));

    let dados = null;
    try {
        const r = await fetch(BASE_PATH + '/cliente/triagem/avaliar', { method: 'POST', body: form, headers: { 'Accept': 'application/json' } });
        dados = await r.json();
    } catch { dados = null; }

    if (!dados || !dados.ok) {
        document.getElementById('resultadoTriagem').innerHTML =
            '<div class="socorro-reco"><i class="fas fa-circle-info me-1"></i>Não conseguimos analisar automaticamente agora — sem problema, escolha o veículo e o destino abaixo que um atendente vai te ajudar.</div>';
        aplicarDestinoNecessario(true);
        document.getElementById('tipo_problema').value = TIPO_LEGADO[sintomaAtual] || 'outro';
        checkSubmit();
        return;
    }

    document.getElementById('tipo_problema').value = TIPO_LEGADO[sintomaAtual] || 'outro';

    let html = '';
    if (dados.recomendado) {
        document.getElementById('service_type_id').value = dados.recomendado.id;
        html += `<div class="socorro-reco${dados.safety_risk ? ' is-risco' : ''}">`
              + (dados.safety_risk ? '<i class="fas fa-triangle-exclamation text-danger me-1"></i>' : '<i class="fas fa-clipboard-check text-success me-1"></i>')
              + `<strong>${dados.recomendado.name}</strong><br><span class="small">${dados.explicacao || ''}</span></div>`;
        aplicarDestinoNecessario(!!dados.recomendado.requires_destination);
    } else {
        html += `<div class="socorro-reco"><i class="fas fa-circle-info me-1"></i>${dados.explicacao || 'Vamos avaliar seu caso no local.'}</div>`;
        aplicarDestinoNecessario(true);
    }

    if (Array.isArray(dados.alternativas) && dados.alternativas.length) {
        html += '<p class="small text-muted mb-1">Outras opções:</p><div class="d-flex flex-wrap gap-2 mb-2">';
        dados.alternativas.forEach(alt => {
            html += `<button type="button" class="socorro-chip" style="min-height:auto;padding:.4rem .8rem;" `
                  + `onclick="window.__selecionarAlternativa(${alt.id}, ${alt.requires_destination ? 'true' : 'false'})"><span>${alt.name}</span></button>`;
        });
        html += '</div>';
    }

    document.getElementById('resultadoTriagem').innerHTML = html;
    checkSubmit();
}
window.__selecionarAlternativa = function (id, requiresDestino) {
    document.getElementById('service_type_id').value = id;
    aplicarDestinoNecessario(requiresDestino);
    checkSubmit();
};

function aplicarDestinoNecessario(necessario) {
    destinoNecessario = necessario;
    const bloco = document.getElementById('destinoBlock');
    const nota = document.getElementById('destinoLocalNota');
    if (necessario) {
        bloco.classList.remove('d-none');
        nota.classList.add('d-none');
        destOk = false;
        document.getElementById('lat_destino').value = '';
        document.getElementById('lng_destino').value = '';
        document.getElementById('endereco_destino').value = '';
    } else {
        bloco.classList.add('d-none');
        nota.classList.remove('d-none');
        recalcularOuCopiar();
    }
}

document.addEventListener('DOMContentLoaded', function () {
    initMap();

    document.getElementById('btnBuscarOrigem')?.addEventListener('click', geocodeOrigem);
    document.getElementById('btnGps')?.addEventListener('click', usarGPS);
    const inputOrigem = document.getElementById('inputOrigem');
    inputOrigem?.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); geocodeOrigem(); } });

    document.querySelectorAll('.socorro-chip[data-symptom]').forEach(chip => {
        chip.addEventListener('click', function () {
            document.querySelectorAll('.socorro-chip[data-symptom]').forEach(c => c.classList.remove('is-selected'));
            this.classList.add('is-selected');
            sintomaAtual = this.dataset.symptom;

            if (temPerguntasExtras(sintomaAtual)) {
                document.querySelectorAll('.socorro-q2').forEach(g => g.classList.toggle('is-active', g.dataset.symptomGroup === sintomaAtual));
                mostrarStep('detalhes');
            } else {
                avaliarTriagemEIrParaConfirmar();
            }
        });
    });

    document.querySelectorAll('.socorro-toggle').forEach(grupo => {
        grupo.querySelectorAll('button').forEach(btn => {
            btn.addEventListener('click', function () {
                grupo.querySelectorAll('button').forEach(b => b.classList.remove('is-selected'));
                this.classList.add('is-selected');
                respostas[grupo.dataset.resposta] = this.dataset.val;
            });
        });
    });

    document.getElementById('btnContinuarDetalhes')?.addEventListener('click', avaliarTriagemEIrParaConfirmar);

    document.querySelectorAll('.socorro-back').forEach(btn => {
        btn.addEventListener('click', function () {
            const alvo = this.dataset.back === 'auto' ? (temPerguntasExtras(sintomaAtual) ? 'detalhes' : 'sintoma') : this.dataset.back;
            mostrarStep(alvo);
        });
    });

    document.getElementById('btnPularTriagem')?.addEventListener('click', function () {
        sintomaAtual = sintomaAtual || 'NAO_SEI';
        document.getElementById('tipo_problema').value = TIPO_LEGADO[sintomaAtual] || 'outro';
        aplicarDestinoNecessario(true);
        document.getElementById('resultadoTriagem').innerHTML =
            '<div class="socorro-reco"><i class="fas fa-circle-info me-1"></i>Sem problema — preencha destino e veículo abaixo.</div>';
        mostrarStep('confirmar');
        checkSubmit();
    });

    document.getElementById('btnBuscarDestinoTab')?.addEventListener('click', geocodeDest);
    document.getElementById('btnBuscarDestinoLivre')?.addEventListener('click', geocodeDest);
    document.getElementById('selectOficina')?.addEventListener('change', function () { selecionarOficina(this); });
    document.getElementById('inputDest')?.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); geocodeDest(); } });
    document.getElementById('veiculo_id_select')?.addEventListener('change', function () {
        checkSubmit();
        if (origemOk && destOk) recalcularOuCopiar();
    });

    const tabOutro = document.getElementById('tabOutro');
    tabOutro?.addEventListener('shown.bs.tab', () => setMapMode('destino'));

    if (PEDIDO_RASCUNHO && PEDIDO_RASCUNHO.lat_origem && PEDIDO_RASCUNHO.lng_origem) {
        const lat = parseFloat(PEDIDO_RASCUNHO.lat_origem), lng = parseFloat(PEDIDO_RASCUNHO.lng_origem);
        if (!Number.isNaN(lat) && !Number.isNaN(lng)) setOrigem(lat, lng, PEDIDO_RASCUNHO.endereco_origem || '');
    }

    <?php if (!empty($triagemServiceType)): ?>
    // Veio de /cliente/triagem/resultado (link direto) — já pula pro passo 3.
    aplicarDestinoNecessario(<?php echo ServiceType::requiresDestination($triagemServiceType) ? 'true' : 'false'; ?>);
    document.getElementById('resultadoTriagem').innerHTML =
        '<div class="socorro-reco"><i class="fas fa-clipboard-check text-success me-1"></i><strong>' +
        <?php echo json_encode($triagemServiceType['name'], JSON_UNESCAPED_UNICODE); ?> + '</strong></div>';
    document.getElementById('tipo_problema').value = 'outro';
    mostrarStep('confirmar');
    <?php endif; ?>

    checkSubmit();
});

})();
</script>
<?php include __DIR__ . '/../layouts/footer.php'; ?>

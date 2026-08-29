<?php
$bp = defined('BASE_PATH') ? BASE_PATH : '';
$mostrarMercadoPago = $mostrarMercadoPago ?? true;
$mostrarPagSeguro = $mostrarPagSeguro ?? false;
$mpPublicKey = $mpPublicKey ?? '';
$pedidoId = (int)($pedido['id'] ?? 0);
$valorPedido = (float)($pedido['custo_estimado'] ?? 0);
$psScriptBase = (defined('PS_ENV') && PS_ENV === 'production')
    ? 'https://stc.pagseguro.uol.com.br'
    : 'https://stc.sandbox.pagseguro.uol.com.br';
include __DIR__ . '/../layouts/header.php';
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars($bp); ?>/public/assets/css/pages/cliente-pagamento.css">
<div class="main-wrapper">
<?php include __DIR__ . '/../layouts/sidebar_cliente.php'; ?>
<main class="main-content">
    <header class="page-head mb-4">
        <div>
            <span class="eyebrow">Pagamento</span>
            <h1><i class="fas fa-credit-card me-2 text-primary-custom"></i>Checkout do Pagamento</h1>
            <p>Pedido #<?php echo $pedidoId; ?> — valor: R$ <?php echo number_format($valorPedido, 2, ',', '.'); ?>. Finalize sem sair desta página.</p>
        </div>
    </header>

    <div id="checkout-mensagem" class="alert d-none" role="alert"></div>

    <?php if (!$mostrarMercadoPago && !$mostrarPagSeguro): ?>
        <div class="alert alert-warning" role="alert">
            Nenhum provedor de pagamento está disponível no momento.
        </div>
    <?php endif; ?>

    <?php
        $mapaProblema = [
            'mecanico'    => 'Problema mecânico',
            'eletrico'    => 'Problema elétrico',
            'pneu'        => 'Pneu',
            'bateria'     => 'Bateria',
            'combustivel' => 'Falta de combustível',
            'acidente'    => 'Acidente',
            'colisao'     => 'Colisão',
            'outro'       => 'Outro',
        ];
        $descricaoProblema = $mapaProblema[$pedido['tipo_problema'] ?? ''] ?? ($pedido['tipo_problema'] ?? '—');
        $veiculoDescricao = trim(
            (string)($pedido['marca'] ?? '') . ' ' . (string)($pedido['modelo'] ?? '')
        );
        $criadoEm = !empty($pedido['criado_em']) ? date('d/m/Y H:i', strtotime($pedido['criado_em'])) : null;
    ?>

    <div class="row g-4 checkout-row">
        <!-- Recibo / resumo da solicitação -->
        <div class="col-12 col-lg-5 col-xl-4">
            <div class="card h-100 checkout-recibo">
                <div class="card-header"><i class="fas fa-receipt me-2"></i>Resumo da solicitação</div>
                <div class="card-body">
                    <div class="recibo-linha recibo-total">
                        <span>Total a pagar</span>
                        <strong>R$ <?php echo number_format($valorPedido, 2, ',', '.'); ?></strong>
                    </div>

                    <hr class="config-hr">

                    <dl class="recibo-lista">
                        <dt>Pedido</dt>
                        <dd>#<?php echo $pedidoId; ?><?php echo $criadoEm ? ' — ' . htmlspecialchars($criadoEm) : ''; ?></dd>

                        <dt>Problema relatado</dt>
                        <dd><?php echo htmlspecialchars($descricaoProblema); ?></dd>

                        <?php if ($veiculoDescricao !== ''): ?>
                        <dt>Veículo</dt>
                        <dd>
                            <?php echo htmlspecialchars($veiculoDescricao); ?>
                            <?php if (!empty($pedido['placa'])): ?>
                                — placa <?php echo htmlspecialchars($pedido['placa']); ?>
                            <?php endif; ?>
                        </dd>
                        <?php endif; ?>

                        <dt>Origem</dt>
                        <dd><?php echo htmlspecialchars($pedido['endereco_origem'] ?? '—'); ?></dd>

                        <dt>Destino</dt>
                        <dd><?php echo htmlspecialchars($pedido['endereco_destino'] ?? '—'); ?></dd>

                        <?php if (!empty($pedido['distancia_km'])): ?>
                        <dt>Distância estimada</dt>
                        <dd><?php echo number_format((float)$pedido['distancia_km'], 1, ',', '.'); ?> km</dd>
                        <?php endif; ?>

                        <dt>Cliente</dt>
                        <dd><?php echo htmlspecialchars($pedido['cliente_nome'] ?? '—'); ?></dd>
                    </dl>

                    <hr class="config-hr">

                    <div class="recibo-linha">
                        <span>Valor do serviço</span>
                        <span>R$ <?php echo number_format($valorPedido, 2, ',', '.'); ?></span>
                    </div>
                    <div class="recibo-linha recibo-total">
                        <span>Total</span>
                        <strong>R$ <?php echo number_format($valorPedido, 2, ',', '.'); ?></strong>
                    </div>

                    <p class="text-muted small mt-3 mb-0">
                        <i class="fas fa-lock me-1"></i>Pagamento processado direto pelo gateway — seus dados de cartão não passam pelos nossos servidores.
                    </p>
                </div>
            </div>
        </div>

        <!-- Formulário de checkout -->
        <div class="col-12 col-lg-7 col-xl-8">
            <div class="row g-4">
                <?php if ($mostrarMercadoPago): ?>
                <div class="col-12">
                    <div class="card h-100">
                        <div class="card-header"><i class="fas fa-bolt me-2"></i>MercadoPago</div>
                        <div class="card-body">
                            <p class="text-muted">Cartão, Pix ou boleto — aprovação sem sair desta página.</p>
                            <div id="mp-payment-brick-container"></div>
                            <div id="mp-payment-status" class="mt-3"></div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($mostrarPagSeguro): ?>
                <div class="col-12">
                    <div class="card h-100">
                        <div class="card-header"><i class="fas fa-building-columns me-2"></i>PagSeguro</div>
                        <div class="card-body">
                            <p class="text-muted">Cartão ou boleto via PagSeguro, sem sair desta página.</p>

                            <div class="btn-group w-100 mb-3" role="group">
                                <input type="radio" class="btn-check" name="ps-metodo" id="ps-metodo-cartao" value="creditCard" checked>
                                <label class="btn btn-outline-success" for="ps-metodo-cartao">Cartão de crédito</label>
                                <input type="radio" class="btn-check" name="ps-metodo" id="ps-metodo-boleto" value="boleto">
                                <label class="btn btn-outline-success" for="ps-metodo-boleto">Boleto</label>
                            </div>

                            <form id="ps-form" autocomplete="off">
                                <div class="mb-2">
                                    <label class="form-label">CPF do titular</label>
                                    <input type="text" class="form-control" id="ps-cpf" placeholder="000.000.000-00" required>
                                </div>
                                <div class="row g-2 mb-2" id="ps-fone-row">
                                    <div class="col-4">
                                        <label class="form-label">DDD</label>
                                        <input type="text" class="form-control" id="ps-ddd" maxlength="2" placeholder="21">
                                    </div>
                                    <div class="col-8">
                                        <label class="form-label">Telefone</label>
                                        <input type="text" class="form-control" id="ps-telefone" placeholder="999999999">
                                    </div>
                                </div>

                                <div id="ps-campos-cartao">
                                    <hr class="config-hr">
                                    <div class="mb-2">
                                        <label class="form-label">Número do cartão</label>
                                        <input type="text" class="form-control" id="ps-cc-numero" placeholder="0000 0000 0000 0000">
                                    </div>
                                    <div class="row g-2 mb-2">
                                        <div class="col-4">
                                            <label class="form-label">Validade (MM/AAAA)</label>
                                            <input type="text" class="form-control" id="ps-cc-validade" placeholder="12/2030">
                                        </div>
                                        <div class="col-4">
                                            <label class="form-label">CVV</label>
                                            <input type="text" class="form-control" id="ps-cc-cvv" maxlength="4">
                                        </div>
                                        <div class="col-4">
                                            <label class="form-label">Parcelas</label>
                                            <select class="form-control" id="ps-cc-parcelas">
                                                <option value="1">1x</option>
                                                <option value="2">2x</option>
                                                <option value="3">3x</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label">Nome impresso no cartão</label>
                                        <input type="text" class="form-control" id="ps-cc-nome">
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label">Data de nascimento do titular</label>
                                        <input type="text" class="form-control" id="ps-cc-nascimento" placeholder="dd/mm/aaaa">
                                    </div>
                                    <hr class="config-hr">
                                    <p class="text-muted small mb-2">Endereço de cobrança (exigido pelo PagSeguro para cartão)</p>
                                    <div class="row g-2 mb-2">
                                        <div class="col-8">
                                            <label class="form-label">Rua</label>
                                            <input type="text" class="form-control" id="ps-end-rua">
                                        </div>
                                        <div class="col-4">
                                            <label class="form-label">Número</label>
                                            <input type="text" class="form-control" id="ps-end-numero">
                                        </div>
                                    </div>
                                    <div class="row g-2 mb-2">
                                        <div class="col-6">
                                            <label class="form-label">Bairro</label>
                                            <input type="text" class="form-control" id="ps-end-bairro">
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label">CEP</label>
                                            <input type="text" class="form-control" id="ps-end-cep">
                                        </div>
                                    </div>
                                    <div class="row g-2 mb-3">
                                        <div class="col-8">
                                            <label class="form-label">Cidade</label>
                                            <input type="text" class="form-control" id="ps-end-cidade">
                                        </div>
                                        <div class="col-4">
                                            <label class="form-label">UF</label>
                                            <input type="text" class="form-control" id="ps-end-uf" maxlength="2">
                                        </div>
                                    </div>
                                </div>

                                <button class="btn btn-success w-100" type="submit" id="ps-submit">
                                    <i class="fas fa-lock me-1"></i>Pagar com PagSeguro
                                </button>
                            </form>
                            <div id="ps-payment-status" class="mt-3"></div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>
<?php include __DIR__ . '/../layouts/footer.php'; ?>

<?php if ($mostrarMercadoPago): ?>
<script src="https://sdk.mercadopago.com/js/v2"<?php echo csp_script_nonce_attr(); ?>></script>
<?php endif; ?>
<?php if ($mostrarPagSeguro): ?>
<script src="<?php echo htmlspecialchars($psScriptBase); ?>/pagseguro/api/v2/checkout/pagseguro.directpayment.js"<?php echo csp_script_nonce_attr(); ?>></script>
<?php endif; ?>

<script<?php echo csp_script_nonce_attr(); ?>>
(function () {
    'use strict';

    var BP = <?php echo json_encode($bp); ?>;
    var PEDIDO_ID = <?php echo json_encode($pedidoId); ?>;
    var CSRF_TOKEN = <?php echo json_encode($csrfToken ?? ''); ?>;
    var VALOR_PEDIDO = <?php echo json_encode($valorPedido); ?>;
    var MP_PUBLIC_KEY = <?php echo json_encode($mpPublicKey); ?>;

    function mostrarMensagemGlobal(texto, tipo) {
        var el = document.getElementById('checkout-mensagem');
        if (!el) return;
        el.className = 'alert alert-' + (tipo || 'danger');
        el.textContent = texto;
        el.classList.remove('d-none');
        el.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function tratarRespostaPagamento(resp, statusElId) {
        // §QA-RESP-CAPTURA-01: guarda a última resposta em window pra QA
        // (Playwright) conseguir ler o resultado real sem depender de
        // reconstruir o corpo da resposta de rede via CDP depois do fetch —
        // isso corre risco de race contra o window.location.href logo
        // abaixo (no sucesso), que descarta o recurso de rede antes do
        // teste conseguir ler o body (visto nos logs: "Protocol error
        // (Network.getResponseBody): No resource with given identifier
        // found"). Não afeta usuários reais, só expõe um valor extra em
        // window.
        window.__qaUltimaRespostaPagamento = resp;
        var statusEl = document.getElementById(statusElId);
        if (!resp || !resp.sucesso) {
            var msg = (resp && resp.erro) ? resp.erro : 'Não foi possível processar o pagamento. Tente novamente.';
            mostrarMensagemGlobal(msg, 'danger');
            return;
        }

        if (resp.status === 'aprovado') {
            mostrarMensagemGlobal('Pagamento aprovado! Redirecionando...', 'success');
            window.location.href = resp.redirect || (BP + '/pagamento/sucesso/' + PEDIDO_ID);
            return;
        }

        if (resp.status === 'pendente') {
            var detalhe = resp.detalhe || {};
            if (statusEl && detalhe.qr_code_base64) {
                statusEl.innerHTML = '<div class="alert alert-info">Escaneie o QR Code do Pix para concluir:</div>'
                    + '<img alt="QR Code Pix" style="max-width:220px" src="data:image/png;base64,' + detalhe.qr_code_base64 + '">';
            } else if (statusEl && detalhe.boleto_url) {
                statusEl.innerHTML = '<div class="alert alert-info">Boleto gerado. <a target="_blank" rel="noopener" href="'
                    + detalhe.boleto_url + '">Abrir boleto</a></div>';
            } else if (statusEl) {
                statusEl.innerHTML = '<div class="alert alert-info">Pagamento em análise. Avisaremos assim que for confirmado.</div>';
            }
            return;
        }

        mostrarMensagemGlobal('O pagamento não foi aprovado. Verifique os dados e tente novamente.', 'danger');
    }

    // ─── MercadoPago Payment Brick ──────────────────────────────────────
    <?php if ($mostrarMercadoPago): ?>
    (function initMercadoPagoBrick() {
        if (!MP_PUBLIC_KEY || typeof MercadoPago === 'undefined') {
            mostrarMensagemGlobal('MercadoPago não pôde ser carregado. Tente atualizar a página.', 'warning');
            return;
        }

        var mp = new MercadoPago(MP_PUBLIC_KEY, { locale: 'pt-BR' });
        var bricksBuilder = mp.bricks();

        bricksBuilder.create('payment', 'mp-payment-brick-container', {
            initialization: {
                amount: VALOR_PEDIDO,
            },
            customization: {
                paymentMethods: {
                    creditCard: 'all',
                    debitCard: 'all',
                    bankTransfer: 'all', // Pix
                    ticket: 'all',       // boleto
                },
            },
            callbacks: {
                onReady: function () {},
                onError: function (error) {
                    console.error('[Brick][onError]', error);
                    mostrarMensagemGlobal('Não foi possível carregar o formulário de pagamento do MercadoPago.', 'danger');
                },
                // §PAY-BRICK-01: o SDK do MP chama onSubmit com um único
                // objeto { selectedPaymentMethod, formData } — NÃO com o
                // formData direto (confirmado na doc oficial do Payment
                // Brick, sdk-js/docs/bricks/payment.md). Tratar esse
                // parâmetro inteiro como se já fosse o formData manda
                // payment_method_id/token um nível mais fundo do que o
                // backend espera, e PagamentoController::mercadoPagoTransparente()
                // recusa com "Método de pagamento não identificado." —
                // exatamente o bug que apareceu no primeiro teste real.
                onSubmit: function (dadosSubmit) {
                    var formData = (dadosSubmit && dadosSubmit.formData) ? dadosSubmit.formData : dadosSubmit;
                    return fetch(BP + '/pagamento/mercadopago/pagar', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            csrf_token: CSRF_TOKEN,
                            pedido_id: PEDIDO_ID,
                            formData: formData,
                        }),
                    })
                        .then(function (r) { return r.json(); })
                        .then(function (resp) {
                            tratarRespostaPagamento(resp, 'mp-payment-status');
                            if (!resp.sucesso || resp.status !== 'aprovado') {
                                // Mantém a Promise resolvida (Brick espera isso), mas sem
                                // redirecionar — o Brick volta a ficar interativo pra nova tentativa.
                                return Promise.resolve();
                            }
                        })
                        .catch(function (err) {
                            console.error('[Brick][onSubmit]', err);
                            mostrarMensagemGlobal('Erro de comunicação ao processar o pagamento.', 'danger');
                            return Promise.reject(err);
                        });
                },
            },
        });
    })();
    <?php endif; ?>

    // ─── PagSeguro Checkout Transparente ────────────────────────────────
    <?php if ($mostrarPagSeguro): ?>
    (function initPagSeguroTransparente() {
        var sessionId = null;
        var senderHash = '';

        function metodoSelecionado() {
            var el = document.querySelector('input[name="ps-metodo"]:checked');
            return el ? el.value : 'creditCard';
        }

        function alternarCamposCartao() {
            var ehCartao = metodoSelecionado() === 'creditCard';
            document.getElementById('ps-campos-cartao').style.display = ehCartao ? '' : 'none';
        }
        document.querySelectorAll('input[name="ps-metodo"]').forEach(function (el) {
            el.addEventListener('change', alternarCamposCartao);
        });
        alternarCamposCartao();

        function carregarSessao() {
            return fetch(BP + '/pagamento/pagseguro/sessao/' + PEDIDO_ID, { method: 'GET' })
                .then(function (r) { return r.json(); })
                .then(function (resp) {
                    if (!resp.sucesso) {
                        throw new Error(resp.erro || 'Falha ao iniciar sessão PagSeguro.');
                    }
                    sessionId = resp.sessionId;
                    if (typeof PagSeguroDirectPayment !== 'undefined') {
                        PagSeguroDirectPayment.setSessionId(sessionId);
                    }
                });
        }

        if (typeof PagSeguroDirectPayment !== 'undefined') {
            carregarSessao().catch(function (err) {
                console.error('[PagSeguro][sessao]', err);
                mostrarMensagemGlobal('Não foi possível iniciar o checkout PagSeguro. Tente atualizar a página.', 'warning');
            });

            // §PS-HASH-01: senderHash NÃO deve disparar no carregamento da
            // página nem no submit — a doc do PagSeguro pede pra gerar assim
            // que o comprador começa a preencher os dados (aqui: quando sai
            // do campo de CPF), senão o hash pode vir vazio/atrasado.
            var cpfEl = document.getElementById('ps-cpf');
            if (cpfEl) {
                cpfEl.addEventListener('blur', function () {
                    if (typeof PagSeguroDirectPayment !== 'undefined' && PagSeguroDirectPayment.onSenderHashReady) {
                        PagSeguroDirectPayment.onSenderHashReady(function (response) {
                            if (response.status === 'error') {
                                console.error('[PagSeguro][senderHash]', response.message);
                                return;
                            }
                            senderHash = response.senderHash;
                        });
                    }
                });
            }
        }

        var form = document.getElementById('ps-form');
        if (form) {
            form.addEventListener('submit', function (ev) {
                ev.preventDefault();
                var submitBtn = document.getElementById('ps-submit');
                var metodo = metodoSelecionado();

                var payload = {
                    csrf_token: CSRF_TOKEN,
                    pedido_id: PEDIDO_ID,
                    metodo: metodo,
                    docNumero: (document.getElementById('ps-cpf').value || '').replace(/\D+/g, ''),
                    telefoneDdd: document.getElementById('ps-ddd').value || '',
                    telefoneNumero: document.getElementById('ps-telefone').value || '',
                    senderHash: senderHash,
                };

                function enviar() {
                    submitBtn.disabled = true;
                    fetch(BP + '/pagamento/pagseguro/pagar', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload),
                    })
                        .then(function (r) { return r.json(); })
                        .then(function (resp) {
                            tratarRespostaPagamento(resp, 'ps-payment-status');
                        })
                        .catch(function (err) {
                            console.error('[PagSeguro][pagar]', err);
                            mostrarMensagemGlobal('Erro de comunicação ao processar o pagamento.', 'danger');
                        })
                        .finally(function () {
                            submitBtn.disabled = false;
                        });
                }

                if (metodo !== 'creditCard') {
                    enviar();
                    return;
                }

                if (typeof PagSeguroDirectPayment === 'undefined') {
                    mostrarMensagemGlobal('PagSeguro não carregou corretamente. Atualize a página.', 'warning');
                    return;
                }

                var numero = (document.getElementById('ps-cc-numero').value || '').replace(/\s+/g, '');
                var validade = (document.getElementById('ps-cc-validade').value || '').split('/');
                var mes = (validade[0] || '').trim();
                var ano = (validade[1] || '').trim();
                var cvv = document.getElementById('ps-cc-cvv').value || '';
                var brand = null;

                PagSeguroDirectPayment.getBrand({
                    cardBin: numero.substring(0, 6),
                    success: function (resp) {
                        brand = resp.brand && resp.brand.name;
                        PagSeguroDirectPayment.createCardToken({
                            cardNumber: numero,
                            brand: brand,
                            cvv: cvv,
                            expirationMonth: mes,
                            expirationYear: ano,
                            success: function (tokenResp) {
                                payload.token = tokenResp.card.token;
                                payload.parcelas = parseInt(document.getElementById('ps-cc-parcelas').value || '1', 10);
                                payload.nascimento = document.getElementById('ps-cc-nascimento').value || '';
                                payload.enderecoRua = document.getElementById('ps-end-rua').value || '';
                                payload.enderecoNumero = document.getElementById('ps-end-numero').value || '';
                                payload.enderecoBairro = document.getElementById('ps-end-bairro').value || '';
                                payload.enderecoCep = document.getElementById('ps-end-cep').value || '';
                                payload.enderecoCidade = document.getElementById('ps-end-cidade').value || '';
                                payload.enderecoUf = document.getElementById('ps-end-uf').value || '';
                                enviar();
                            },
                            error: function (err) {
                                console.error('[PagSeguro][createCardToken]', err);
                                mostrarMensagemGlobal('Não foi possível validar os dados do cartão. Confira e tente novamente.', 'danger');
                            },
                        });
                    },
                    error: function (err) {
                        console.error('[PagSeguro][getBrand]', err);
                        mostrarMensagemGlobal('Não foi possível identificar a bandeira do cartão.', 'danger');
                    },
                });
            });
        }
    })();
    <?php endif; ?>
})();
</script>

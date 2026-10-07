/**
 * public-pre-cotacao-flow.js
 *
 * Maquina de estados do funil de pre-cotacao v2.
 *
 * Ordem dos estagios (segue a UX pedida):
 *   endereco -> modo -> veiculo -> opcoes -> destino -> cotacao
 *                 (fallback WhatsApp se sem cobertura)
 *
 * Depende de:
 *   - window.gfAddressPicker (components/address-picker.js)
 *   - containers <div id="stage-XXX"> no HTML
 *   - atributos [data-mode], [data-servico], [data-categoria],
 *     [data-action="voltar"], [data-action="aceitar-reboque"]
 *
 * Emite:
 *   gf:flow-stage  { stage, prev }   a cada transicao
 */
(function () {
    'use strict';

    var DEBUG = true;
    var API = (window.__gfBasePath || '').replace(/\/$/, '') + '/api/pre-cotacao';

    var OPTS = window.__gfFlowOptions || {};
    var VEHICLE_CATEGORIES = ['popular', 'moto', 'suv', 'caminhonete', 'eletrico'];

    function log() {
        if (!DEBUG) return;
        var args = Array.prototype.slice.call(arguments);
        console.log.apply(console, ['[flow]'].concat(args));
    }

    var STAGES = ['endereco', 'modo', 'veiculo', 'sintoma', 'opcoes', 'destino', 'cotacao'];

    var STATE = {
        role: 'cliente',
        endereco: null,
        cobertura: null,
        modo: null,
        servico: null,
        veiculo: null,
        destino: null,
        opcoes: null,
        whatsappLink: null,
        reboqueBloqueado: false,
    };

    var currentStage = null;

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------
    function $(id) { return document.getElementById(id); }
    function $$(sel, root) { return (root || document).querySelectorAll(sel); }

    function showStage(name) {
        if (STAGES.indexOf(name) === -1) return;

        var prev = currentStage;

        STAGES.forEach(function (s) {
            var el = $('stage-' + s);

            if (!el) return;

            if (s === name) {
                el.hidden = false;
                el.classList.add('is-active');
            } else {
                el.hidden = true;
                el.classList.remove('is-active');
            }
        });

        Array.prototype.forEach.call(document.body.classList, function (c) {
            if (c.indexOf('stage-') === 0) {
                document.body.classList.remove(c);
            }
        });

        document.body.classList.add('stage-' + name);

        currentStage = name;

        log('stage ->', name, '(prev:', prev + ')');

        try {
            document.dispatchEvent(
                new CustomEvent('gf:flow-stage', {
                    detail: {
                        stage: name,
                        prev: prev
                    }
                })
            );
        } catch (e) {}
    }

    function apiGet(path, params) {
        var qs = params
            ? '?' + Object.keys(params).map(function (k) {
                return encodeURIComponent(k) + '=' + encodeURIComponent(params[k]);
            }).join('&')
            : '';

        return fetch(API + path + qs, {
            headers: {
                'Accept': 'application/json'
            }
        }).then(function (r) {
            return r.json();
        });
    }

    function apiPost(path, body) {
        var csrf =
            (document.querySelector('input[name="csrf_token"]') || {}).value || '';

        var params = new URLSearchParams();

        Object.keys(body || {}).forEach(function (k) {
            params.append(k, body[k]);
        });

        params.append('csrf_token', csrf);

        return fetch(API + path, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: params.toString(),
        }).then(function (r) {
            return r.json();
        });
    }

    function escapeHtml(s) {
        return String(s || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function money(n) {
        var v = Number(n) || 0;
        return 'R$ ' + v.toFixed(2).replace('.', ',');
    }

    function setStatus(id, msg, isErr) {
        var el = $(id);

        if (!el) return;

        el.textContent = msg || '';
        el.style.color = isErr ? '#b02a37' : '';
    }

    // ----------------------------------------------------------------
    // Bloqueio de submit quando nao existe guincho disponivel
    // ----------------------------------------------------------------

    function setSubmitBlocked(blocked) {
        STATE.reboqueBloqueado = !!blocked;

        var form =
            document.querySelector('form[data-precotacao-form]') ||
            document.querySelector('form');

        if (!form) return;

        var submits = form.querySelectorAll(
            'button[type="submit"], input[type="submit"]'
        );

        Array.prototype.forEach.call(submits, function (btn) {
            btn.disabled = !!blocked;

            btn.setAttribute(
                'aria-disabled',
                blocked ? 'true' : 'false'
            );
        });
    }

    function mostrarReboqueIndisponivel() {
        var stage =
            $('stage-destino') ||
            $('stage-opcoes');

        if (!stage) return;

        var alert = $('reboque-indisponivel-alert');

        if (!alert) {
            alert = document.createElement('div');

            alert.id = 'reboque-indisponivel-alert';

            alert.className =
                'alert alert-warning mt-3';

            alert.setAttribute(
                'role',
                'alert'
            );

            stage.appendChild(alert);
        }

        alert.textContent =
            'Nenhum guincho disponivel na sua regiao agora';

        setSubmitBlocked(true);
    }

    function limparReboqueIndisponivel() {
        var alert =
            $('reboque-indisponivel-alert');

        if (alert && alert.parentNode) {
            alert.parentNode.removeChild(alert);
        }

        setSubmitBlocked(false);
    }

    // ----------------------------------------------------------------
    // Endereco (origem)
    // ----------------------------------------------------------------

    document.addEventListener(
        'gf:address-confirmed',
        function (ev) {
            var d = ev.detail || {};

            if (d.role !== 'origem') return;

            log('origem confirmada', d);

            STATE.endereco = d;

            showStage('modo');
        }
    );

    // ----------------------------------------------------------------
    // Modo
    // ----------------------------------------------------------------

    document.addEventListener(
        'click',
        function (ev) {
            var btn =
                ev.target.closest &&
                ev.target.closest('[data-mode]');

            if (!btn) return;

            ev.preventDefault();

            var modo =
                btn.getAttribute('data-mode');

            STATE.modo = modo;

            log('modo ->', modo);

            var categoriaInformada =
                String(
                    OPTS.veiculoCategoria || ''
                ).toLowerCase();

            if (
                OPTS.skipVeiculo === true &&
                VEHICLE_CATEGORIES.indexOf(
                    categoriaInformada
                ) !== -1
            ) {
                STATE.veiculo =
                    categoriaInformada;

                $$('[data-categoria]').forEach(
                    function (b) {
                        b.classList.toggle(
                            'is-selected',
                            b.getAttribute('data-categoria') ===
                                categoriaInformada
                        );
                    }
                );

                log(
                    'vehicle preset ->',
                    categoriaInformada
                );

                showStage('sintoma');

                carregarTriagemServico();

                return;
            }

            showStage('veiculo');
        }
    );

    // ----------------------------------------------------------------
    // Veiculo
    // ----------------------------------------------------------------

    document.addEventListener(
        'click',
        function (ev) {
            var btn =
                ev.target.closest &&
                ev.target.closest(
                    '[data-categoria]'
                );

            if (!btn) return;

            ev.preventDefault();

            var cat =
                btn.getAttribute(
                    'data-categoria'
                );

            STATE.veiculo = cat;

            $$('[data-categoria]').forEach(
                function (b) {
                    b.classList.toggle(
                        'is-selected',
                        b === btn
                    );
                }
            );

            log('veiculo ->', cat);

            if (
                STATE.modo ===
                'levar_carro'
            ) {
                showStage('destino');

                inicializarDestino();
            } else {
                showStage('sintoma');

                carregarTriagemServico();
            }
        }
    );

    // ----------------------------------------------------------------
    // Triagem de servico
    // ----------------------------------------------------------------

    function carregarTriagemServico() {
        var container =
            $('triagem-servicos');

        if (!container) {
            log(
                'sem #triagem-servicos — pulando'
            );

            return;
        }

        container.innerHTML =
            '<p class="text-muted small">' +
            'Carregando servicos...' +
            '</p>';

        apiGet(
            '/triagem-servicos'
        ).then(function (j) {

            var servicos =
                (
                    j &&
                    j.data &&
                    j.data.servicos
                ) || [];

            if (!servicos.length) {
                container.innerHTML =
                    '<p class="text-muted small">' +
                    'Nenhum servico disponivel.' +
                    '</p>';

                return;
            }

            container.innerHTML =
                servicos.map(function (s) {

                    return (
                        '<button type="button" ' +
                        'class="choice-card" ' +
                        'data-servico="' +
                        escapeHtml(s.slug) +
                        '">' +

                        '<i class="fas ' +
                        escapeHtml(
                            s.icone ||
                            'fa-wrench'
                        ) +
                        '"></i>' +

                        '<strong>' +
                        escapeHtml(s.nome) +
                        '</strong>' +

                        '<small>' +
                        escapeHtml(
                            s.descricao || ''
                        ) +
                        '</small>' +

                        '</button>'
                    );

                }).join('');

        }).catch(function (e) {

            log(
                'erro triagem',
                e
            );

            container.innerHTML =
                '<p class="text-danger small">' +
                'Falha ao carregar servicos.' +
                '</p>';
        });
    }

    document.addEventListener(
        'click',
        function (ev) {

            var btn =
                ev.target.closest &&
                ev.target.closest(
                    '[data-servico]'
                );

            if (!btn) return;

            ev.preventDefault();

            var slug =
                btn.getAttribute(
                    'data-servico'
                );

            STATE.servico = slug;

            log(
                'servico ->',
                slug
            );

            carregarOpcoes(slug);
        }
    );

    // ----------------------------------------------------------------
    // Opcoes
    // ----------------------------------------------------------------

    function carregarOpcoes(slug) {
        var wrap =
            $('opcoes-wrap');

        if (!wrap) {
            log('sem #opcoes-wrap');
            return;
        }

        wrap.innerHTML =
            '<p class="text-muted small">' +
            'Calculando opcoes...' +
            '</p>';

        showStage('opcoes');

        var modo =
            STATE.modo === 'local'
                ? 'local'
                : 'orientacao';

        apiGet(
            '/opcoes',
            {
                modo: modo,
                servico: slug || '',
                lat: STATE.endereco.lat,
                lng: STATE.endereco.lng,
            }
        ).then(function (j) {

            if (!j || !j.ok) {
                throw new Error(
                    (j && j.error) ||
                    'Falha'
                );
            }

            var d =
                j.data || {};

            STATE.opcoes = d;

            log(
                'opcoes ->',
                d
            );

            if (!d.disponivel) {

                if (
                    d.fallback_tipo ===
                    'guincho'
                ) {
                    wrap.innerHTML =
                        '<div class="alert alert-warning">' +
                        'Sem assistencia para ' +
                        escapeHtml(
                            slug ||
                            'esse servico'
                        ) +
                        ' na sua regiao agora. ' +
                        'Mas temos guincho disponivel.' +
                        '</div>' +

                        '<button type="button" ' +
                        'class="btn-main w-100" ' +
                        'data-action="aceitar-reboque">' +
                        'Quero rebocar' +
                        '</button>';

                    return;
                }

                renderWhatsApp(
                    wrap,
                    d.mensagem
                );

                return;
            }

            renderOpcoes(
                wrap,
                d,
                slug
            );

        }).catch(function (e) {

            log(
                'erro opcoes',
                e
            );

            wrap.innerHTML =
                '<p class="text-danger small">' +
                'Falha ao calcular opcoes.' +
                '</p>';
        });
    }

    function renderOpcoes(
        wrap,
        d,
        slug
    ) {
        var html =
            '<div class="decision-grid">';

        // ------------------------------------------------------------
        // Card A — Resolver no local
        // ------------------------------------------------------------

        if (
            d.valor_deslocamento != null
        ) {
            var valorAssist =
                Number(
                    d.valor_deslocamento
                ) || 0;

            var taxaReboque =
                Number(
                    d.taxa_base_reboque
                ) || 0;

            var economia =
                Number(
                    d.economia_estimada
                ) ||
                Math.max(
                    0,
                    taxaReboque -
                    valorAssist
                );

            var reboqueComparacao =
                taxaReboque > 0
                    ? money(taxaReboque)
                    : 'R$ 150,00';

            html +=
                '<button type="button" ' +
                'class="decision-card is-recommended" ' +
                'data-action="aceitar-local">' +

                '<div class="card-icon">' +
                '<i class="fas fa-user-cog"></i>' +
                '</div>' +

                '<h3>Resolver no local</h3>' +

                '<span class="card-price">' +
                money(valorAssist) +
                '</span>' +

                '<span class="card-prazo">' +
                'Mecanico chega em ~15-20 min' +
                '</span>' +

                '<ul>' +

                '<li>' +
                '<i class="fas fa-truck-pickup"></i>' +
                '<span>' +
                'So a <strong>saida do guincho</strong> ' +
                'ate voce custa <strong>' +
                reboqueComparacao +
                '</strong>. Voce economiza <strong>' +
                money(economia) +
                '</strong>.' +
                '</span>' +
                '</li>' +

                '<li>' +
                '<i class="fas fa-screwdriver-wrench"></i>' +
                '<span>' +
                'Se o mecanico <strong>nao conseguir resolver no local</strong>, ' +
                'ele mesmo reboca seu carro ate a oficina dele e ' +
                '<strong>abate o valor do socorro do conserto final</strong>.' +
                '</span>' +
                '</li>' +

                '<li>' +
                '<i class="fas fa-handshake"></i>' +
                '<span>' +
                'Voce <strong>nao e obrigado</strong> a aceitar o orcamento. ' +
                'Pode pedir o <strong>guincho depois com desconto</strong>.' +
                '</span>' +
                '</li>' +

                '<li>' +
                '<i class="fas fa-shield-halved"></i>' +
                '<span>' +
                '<strong>Risco zero</strong>: so paga a saida. ' +
                'Nada mais e cobrado sem sua aprovacao.' +
                '</span>' +
                '</li>' +

                '</ul>' +

                '<span class="btn-main">' +
                'Quero resolver no local' +
                '</span>' +

                '</button>';
        }

        // ------------------------------------------------------------
        // Card B — Reboque
        // ------------------------------------------------------------

        html +=
            '<button type="button" ' +
            'class="decision-card" ' +
            'data-action="aceitar-reboque">' +

            '<div class="card-icon">' +

            '<svg viewBox="0 0 24 24" ' +
            'width="48" height="48" ' +
            'aria-hidden="true">' +

            '<path fill="#2fb34a" ' +
            'd="M20 8h-3V4H3c-1.1 0-2 .9-2 2v11h2c0 1.66 1.34 3 3 3s3-1.34 3-3h6c0 1.66 1.34 3 3 3s3-1.34 3-3h2v-5l-3-4zM6 18.5c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zm13.5-9l1.96 2.5H17V9.5h2.5zm-1.5 9c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5z"/>' +

            '</svg>' +

            '</div>' +

            '<h3>Rebocar</h3>' +

            '<ul>' +

            '<li>' +
            '<i class="fas fa-bolt"></i>' +
            '<span>' +
            'Um <strong>guincho plataforma</strong> remove seu veiculo ' +
            '<strong>imediatamente</strong>.' +
            '</span>' +
            '</li>' +

            '<li>' +
            '<i class="fas fa-route"></i>' +
            '<span>' +
            'Voce escolhe o <strong>destino final</strong> — oficina, casa ou outro endereco.' +
            '</span>' +
            '</li>' +

            '<li>' +
            '<i class="fas fa-lock"></i>' +
            '<span>' +
            'O valor exato e calculado pelo <strong>trajeto completo</strong>, sem surpresas.' +
            '</span>' +
            '</li>' +

            '</ul>' +

            '<span class="btn-main">' +
            'Pedir guincho agora' +
            '</span>' +

            '</button>';

        if (
            d.economia_estimada != null &&
            d.economia_estimada > 0
        ) {
            html +=
                '<div class="economia-box">' +
                '<i class="fas fa-piggy-bank me-1"></i> ' +
                'Escolhendo <strong>resolver no local</strong> ' +
                'voce economiza <strong>' +
                money(
                    d.economia_estimada
                ) +
                '</strong>.' +
                '</div>';
        }

        html +=
            '</div>';

        html +=
            '<div class="mt-3 d-flex gap-2">' +
            '<button type="button" ' +
            'class="btn btn-outline-secondary" ' +
            'data-action="voltar">' +
            'Voltar' +
            '</button>' +
            '</div>';

        wrap.innerHTML =
            html;
    }

    // ----------------------------------------------------------------
    // Aceitar opcao
    // ----------------------------------------------------------------

    document.addEventListener(
        'click',
        function (ev) {

            var btn =
                ev.target.closest &&
                ev.target.closest(
                    '[data-action]'
                );

            if (!btn) return;

            var action =
                btn.getAttribute(
                    'data-action'
                );

            if (
                action ===
                'aceitar-local'
            ) {
                ev.preventDefault();

                log(
                    'aceitou local'
                );

                STATE.valorCotado =
                    (
                        STATE.opcoes &&
                        STATE.opcoes.valor_deslocamento
                    ) || null;

                preencherHiddenFields(
                    'assistencia'
                );

                showStage(
                    'cotacao'
                );

            } else if (
                action ===
                'aceitar-reboque'
            ) {
                ev.preventDefault();

                log(
                    'aceitou reboque'
                );

                showStage(
                    'destino'
                );

                inicializarDestino();

            } else if (
                action ===
                'voltar'
            ) {
                ev.preventDefault();

                if (
                    currentStage ===
                    'opcoes'
                ) {
                    showStage(
                        'veiculo'
                    );

                } else if (
                    currentStage ===
                    'destino'
                ) {
                    showStage(
                        'opcoes'
                    );

                } else if (
                    currentStage ===
                    'veiculo'
                ) {
                    showStage(
                        'modo'
                    );
                }
            }
        }
    );

    // ----------------------------------------------------------------
    // Destino
    // ----------------------------------------------------------------

    function inicializarDestino() {
        if (!STATE.endereco) return;

        var wrap =
            document.querySelector(
                '.gf-ap[data-role="destino"]'
            );

        if (!wrap) return;

        var mapEl =
            wrap.querySelector(
                '[data-ap-map]'
            );

        if (
            mapEl &&
            !mapEl.__gfMapBound
        ) {
            wrap.setAttribute(
                'data-ap-init-lat',
                STATE.endereco.lat
            );

            wrap.setAttribute(
                'data-ap-init-lng',
                STATE.endereco.lng
            );

            if (
                window.gfAddressPicker &&
                window.gfAddressPicker.boot
            ) {
                wrap.removeAttribute(
                    '__gfApBooted'
                );

                window.gfAddressPicker.boot();
            }
        }
    }

    document.addEventListener(
        'gf:address-confirmed',
        function (ev) {

            var d =
                ev.detail || {};

            if (
                d.role !==
                'destino'
            ) {
                return;
            }

            log(
                'destino confirmado',
                d
            );

            STATE.destino = d;

            // Nova tentativa de destino:
            // remove o bloqueio anterior antes de consultar novamente.
            limparReboqueIndisponivel();

            function carregarCotacaoReboque() {

                apiGet(
                    '/opcoes',
                    {
                        modo: 'reboque',

                        lat:
                            STATE.endereco.lat,

                        lng:
                            STATE.endereco.lng,

                        lat_destino:
                            d.lat,

                        lng_destino:
                            d.lng,
                    }
                ).then(function (j) {

                    var dados =
                        j && j.data
                            ? j.data
                            : null;

                    /*
                     * REGRA DE NEGOCIO 2026-10-06
                     *
                     * Se nao ha guincho capaz de atender:
                     *
                     * - nao avanca para cotacao
                     * - nao cria pedido
                     * - permanece no destino
                     * - bloqueia submit
                     */
                    if (
                        j &&
                        j.ok &&
                        dados &&
                        dados.disponivel === false &&
                        dados.fallback_tipo === 'suporte'
                    ) {
                        log(
                            'reboque indisponivel -> bloqueando fluxo',
                            dados
                        );

                        STATE.valorCotado =
                            null;

                        mostrarReboqueIndisponivel();

                        showStage(
                            'destino'
                        );

                        return;
                    }

                    /*
                     * Somente uma cotacao efetivamente disponivel
                     * pode liberar o estagio final.
                     */
                    if (
                        j &&
                        j.ok &&
                        dados &&
                        dados.disponivel &&
                        dados.valor_reboque != null
                    ) {
                        limparReboqueIndisponivel();

                        STATE.valorCotado =
                            dados.valor_reboque;

                        preencherHiddenFields(
                            'reboque'
                        );

                        showStage(
                            'cotacao'
                        );

                        return;
                    }

                    /*
                     * Fail closed:
                     *
                     * qualquer resposta sem cotacao valida
                     * permanece no destino e impede submit.
                     */
                    STATE.valorCotado =
                        null;

                    mostrarReboqueIndisponivel();

                    showStage(
                        'destino'
                    );

                }).catch(function (e) {

                    log(
                        'erro cotacao reboque',
                        e
                    );

                    STATE.valorCotado =
                        null;

                    /*
                     * Erro de API tambem nao deve permitir
                     * criacao cega de pedido.
                     */
                    mostrarReboqueIndisponivel();

                    showStage(
                        'destino'
                    );
                });
            }

            if (
                OPTS.consultarReboques !== true
            ) {
                carregarCotacaoReboque();

                return;
            }

            apiGet(
                '/reboques-proximos',
                {
                    lat_origem:
                        STATE.endereco.lat,

                    lng_origem:
                        STATE.endereco.lng,

                    lat_destino:
                        d.lat,

                    lng_destino:
                        d.lng,

                    categoria:
                        STATE.veiculo ||
                        'popular'
                }
            ).then(function (j) {

                STATE.reboquesDisponiveis =
                    (
                        j &&
                        j.ok &&
                        j.data &&
                        j.data.guinchos
                    ) || [];

            }).catch(function (e) {

                log(
                    'erro consulta reboques proximos',
                    e
                );

                STATE.reboquesDisponiveis =
                    [];

            }).then(function () {

                document.dispatchEvent(
                    new CustomEvent(
                        'gf:reboques-loaded',
                        {
                            detail: {
                                guinchos:
                                    STATE.reboquesDisponiveis
                            }
                        }
                    )
                );

                carregarCotacaoReboque();
            });
        }
    );

    // ----------------------------------------------------------------
    // Hard guard
    //
    // Mesmo que algum outro JS tente submeter o formulario,
    // quando nao ha guincho o POST e interrompido antes de sair.
    // ----------------------------------------------------------------

    document.addEventListener(
        'submit',
        function (ev) {

            var form =
                ev.target;

            if (
                !form ||
                !form.matches ||
                !form.matches(
                    'form[data-precotacao-form], form'
                )
            ) {
                return;
            }

            if (
                !STATE.reboqueBloqueado
            ) {
                return;
            }

            ev.preventDefault();

            ev.stopImmediatePropagation();

            mostrarReboqueIndisponivel();

            showStage(
                'destino'
            );

            log(
                'submit bloqueado: nenhum guincho disponivel'
            );
        },
        true
    );

    // ----------------------------------------------------------------
    // Submissao final
    // ----------------------------------------------------------------

    function preencherHiddenFields(
        decisao
    ) {
        var form =
            document.querySelector(
                'form[data-precotacao-form]'
            ) ||
            document.querySelector(
                'form'
            );

        if (!form) {
            log('sem form');
            return;
        }

        var set =
            function (name, val) {

                var el =
                    form.querySelector(
                        '[name="' +
                        name +
                        '"]'
                    );

                if (!el) {
                    el =
                        document.createElement(
                            'input'
                        );

                    el.type =
                        'hidden';

                    el.name =
                        name;

                    form.appendChild(
                        el
                    );
                }

                el.value =
                    val == null
                        ? ''
                        : val;
            };

        set(
            'tipo_problema',
            STATE.servico ||
            (
                decisao ===
                'reboque'
                    ? 'reboque'
                    : 'me_orientem'
            )
        );

        set(
            'decisao_atendimento',
            decisao
        );

        set(
            'categoria',
            STATE.veiculo ||
            'popular'
        );

        set(
            'lat_origem',
            STATE.endereco &&
            STATE.endereco.lat
        );

        set(
            'lng_origem',
            STATE.endereco &&
            STATE.endereco.lng
        );

        set(
            'localizacao',
            STATE.endereco &&
            STATE.endereco.label
        );

        set(
            'numero_origem',
            STATE.endereco &&
            STATE.endereco.numero
        );

        set(
            'valor_cotado',
            STATE.valorCotado ||
            ''
        );

        if (
            STATE.destino
        ) {
            set(
                'lat_destino',
                STATE.destino.lat
            );

            set(
                'lng_destino',
                STATE.destino.lng
            );

            set(
                'destino',
                STATE.destino.label
            );

            set(
                'numero_destino',
                STATE.destino.numero
            );
        }
    }

    // ----------------------------------------------------------------
    // WhatsApp fallback
    // ----------------------------------------------------------------

    function renderWhatsApp(
        wrap,
        mensagemCustom
    ) {
        apiPost(
            '/whatsapp',
            {
                modo:
                    STATE.modo ||
                    '',

                servico:
                    STATE.servico ||
                    '',

                veiculo:
                    STATE.veiculo ||
                    '',

                origem_texto:
                    (
                        STATE.endereco &&
                        STATE.endereco.label
                    ) || '',

                origem_lat:
                    STATE.endereco
                        ? STATE.endereco.lat
                        : '',

                origem_lng:
                    STATE.endereco
                        ? STATE.endereco.lng
                        : '',

                destino_texto:
                    (
                        STATE.destino &&
                        STATE.destino.label
                    ) || '',
            }
        ).then(function (j) {

            var link =
                j &&
                j.ok &&
                j.data &&
                j.data.link;

            if (!link) {
                wrap.innerHTML =
                    '<div class="alert alert-warning">' +
                    'Fale com a central no WhatsApp.' +
                    '</div>';

                return;
            }

            STATE.whatsappLink =
                link;

            wrap.innerHTML =
                '<div class="alert alert-info">' +
                (
                    mensagemCustom ||
                    'Nenhuma oficina ou guincho disponivel agora. Vamos te atender pelo WhatsApp.'
                ) +
                '</div>' +

                '<a class="btn-main w-100 d-block text-center" ' +
                'href="' +
                escapeHtml(link) +
                '" ' +
                'target="_blank" ' +
                'rel="noopener">' +
                'Abrir WhatsApp' +
                '</a>';

        }).catch(function () {

            wrap.innerHTML =
                '<div class="alert alert-warning">' +
                'Nao conseguimos gerar o link agora.' +
                '</div>';
        });
    }

    // ----------------------------------------------------------------
    // Cobertura
    // ----------------------------------------------------------------

    document.addEventListener(
        'gf:address-confirmed',
        function (ev) {

            var d =
                ev.detail || {};

            if (
                d.role !==
                'origem'
            ) {
                return;
            }

            apiGet(
                '/cobertura',
                {
                    lat: d.lat,
                    lng: d.lng
                }
            ).then(function (j) {

                if (
                    j &&
                    j.ok
                ) {
                    STATE.cobertura =
                        j.data;

                    log(
                        'cobertura ->',
                        j.data
                    );

                    if (
                        !j.data.tem_guincho &&
                        !j.data.qtd_oficinas
                    ) {
                        var wrap =
                            $('opcoes-wrap');

                        if (wrap) {
                            showStage(
                                'opcoes'
                            );

                            renderWhatsApp(
                                wrap
                            );
                        }
                    }
                }

            }).catch(function () {});
        }
    );

    // ----------------------------------------------------------------
    // Boot
    // ----------------------------------------------------------------

    function boot() {
        log(
            'flow v2 pronto. Aguardando gf:address-confirmed.'
        );

        showStage(
            'endereco'
        );
    }

    if (
        document.readyState ===
        'loading'
    ) {
        document.addEventListener(
            'DOMContentLoaded',
            boot
        );
    } else {
        boot();
    }

    window.__gfFlow = {
        STATE: STATE,
        showStage: showStage,
        API: API
    };

})();
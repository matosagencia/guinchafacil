/* public/assets/js/pre-cotacao-flow.js
   §23 · Wizard + autocomplete + debug + unwrap({ok,data}).
   v20261016-6 — fix B-011-B2 §1: tipo_problema=reboque no modo levar_carro/reboque. */

(function () {
    'use strict';

    var form = document.getElementById('gfPreqForm');
    if (!form) return;

    // ─── Debug ────────────────────────────────────────────────
    var DEBUG = /[?&]debug=1\b/.test(location.search) || localStorage.getItem('gf_debug') === '1';
    var dbgEl = null;
    function dbg(kind, msg, data) {
        if (!DEBUG) return;
        if (!dbgEl) {
            dbgEl = document.createElement('div');
            dbgEl.className = 'gf-dbg';
            dbgEl.innerHTML =
                '<div class="gf-dbg__hd"><span>flow debug</span>' +
                '<button id="gfDbgOff">fechar</button></div>' +
                '<div class="gf-dbg__body" id="gfDbgBody"></div>';
            document.body.appendChild(dbgEl);
            document.getElementById('gfDbgOff').onclick = function () {
                localStorage.setItem('gf_debug', '0');
                dbgEl.remove();
                dbgEl = null;
            };
        }
        var body = document.getElementById('gfDbgBody');
        var row = document.createElement('div');
        row.className = 'gf-dbg__row ' + (kind || '');
        var txt = '<span class="gf-dbg__k">[' + new Date().toTimeString().slice(0, 8) + ']</span> ' + msg;
        if (data !== undefined) txt += '\n' + (typeof data === 'string' ? data : JSON.stringify(data, null, 2));
        row.textContent = txt;
        body.appendChild(row);
        body.scrollTop = body.scrollHeight;
        console.log('[flow][' + kind + ']', msg, data !== undefined ? data : '');
    }

    // ─── Desembrulha {ok, data} e outros formatos ─────────────
    function unwrap(d) {
        if (d && typeof d === 'object' && !Array.isArray(d) && 'data' in d && ('ok' in d || 'success' in d)) {
            return d.data;
        }
        return d;
    }

    // ─── State ────────────────────────────────────────────────
    var state = {
        step: 1,
        hasOficina: null,
        hasGuincho: null,
        nOficinas: 0,
        modo: '',
        sintoma: '',
        categoria: '',
        cobertura: null,
        comparativo: null,
        escolha: null,
    };

    var els = {
        lat:   document.getElementById('lat_origem'),
        lng:   document.getElementById('lng_origem'),
        latD:  document.getElementById('lat_destino'),
        lngD:  document.getElementById('lng_destino'),
        num:   document.getElementById('numero_origem'),
        numD:  document.getElementById('numero_destino'),
        loc:   document.getElementById('localizacao'),
        dest:  document.getElementById('destino'),
        tipo:  document.getElementById('tipo_problema'),
        dec:   document.getElementById('decisao_atendimento'),
        cat:   document.getElementById('categoria'),
        valor: document.getElementById('valor_cotado'),
        stid:  document.getElementById('service_type_id'),
    };

    dbg('ok', 'flow boot · v20261016-6', { debug: DEBUG });

    // ─── Navegação ────────────────────────────────────────────
    function showStep(n) {
        form.querySelectorAll('.gf-step').forEach(function (fs) {
            fs.classList.toggle('is-active', fs.getAttribute('data-step') === String(n));
        });
        document.querySelectorAll('[data-step-indicator]').forEach(function (li) {
            var s = parseInt(li.getAttribute('data-step-indicator'), 10);
            li.classList.toggle('is-current', s === n);
            li.classList.toggle('is-done', s < n);
        });
        state.step = n;
        dbg('info', 'showStep ' + n, { modo: state.modo, sintoma: state.sintoma, categoria: state.categoria });
        var top = form.getBoundingClientRect().top + window.pageYOffset - 40;
        window.scrollTo({ top: Math.max(0, top), behavior: 'smooth' });
    }

    form.querySelectorAll('[data-next]').forEach(function (b) {
        b.addEventListener('click', function () {
            var next = parseInt(b.getAttribute('data-next'), 10);
            if (state.step === 1 && !validateStep1()) return;
            if (state.step === 5 && !validateStep5()) return;
            if (next === 6) computeComparison();
            showStep(next);
        });
    });

    form.querySelectorAll('[data-back]').forEach(function (b) {
        b.addEventListener('click', function () {
            showStep(parseInt(b.getAttribute('data-back'), 10));
        });
    });

    // ─── Autocomplete ─────────────────────────────────────────
    function attachAutocomplete(input, onPick) {
        if (!input) return;
        var list = document.createElement('div');
        list.className = 'gf-ac';
        list.hidden = true;
        input.parentNode.appendChild(list);

        var timer = null;
        var items = [];
        var activeIdx = -1;

        function close() { list.hidden = true; list.innerHTML = ''; activeIdx = -1; items = []; }

        function render(arr) {
            items = arr;
            if (!arr.length) { close(); return; }
            list.innerHTML = arr.map(function (r, i) {
                var principal = (r.principal || r.display_name || r.label || r.endereco || '').replace(/[<>&]/g, '');
                var secundario = (r.secundario || r.display_name || '').replace(/[<>&]/g, '');
                if (principal === secundario) secundario = '';
                return '<button type="button" class="gf-ac__item" data-i="' + i + '">' +
                    '<strong>' + principal + '</strong>' +
                    (secundario ? '<small>' + secundario + '</small>' : '') + '</button>';
            }).join('');
            list.hidden = false;
            list.querySelectorAll('.gf-ac__item').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    pick(arr[parseInt(btn.getAttribute('data-i'), 10)]);
                });
            });
        }

        function pick(r) {
            input.value = r.principal
                ? (r.principal + (r.secundario ? ', ' + r.secundario : ''))
                : (r.display_name || r.label || r.endereco || input.value);
            dbg('ok', 'autocomplete pick', { input: input.id, lat: r.lat, lng: r.lng, endereco: input.value });
            close();
            onPick && onPick(r);
        }

        input.addEventListener('input', function () {
            clearTimeout(timer);
            var q = (input.value || '').trim();
            if (q.length < 3) { close(); return; }
            timer = setTimeout(function () {
                dbg('info', 'autocomplete q="' + q + '"');
                fetch('/api/pre-cotacao/endereco/sugestoes?q=' + encodeURIComponent(q), { credentials: 'same-origin' })
                    .then(function (r) { return r.ok ? r.json() : null; })
                    .then(function (raw) {
                        var data = unwrap(raw);
                        var arr = (data && (data.items || data.results || data.sugestoes)) || (Array.isArray(data) ? data : []);
                        dbg('ok', 'sugestões n=' + arr.length, raw);
                        render(arr);
                    })
                    .catch(function (e) {
                        dbg('err', 'autocomplete falhou', String(e));
                        close();
                    });
            }, 300);
        });

        input.addEventListener('keydown', function (e) {
            if (list.hidden) return;
            var nodes = list.querySelectorAll('.gf-ac__item');
            if (!nodes.length) return;
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                activeIdx = Math.min(activeIdx + 1, nodes.length - 1);
                nodes.forEach(function (n, i) { n.classList.toggle('is-active', i === activeIdx); });
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                activeIdx = Math.max(activeIdx - 1, 0);
                nodes.forEach(function (n, i) { n.classList.toggle('is-active', i === activeIdx); });
            } else if (e.key === 'Enter' && activeIdx >= 0) {
                e.preventDefault();
                pick(items[activeIdx]);
            } else if (e.key === 'Escape') {
                close();
            }
        });

        document.addEventListener('click', function (e) {
            if (!list.hidden && !list.contains(e.target) && e.target !== input) close();
        });
    }

    attachAutocomplete(els.loc, function (r) {
        if (r.lat && r.lng) {
            els.lat.value = r.lat;
            els.lng.value = r.lng;
            if (typeof window.__gfMapSet === 'function') window.__gfMapSet(parseFloat(r.lat), parseFloat(r.lng));
        }
        updateSummary('origem');
    });
    attachAutocomplete(els.dest, function (r) {
        if (r.lat && r.lng) {
            els.latD.value = r.lat;
            els.lngD.value = r.lng;
            if (typeof window.__gfMapSetDest === 'function') window.__gfMapSetDest(parseFloat(r.lat), parseFloat(r.lng));
        }
        updateSummary('destino');
    });

    // ─── Etapa 1 ──────────────────────────────────────────────
    function validateStep1() {
        var loc = (els.loc.value || '').trim();
        var hasLat = parseFloat(els.lat.value) !== 0 && els.lat.value !== '';
        if (!loc && !hasLat) { els.loc.classList.add('gf-invalid'); els.loc.focus(); return false; }
        els.loc.classList.remove('gf-invalid');
        if (hasLat) { advanceFromStep1(); return false; }
        fetch('/geocode/public?q=' + encodeURIComponent(loc), { credentials: 'same-origin' })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (raw) {
                var data = unwrap(raw);
                if (!data) return;
                var item = (data.results && data.results[0]) || data[0] || data;
                if (item && item.lat && item.lng) {
                    els.lat.value = item.lat;
                    els.lng.value = item.lng;
                    if (typeof window.__gfMapSet === 'function') window.__gfMapSet(parseFloat(item.lat), parseFloat(item.lng));
                }
            })
            .catch(function () {})
            .then(function () { advanceFromStep1(); });
        return false;
    }

    function advanceFromStep1() {
        var lat = els.lat.value, lng = els.lng.value;
        if (!lat || !lng) {
            state.hasOficina = true;
            state.hasGuincho = true;
            state.nOficinas = 0;
            applyBranch();
            showStep(2);
            return;
        }
        fetch('/api/pre-cotacao/cobertura?lat=' + encodeURIComponent(lat) + '&lng=' + encodeURIComponent(lng), { credentials: 'same-origin' })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (raw) {
                var data = unwrap(raw) || {};
                state.cobertura = data;
                state.nOficinas = Number(
                    data.qtd_oficinas ?? data.oficinas_count ?? data.total_oficinas ??
                    (Array.isArray(data.oficinas) ? data.oficinas.length : 0)
                ) || 0;
                state.hasOficina = state.nOficinas > 0 || data.tem_oficina === true || data.has_oficina === true;
                state.hasGuincho = data.tem_guincho === true || data.has_guincho === true;
                dbg('ok', 'cobertura', {
                    tem_oficina: state.hasOficina,
                    tem_guincho: state.hasGuincho,
                    qtd_oficinas: state.nOficinas,
                    raw: data,
                });
            })
            .catch(function (e) {
                dbg('err', 'cobertura falhou', String(e));
                state.hasOficina = true;
                state.hasGuincho = true;
            })
            .then(function () { applyBranch(); showStep(2); });
    }

    // §23 §1 (B-011-B2): quando o fluxo é reboque direto (escolha "Levar o carro"
    // ou ausência de oficina), tipo_problema precisa sair como "reboque" para
    // o motor mapear via ServiceTypeResolver → TOW_CAR.
    function syncTipoProblemaForModo() {
        if (!els.tipo) return;
        var ehReboque = state.modo === 'levar_carro' || state.modo === 'reboque' || state.hasOficina === false;
        if (ehReboque) {
            els.tipo.value = 'reboque';
            dbg('info', 'tipo_problema sincronizado', { valor: 'reboque', modo: state.modo, hasOficina: state.hasOficina });
        }
        // Para me_orientem/resolver_local, o valor é definido pela etapa 3 (sintoma)
    }

    function applyBranch() {
        var sub = document.getElementById('gfModeSubtitle');
        var modes = document.querySelectorAll('#gfModes .gf-mode');
        if (state.hasOficina) {
            if (sub) sub.textContent = 'Encontramos ' + state.nOficinas + ' oficina(s) na sua região. Como prefere seguir?';
            modes.forEach(function (b) { b.style.display = ''; });
        } else {
            if (sub) sub.textContent = 'Sem oficinas no seu raio no momento. Vamos direto ao reboque.';
            modes.forEach(function (b) { b.style.display = 'none'; });
            if (els.dec) els.dec.value = 'reboque';
            state.modo = 'reboque';
            syncTipoProblemaForModo();
            var levar = document.querySelector('#gfModes [data-mode="levar_carro"]');
            if (levar) {
                levar.style.display = '';
                levar.classList.add('is-selected');
            }
        }
    }

    // ─── Etapa 2 ──────────────────────────────────────────────
    document.querySelectorAll('#gfModes .gf-mode').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('#gfModes .gf-mode').forEach(function (b) { b.classList.remove('is-selected'); });
            btn.classList.add('is-selected');
            state.modo = String(btn.getAttribute('data-mode') || '').trim();
            if (els.dec) els.dec.value = state.modo;
            syncTipoProblemaForModo();
            updateSummary('modo');
            dbg('ok', 'modo escolhido', { modo: state.modo, tipo_problema: els.tipo ? els.tipo.value : null });
            var skipSintoma = state.modo === 'levar_carro' || state.modo === 'reboque' || state.hasOficina === false;
            showStep(skipSintoma ? 4 : 3);
        });
    });

    // ─── Etapa 3 ──────────────────────────────────────────────
    document.querySelectorAll('#gfSintomas [data-sintoma]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('#gfSintomas [data-sintoma]').forEach(function (b) { b.classList.remove('is-selected'); });
            btn.classList.add('is-selected');
            state.sintoma = btn.getAttribute('data-sintoma');
            if (els.tipo) els.tipo.value = state.sintoma;
            updateSummary('sintoma');
            dbg('ok', 'sintoma escolhido', { sintoma: state.sintoma, tipo_problema: els.tipo ? els.tipo.value : null });
            setTimeout(function () { showStep(4); }, 120);
        });
    });

    // ─── Etapa 4 ──────────────────────────────────────────────
    document.querySelectorAll('#gfVeiculos [data-categoria]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('#gfVeiculos [data-categoria]').forEach(function (b) { b.classList.remove('is-selected'); });
            btn.classList.add('is-selected');
            state.categoria = btn.getAttribute('data-categoria');
            if (els.cat) els.cat.value = state.categoria;
            updateSummary('categoria');
            dbg('ok', 'categoria escolhida', { categoria: state.categoria });
            var precisaDestino = state.modo === 'levar_carro' || state.modo === 'reboque' || state.hasOficina === false;
            if (precisaDestino) {
                showStep(5);
            } else {
                computeComparison();
                showStep(6);
            }
        });
    });

    // ─── Etapa 5 ──────────────────────────────────────────────
    function validateStep5() {
        var dest = (els.dest.value || '').trim();
        if (!dest) { els.dest.classList.add('gf-invalid'); els.dest.focus(); return false; }
        els.dest.classList.remove('gf-invalid');
        var numVis = document.getElementById('numero_destino_vis');
        if (numVis && els.numD) els.numD.value = (numVis.value || '').trim();
        if (!els.latD.value && dest.length >= 3) {
            dbg('info', 'geocode destino', dest);
            fetch('/geocode/public?q=' + encodeURIComponent(dest), { credentials: 'same-origin' })
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (raw) {
                    var data = unwrap(raw);
                    if (!data) return;
                    var item = (data.results && data.results[0]) || data[0] || data;
                    if (item && item.lat && item.lng) {
                        els.latD.value = item.lat;
                        els.lngD.value = item.lng;
                        if (typeof window.__gfMapSetDest === 'function') window.__gfMapSetDest(parseFloat(item.lat), parseFloat(item.lng));
                    }
                })
                .catch(function () {});
        }
        return true;
    }

    // ─── Etapa 6: comparativo ─────────────────────────────────
    function computeComparison() {
        // Garante tipo_problema correto antes de enviar
        syncTipoProblemaForModo();

        var body = new URLSearchParams();
        body.append('lat_origem', els.lat.value || '');
        body.append('lng_origem', els.lng.value || '');
        body.append('lat_destino', els.latD.value || '');
        body.append('lng_destino', els.lngD.value || '');
        body.append('tipo_problema', els.tipo ? els.tipo.value : (state.sintoma || 'reboque'));
        body.append('categoria', state.categoria || 'popular');
        body.append('decisao_atendimento', state.modo || 'reboque');

        dbg('info', 'POST /decisao', body.toString());

        fetch('/api/pre-cotacao/decisao', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
            body: body.toString(),
        })
        .then(function (r) {
            return r.text().then(function (t) {
                dbg(r.ok ? 'ok' : 'err', 'resposta ' + r.status, t.slice(0, 500));
                try { return JSON.parse(t); } catch (e) { return null; }
            });
        })
        .then(function (raw) {
            var data = unwrap(raw) || {};
            state.comparativo = data;
            renderComparison(data);
        })
        .catch(function (e) {
            dbg('err', 'comparativo request falhou', String(e));
            renderComparison({});
        });
    }

    function formatBRL(v) {
        var n = Number(v);
        if (!isFinite(n) || n <= 0) return 'Calculado em seguida';
        return 'R$ ' + n.toFixed(2).replace('.', ',');
    }

    function renderComparison(d) {
        var assist = d.opcao_assistencia || {};
        var reboque = d.opcao_reboque || {};
        var cAssist = document.querySelector('[data-decision="assistencia"]');
        var cReb = document.querySelector('[data-decision="reboque"]');
        var rec = document.getElementById('gfRecommendation');

        var modoDireto = state.modo === 'levar_carro' || state.modo === 'reboque' || state.hasOficina === false;

        // §23 / B-011-B4: o endpoint /api/pre-cotacao/decisao é OPERACIONAL.
        // Ele diz o que está disponível e o que a plataforma recomenda, mas
        // NÃO é a autoridade de preço. O preço definitivo vem só de
        // POST /pre-cotacao (AuthController → PrecificacaoService::calcular).
        // Por isso os cards não mostram valor aqui — mostram "calculado ao
        // confirmar".
        var temAssist = assist.disponivel === true;
        var temReboque = reboque.disponivel === true;
        var precos = d.precos || {};

        var showAssist = !modoDireto && temAssist;
        var showReb = temReboque;

        dbg('info', 'renderComparison', {
            modoDireto: modoDireto,
            showAssist: showAssist,
            showReb: showReb,
            nota: 'preços legados ignorados — valor oficial vem do POST /pre-cotacao',
        });

        if (cAssist) {
            cAssist.hidden = !showAssist;
            if (showAssist) {
                var pa = cAssist.querySelector('[data-price="assistencia"]');
                if (pa) pa.textContent = formatBRL(precos.assistencia);
            }
        }
        if (cReb) {
            cReb.hidden = !showReb;
            if (showReb) {
                var pr = cReb.querySelector('[data-price="reboque"]');
                if (pr) pr.textContent = formatBRL(precos.reboque);
                if (modoDireto) {
                    cReb.classList.add('is-selected');
                    if (els.dec) els.dec.value = 'reboque';
                    selectDecision('reboque', 0);
                }
            }
        }
        if (rec) {
            if (d.justificativa && !modoDireto) {
                rec.textContent = d.justificativa;
                rec.hidden = false;
            } else {
                rec.hidden = true;
            }
        }
        var btnSubmit = document.getElementById('btnSubmit');
        if (btnSubmit) btnSubmit.disabled = !(showAssist || showReb);
        updateSummary('comparativo', d);
        // NÃO chama updateSummary('comparativo', d) aqui — o total no
        // aside fica como "Calculado ao confirmar" até o POST final.
    }
	
    // ─── Preenche hidden valor_cotado ao escolher no comparativo ─
    function selectDecision(dec, preco) {
        state.escolha = dec;
        if (els.dec) els.dec.value = dec;
        if (els.valor) els.valor.value = ''; // client-side nunca determina dinheiro
        dbg('ok', 'decisão registrada', { escolha: dec, valor_cotado: els.valor ? els.valor.value : '' });
    }

    document.querySelectorAll('.gf-compare__card').forEach(function (card) {
        card.addEventListener('click', function () {
            document.querySelectorAll('.gf-compare__card').forEach(function (c) { c.classList.remove('is-selected'); });
            card.classList.add('is-selected');
            var dec = card.getAttribute('data-decision');
            var precoEl = card.querySelector('[data-price]');
            var preco = precoEl ? precoEl.textContent : '';
            var valorNumerico = 0;
            if (preco && preco.indexOf('R$') === 0) {
                valorNumerico = parseFloat(preco.replace('R$', '').replace(/\./g, '').replace(',', '.').trim()) || 0;
            }
            selectDecision(dec, valorNumerico);
            if (state.comparativo) updateSummary('comparativo', state.comparativo);
            if (dec === 'reboque' && !els.latD.value && !els.dest.value) {
                showStep(5);
                return;
            }
        });
    });

    // ─── Summary ──────────────────────────────────────────────
    function setMapLabel(text) {
        var el = document.getElementById('mapLabel');
        if (el) el.textContent = text;
    }

    function updateSummary(when, payload) {
        var svc = document.getElementById('summaryService');
        var desc = document.getElementById('summaryServiceDesc');
        var orig = document.getElementById('summaryOrigem');
        var dest = document.getElementById('summaryDestino');
        var total = document.getElementById('summaryTotal');

        if (when === 'modo' && svc) {
            var labels = { me_orientem: 'Me orientem', resolver_local: 'Resolver no local', levar_carro: 'Levar o carro', reboque: 'Reboque' };
            svc.textContent = labels[state.modo] || 'Atendimento';
        }
        if (when === 'sintoma' && desc) {
            desc.textContent = state.sintoma.charAt(0).toUpperCase() + state.sintoma.slice(1);
        }
        if (when === 'categoria' && desc) {
            var catLabels = { popular: 'Carro', moto: 'Moto', suv: 'SUV', utilitario: 'Utilitário' };
            desc.textContent = (state.sintoma ? state.sintoma.charAt(0).toUpperCase() + state.sintoma.slice(1) + ' · ' : '')
                + (catLabels[state.categoria] || state.categoria);
        }
        if (orig && els.loc && els.loc.value) orig.textContent = els.loc.value.slice(0, 40);
        if (dest && els.dest && els.dest.value) dest.textContent = els.dest.value.slice(0, 40);

        if (when === 'comparativo' && total) {
            var preco = payload && payload.precos ? payload.precos[state.escolha] : null;
            total.textContent = formatBRL(preco);
        }
    }

    // ─── GPS ──────────────────────────────────────────────────
    var btnGps = document.getElementById('btnGps');
    if (btnGps) {
        btnGps.addEventListener('click', function () {
            if (!navigator.geolocation) return;
            btnGps.disabled = true;
            navigator.geolocation.getCurrentPosition(function (pos) {
                els.lat.value = pos.coords.latitude.toFixed(7);
                els.lng.value = pos.coords.longitude.toFixed(7);
                if (typeof window.__gfMapSet === 'function') {
                    window.__gfMapSet(parseFloat(els.lat.value), parseFloat(els.lng.value));
                }
                setMapLabel('Localização capturada');
                btnGps.disabled = false;
            }, function () {
                btnGps.disabled = false;
            }, { enableHighAccuracy: true, timeout: 8000 });
        });
    }

    // ─── Sync summary no blur ─────────────────────────────────
    if (els.loc) els.loc.addEventListener('blur', function () { if (els.loc.value) updateSummary('origem'); });
    if (els.dest) els.dest.addEventListener('blur', function () { if (els.dest.value) updateSummary('destino'); });

    form.addEventListener('submit', function () {
        // Garante tipo_problema correto mesmo se o usuário voltou e trocou modo
        syncTipoProblemaForModo();

        if (!state.escolha && state.comparativo && state.comparativo.opcao_reboque) {
            selectDecision('reboque', 0);
        }
        var b = document.getElementById('btnSubmit');
        if (b) { b.disabled = true; b.textContent = 'Calculando…'; }
        dbg('ok', 'SUBMIT', {
            valor_cotado: els.valor ? els.valor.value : '(sem input)',
            decisao: els.dec ? els.dec.value : '',
            tipo_problema: els.tipo ? els.tipo.value : '',
            categoria: els.cat ? els.cat.value : '',
        });
    });

    window.__gfFlow = { state: state, showStep: showStep };
})();

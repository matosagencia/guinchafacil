/* public/assets/js/pre-cotacao-flow.js
   §23 · Wizard progressivo. Não escreve estado de negócio em hidden;
   só navega, chama APIs e propaga escolhas do usuário para inputs reais. */

(function () {
    'use strict';

    var form = document.getElementById('gfPreqForm');
    if (!form) return;

    var state = {
        step: 1,
        hasOficina: null,
        hasGuincho: null,
        modo: '',
        sintoma: '',
        categoria: 'popular',
        cobertura: null,
    };

    var els = {
        lat:  document.getElementById('lat_origem'),
        lng:  document.getElementById('lng_origem'),
        latD: document.getElementById('lat_destino'),
        lngD: document.getElementById('lng_destino'),
        num:  document.getElementById('numero_origem'),
        numD: document.getElementById('numero_destino'),
        loc:  document.getElementById('localizacao'),
        dest: document.getElementById('destino'),
        tipo: document.getElementById('tipo_problema'),
        dec:  document.getElementById('decisao_atendimento'),
        cat:  document.getElementById('categoria'),
    };

    // ─── Navegação ─────────────────────────────────────────────
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
        window.scrollTo({ top: 120, behavior: 'smooth' });
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

    // ─── Validações ────────────────────────────────────────────
    function validateStep1() {
        var loc = (els.loc.value || '').trim();
        var hasLat = parseFloat(els.lat.value) !== 0 && els.lat.value !== '';
        if (!loc && !hasLat) {
            els.loc.classList.add('gf-invalid');
            els.loc.focus();
            return false;
        }
        els.loc.classList.remove('gf-invalid');
        // sem coords: tenta geocodificar o texto antes de avançar
        if (!hasLat) {
            return geocodeAndAdvance();
        }
        return probeCobertura();
    }

    function validateStep5() {
        var dest = (els.dest.value || '').trim();
        if (!dest) {
            els.dest.classList.add('gf-invalid');
            els.dest.focus();
            return false;
        }
        els.dest.classList.remove('gf-invalid');
        // envia numero visível para hidden
        var numVis = document.getElementById('numero_destino_vis');
        if (numVis) els.numD.value = (numVis.value || '').trim();
        return true;
    }

    function geocodeAndAdvance() {
        var loc = (els.loc.value || '').trim();
        fetch('/geocode?q=' + encodeURIComponent(loc), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var item = (data && data.results && data.results[0]) || (data && data[0]);
                if (!item || !item.lat || !item.lng) throw new Error('geocode_vazio');
                els.lat.value = item.lat;
                els.lng.value = item.lng;
                return probeCobertura();
            })
            .catch(function () {
                setMapLabel('Endereço não localizado. Confira e tente novamente.');
            });
        return false;
    }

    // ─── Cobertura ─────────────────────────────────────────────
    function probeCobertura() {
        var lat = els.lat.value, lng = els.lng.value;
        if (!lat || !lng) return false;
        fetch('/api/pre-cotacao/cobertura?lat=' + encodeURIComponent(lat) + '&lng=' + encodeURIComponent(lng), {
            credentials: 'same-origin',
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            state.cobertura = data || {};
            state.hasOficina = !!(data && (data.tem_oficina || data.has_oficina));
            state.hasGuincho = !!(data && (data.tem_guincho || data.has_guincho));
            applyBranch();
            showStep(2);
        })
        .catch(function () {
            // Sem cobertura da API: assume o caminho mais conservador (reboque).
            state.hasOficina = false;
            state.hasGuincho = true;
            applyBranch();
            showStep(5); // vai direto ao destino (fallback)
        });
        return false; // navegação acontece no callback
    }

    function applyBranch() {
        var sub = document.getElementById('gfModeSubtitle');
        if (state.hasOficina) {
            if (sub) sub.textContent = 'Encontramos oficinas na sua região. Como prefere seguir?';
            document.querySelectorAll('#gfModes .gf-mode').forEach(function (b) { b.style.display = ''; });
        } else {
            // Sem oficina → pula a escolha e vai direto ao destino
            if (sub) sub.textContent = 'Você está fora do raio de oficinas. Vamos direto ao reboque.';
            document.querySelectorAll('#gfModes .gf-mode').forEach(function (b) { b.style.display = 'none'; });
            els.dec.value = 'reboque';
            state.modo = 'reboque';
            // mostra 1 card grande: "Levar o carro"
            var levar = document.querySelector('#gfModes [data-mode="levar_carro"]');
            if (levar) {
                levar.style.display = '';
                levar.classList.add('is-selected');
            }
        }
    }

    // ─── Modo ──────────────────────────────────────────────────
    document.querySelectorAll('#gfModes .gf-mode').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('#gfModes .gf-mode').forEach(function (b) { b.classList.remove('is-selected'); });
            btn.classList.add('is-selected');
            state.modo = btn.getAttribute('data-mode');
            els.dec.value = state.modo;
            updateSummary('modo');
            showStep(3);
        });
    });

    // ─── Sintomas ──────────────────────────────────────────────
    document.querySelectorAll('#gfSintomas [data-sintoma]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('#gfSintomas [data-sintoma]').forEach(function (b) { b.classList.remove('is-selected'); });
            btn.classList.add('is-selected');
            state.sintoma = btn.getAttribute('data-sintoma');
            els.tipo.value = state.sintoma === 'combustivel' ? 'combustivel' : state.sintoma;
            updateSummary('sintoma');
            setTimeout(function () { showStep(4); }, 120);
        });
    });

    // ─── Categoria ─────────────────────────────────────────────
    document.querySelectorAll('#gfVeiculos [data-categoria]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('#gfVeiculos [data-categoria]').forEach(function (b) { b.classList.remove('is-selected'); });
            btn.classList.add('is-selected');
            state.categoria = btn.getAttribute('data-categoria');
            els.cat.value = state.categoria;
            updateSummary('categoria');
            // Se modo=levar_carro ou fallback → vai ao destino; senão pula para 6
            var precisaDestino = state.modo === 'levar_carro' || state.modo === 'reboque';
            if (precisaDestino) {
                showStep(5);
            } else {
                computeComparison();
                showStep(6);
            }
        });
    });

    // ─── Comparativo ───────────────────────────────────────────
    function computeComparison() {
        var payload = {
            lat: els.lat.value, lng: els.lng.value,
            lat_destino: els.latD.value || null, lng_destino: els.lngD.value || null,
            tipo_problema: state.sintoma, categoria: state.categoria,
            decisao_atendimento: state.modo,
        };
        fetch('/api/pre-cotacao/decisao', {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        })
        .then(function (r) { return r.json(); })
        .then(function (data) { renderComparison(data || {}); })
        .catch(function () { renderComparison({}); });
    }

    function renderComparison(d) {
        var assist = d.opcao_assistencia || {};
        var reboque = d.opcao_reboque || {};
        var cAssist = document.querySelector('[data-decision="assistencia"]');
        var cReb = document.querySelector('[data-decision="reboque"]');
        var rec = document.getElementById('gfRecommendation');

        var showAssist = !!(assist.disponivel);
        var showReb = !!(reboque.custo_total);

        if (cAssist) {
            cAssist.hidden = !showAssist;
            if (showAssist) {
                cAssist.querySelector('[data-price="assistencia"]').textContent =
                    'R$ ' + (assist.custo_saida || 0).toFixed(2).replace('.', ',');
            }
        }
        if (cReb) {
            cReb.hidden = !showReb;
            if (showReb) {
                cReb.querySelector('[data-price="reboque"]').textContent =
                    'R$ ' + (reboque.custo_total || 0).toFixed(2).replace('.', ',');
            }
        }
        if (rec && d.justificativa) {
            rec.textContent = d.justificativa;
            rec.hidden = false;
        }
        var btnSubmit = document.getElementById('btnSubmit');
        if (btnSubmit) btnSubmit.disabled = !(showAssist || showReb);
        updateSummary('comparativo', d);
    }

    document.querySelectorAll('.gf-compare__card').forEach(function (card) {
        card.addEventListener('click', function () {
            document.querySelectorAll('.gf-compare__card').forEach(function (c) { c.classList.remove('is-selected'); });
            card.classList.add('is-selected');
            var dec = card.getAttribute('data-decision');
            els.dec.value = dec;
            // se escolheu reboque e ainda não tem destino, volta pra etapa 5
            if (dec === 'reboque' && !els.latD.value && !els.dest.value) {
                showStep(5);
                setTimeout(function () { document.getElementById('gfCompareTitle').textContent = 'Informe o destino'; }, 200);
                return;
            }
        });
    });

    // ─── Summary (aside) ───────────────────────────────────────
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
            desc.textContent = (desc.textContent || '') + ' · ' + state.categoria;
        }
        if (orig && els.loc.value) orig.textContent = els.loc.value.slice(0, 40);
        if (dest && els.dest.value) dest.textContent = els.dest.value.slice(0, 40);

        if (when === 'comparativo' && total && payload) {
            var a = payload.opcao_assistencia || {};
            var r = payload.opcao_reboque || {};
            var escolha = payload.recomendacao === 'assistencia' ? (a.custo_saida || 0) : (r.custo_total || 0);
            if (escolha) total.textContent = 'R$ ' + escolha.toFixed(2).replace('.', ',');
        }
    }

    // ─── Botão GPS ─────────────────────────────────────────────
    var btnGps = document.getElementById('btnGps');
    if (btnGps) {
        btnGps.addEventListener('click', function () {
            if (!navigator.geolocation) return;
            btnGps.disabled = true;
            navigator.geolocation.getCurrentPosition(function (pos) {
                els.lat.value = pos.coords.latitude.toFixed(7);
                els.lng.value = pos.coords.longitude.toFixed(7);
                if (window.__gfMapSet && typeof window.__gfMapSet === 'function') {
                    window.__gfMapSet(parseFloat(els.lat.value), parseFloat(els.lng.value));
                }
                setMapLabel('Localização capturada');
                btnGps.disabled = false;
            }, function () {
                btnGps.disabled = false;
            }, { enableHighAccuracy: true, timeout: 8000 });
        });
    }

    // ─── Sync summary com input de endereço ────────────────────
    if (els.loc) els.loc.addEventListener('blur', function () {
        if (els.loc.value) updateSummary('origem');
    });
    if (els.dest) els.dest.addEventListener('blur', function () {
        if (els.dest.value) updateSummary('destino');
    });

    // ─── Geocoding do destino ao blur ──────────────────────────
    if (els.dest) els.dest.addEventListener('blur', function () {
        var q = (els.dest.value || '').trim();
        if (!q || els.latD.value) return;
        fetch('/geocode?q=' + encodeURIComponent(q), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var item = (data && data.results && data.results[0]) || (data && data[0]);
                if (item && item.lat) {
                    els.latD.value = item.lat;
                    els.lngD.value = item.lng;
                    if (window.__gfMapSetDest && typeof window.__gfMapSetDest === 'function') {
                        window.__gfMapSetDest(parseFloat(item.lat), parseFloat(item.lng));
                    }
                }
            })
            .catch(function () {});
    });

    // ─── Submit guard ──────────────────────────────────────────
    form.addEventListener('submit', function () {
        var b = document.getElementById('btnSubmit');
        if (b) { b.disabled = true; b.textContent = 'Calculando…'; }
    });

    // expõe pra debug
    window.__gfFlow = { state: state, showStep: showStep };
})();
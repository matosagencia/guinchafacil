/**
 * GuinchaFácil — Resiliência de GPS (last-known-good + beacon + degradação).
 *
 * Complementa o PorOfflineQueue (que resolve FALHA DE REDE) tratando a
 * FALHA DE SENSOR GPS: permissão negada, sem fix, accuracy ruim, timeout.
 * A regra é nunca ficar sem coordenada — degradar em cascata e sinalizar a
 * confiança:
 *   GPS bom → GPS impreciso (com aviso) → última posição conhecida (com idade)
 *   → (na criação do pedido) endereço/clique manual.
 *
 * Sem dependências. Seguro em navegadores sem localStorage/sendBeacon
 * (degrada para no-op). Persiste por pedido, então sobrevive a reload e a
 * quedas de sinal no meio do trajeto.
 */
(function (global) {
    'use strict';

    var LKG_PREFIX = 'gf_lkg_';   // last-known-good por pedido
    var DRAFT_KEY  = 'gf_pedido_rascunho'; // rascunho de origem/destino (criação)

    function safeGet(key) {
        try { return global.localStorage ? global.localStorage.getItem(key) : null; } catch (e) { return null; }
    }
    function safeSet(key, val) {
        try { if (global.localStorage) global.localStorage.setItem(key, val); } catch (e) {}
    }
    function safeDel(key) {
        try { if (global.localStorage) global.localStorage.removeItem(key); } catch (e) {}
    }

    /** Guarda a última posição BOA de um pedido (só quando a precisão vale a pena). */
    function saveLastGood(pedidoId, ponto) {
        if (!pedidoId || !ponto) return;
        safeSet(LKG_PREFIX + pedidoId, JSON.stringify({
            lat: ponto.lat, lng: ponto.lng,
            accuracy: ponto.accuracy != null ? ponto.accuracy : null,
            ts: ponto.ts || Date.now()
        }));
    }
    /** Recupera a última posição boa conhecida do pedido (ou null). */
    function loadLastGood(pedidoId) {
        var s = safeGet(LKG_PREFIX + pedidoId);
        if (!s) return null;
        try { return JSON.parse(s); } catch (e) { return null; }
    }
    function clearLastGood(pedidoId) { safeDel(LKG_PREFIX + pedidoId); }

    /**
     * Envio "à prova de fechamento": usa sendBeacon (não é cancelado quando a
     * aba vai a background ou é morta pelo SO). `params` é um objeto simples.
     * Retorna true se o beacon foi aceito para envio.
     */
    function beacon(url, params) {
        try {
            if (!global.navigator || !global.navigator.sendBeacon) return false;
            var body = new URLSearchParams(params || {}).toString();
            var blob = new Blob([body], { type: 'application/x-www-form-urlencoded;charset=UTF-8' });
            return global.navigator.sendBeacon(url, blob);
        } catch (e) { return false; }
    }

    /** Classifica a precisão do fix (metros) em rótulo humano. */
    function accuracyLevel(m) {
        if (m == null || isNaN(m)) return { key: 'unknown', label: 'precisão desconhecida' };
        if (m <= 30)  return { key: 'good', label: 'precisão ótima' };
        if (m <= 100) return { key: 'good', label: 'precisão boa' };
        if (m <= 500) return { key: 'fair', label: 'precisão baixa' };
        return { key: 'poor', label: 'precisão muito baixa' };
    }

    /** "há 8s", "há 3 min", "há 1h" — a partir de um timestamp em ms. */
    function ageLabel(tsMs) {
        if (!tsMs) return '';
        var s = Math.max(0, Math.round((Date.now() - tsMs) / 1000));
        if (s < 60) return 'há ' + s + 's';
        var m = Math.round(s / 60);
        if (m < 60) return 'há ' + m + ' min';
        return 'há ' + Math.round(m / 60) + 'h';
    }

    /** Traduz o código de erro do Geolocation API em mensagem acionável. */
    function geoErrorMessage(err) {
        if (!err) return 'Falha ao obter localização.';
        switch (err.code) {
            case 1: return 'Permissão de localização negada. Ative o GPS para o app.';
            case 2: return 'Sinal de GPS indisponível no momento.';
            case 3: return 'Tempo esgotado ao buscar o GPS.';
            default: return 'Falha ao obter localização.';
        }
    }

    // Rascunho de criação de pedido (gap 5) — sobrevive a reload da página.
    function saveDraft(draft) {
        if (!draft) return;
        safeSet(DRAFT_KEY, JSON.stringify({ ts: Date.now(), data: draft }));
    }
    function loadDraft(maxAgeMs) {
        var s = safeGet(DRAFT_KEY);
        if (!s) return null;
        try {
            var obj = JSON.parse(s);
            if (maxAgeMs && obj.ts && (Date.now() - obj.ts) > maxAgeMs) { safeDel(DRAFT_KEY); return null; }
            return obj.data || null;
        } catch (e) { return null; }
    }
    function clearDraft() { safeDel(DRAFT_KEY); }

    global.GpsResilience = {
        saveLastGood: saveLastGood,
        loadLastGood: loadLastGood,
        clearLastGood: clearLastGood,
        beacon: beacon,
        accuracyLevel: accuracyLevel,
        ageLabel: ageLabel,
        geoErrorMessage: geoErrorMessage,
        saveDraft: saveDraft,
        loadDraft: loadDraft,
        clearDraft: clearDraft
    };
})(window);

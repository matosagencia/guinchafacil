(function () {
    'use strict';

    function escapeHtml(value) {
        return String(value || '').replace(/[&<>"']/g, function (ch) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[ch];
        });
    }

    function render(el, data) {
        if (!el || !data || !data.ok) return;

        var label = escapeHtml(data.status_label || data.status || 'Atualizando');
        var chat = data.tem_chat_novo ? '<span class="status-banner-chat">Mensagem nova</span>' : '';
        var name = escapeHtml(data.guincho_nome || data.cliente_nome || '');
        
        var details = '';
        if (data.guincho_nome) {
            details = '<br><small>' + 
                      (data.guincho_tipo ? escapeHtml(data.guincho_tipo) + ' ' : '') + 
                      (data.guincho_placa ? escapeHtml(data.guincho_placa) : '') + 
                      '</small>';
        }

        var extra = name ? '<span class="status-banner-extra">' + name + details + '</span>' : '';

        el.dataset.status = data.status || '';
        var labelEl = el.querySelector('[data-status-label]');
        var extraEl = el.querySelector('[data-status-extra]');
        if (labelEl) labelEl.innerHTML = label;
        if (extraEl) extraEl.innerHTML = extra + chat;
    }

    async function update(el, url) {
        var data = await window.apiFetch(url + (url.indexOf('?') >= 0 ? '&' : '?') + '_=' + Date.now());
        render(el, data);
    }

    window.AtendimentoStatus = {
        start: function (options) {
            var el = document.querySelector(options.bannerSelector || '.status-banner-live');
            if (!el) return;

            var url = options.url || el.dataset.statusUrl;
            if (!url) return;

            var intervalMs = Math.max(5000, Number(options.intervalMs || 15000));
            update(el, url).catch(function (e) { console.warn('[atendimento-status] falha ao atualizar:', e); });
            var handle = setInterval(function () {
                update(el, url).catch(function (error) {
                    if (!error || error.message !== 'SESSION_EXPIRED') console.error('[AtendimentoStatus][update]', error);
                });
            }, intervalMs);
            if (window.SessionManager) window.SessionManager.registerPolling(handle);
        }
    };
})();

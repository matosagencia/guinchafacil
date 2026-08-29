(function (window, document) {
    'use strict';

    var expired = false;
    var pollingHandles = [];

    function basePath() {
        return String(window.BASE_PATH || (window.GUINCHA_CONFIG && window.GUINCHA_CONFIG.basePath) || '');
    }

    function currentReturnUrl() {
        return window.location.pathname + window.location.search + window.location.hash;
    }

    function stopAllPollings() {
        pollingHandles.forEach(function (handle) {
            window.clearInterval(handle);
            window.clearTimeout(handle);
        });
        pollingHandles = [];
        document.dispatchEvent(new CustomEvent('guincha:session-expired'));
    }

    function registerPolling(handle) {
        if (handle !== null && typeof handle !== 'undefined') pollingHandles.push(handle);
        return handle;
    }

    function redirectToLogin() {
        var login = basePath() + '/login';
        var separator = login.indexOf('?') >= 0 ? '&' : '?';
        window.location.assign(login + separator + 'motivo=sessao_expirada&retorno=' + encodeURIComponent(currentReturnUrl()));
    }

    function showExpiredModal() {
        var modalEl = document.getElementById('sessionExpiredModal');
        if (modalEl && window.bootstrap && window.bootstrap.Modal) {
            window.bootstrap.Modal.getOrCreateInstance(modalEl, { backdrop: 'static', keyboard: false }).show();
            return;
        }
        redirectToLogin();
    }

    function handleUnauthorized() {
        if (expired) return;
        expired = true;
        stopAllPollings();
        showExpiredModal();
    }

    window.SessionManager = {
        isExpired: function () { return expired; },
        registerPolling: registerPolling,
        stopAllPollings: stopAllPollings,
        handleUnauthorized: handleUnauthorized,
        redirectToLogin: redirectToLogin
    };
})(window, document);

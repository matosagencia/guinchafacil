(function (window, document) {
    'use strict';

    var body = document.body;
    var basePath = '';

    if (body) {
        basePath = String(body.getAttribute('data-base-path') || '');
    }

    window.BASE_PATH = window.BASE_PATH || basePath;
    window.GUINCHA_CONFIG = window.GUINCHA_CONFIG || {
        basePath: basePath,
        loginUrl: basePath + '/login',
        sessionStatusUrl: basePath + '/auth/session-status'
    };
})(window, document);

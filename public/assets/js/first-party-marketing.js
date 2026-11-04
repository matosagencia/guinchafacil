(function () {
    'use strict';
    var base = document.body && document.body.dataset.basePath ? document.body.dataset.basePath : '';
    var endpoint = base + '/api/marketing/event';
    var consent = 'unknown';
    var referralCode = '';
    try { consent = localStorage.getItem('gf_marketing_consent_v1') || 'unknown'; } catch (e) {}
    try {
        referralCode = new URLSearchParams(window.location.search).get('ref') || localStorage.getItem('gf_referral_code') || '';
        if (referralCode) localStorage.setItem('gf_referral_code', referralCode.slice(0, 80));
    } catch (e) {}

    function record(element) {
        var name = element && element.dataset ? element.dataset.marketingEvent : '';
        if (!name || !window.fetch) return;
        var payload = {
            event_name: name,
            event_label: element.dataset.marketingLabel || null,
            page_path: window.location.pathname,
            destination: element.href || null,
            consent_status: consent,
            user_type: document.body.dataset.userType || 'visitante',
            metadata: referralCode ? { referral_code: referralCode.slice(0, 80) } : {}
        };
        window.fetch(endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
            keepalive: true
        }).catch(function () {});
    }

    document.querySelectorAll('[data-marketing-event]').forEach(function (element) {
        element.addEventListener(element.tagName === 'FORM' ? 'submit' : 'click', function () { record(element); });
    });
}());

(function () {
    'use strict';
    var script = document.currentScript;
    if (!script || script.dataset.enabled !== '1') return;

    var storageKey = 'gf_marketing_consent_v1';
    var ga4 = script.dataset.ga4Id || '';
    var ads = script.dataset.googleAdsId || '';
    var meta = script.dataset.metaPixelId || '';
    var userType = script.dataset.userType || 'visitante';
    var consent = null;
    var metaLoaded = false;
    try { consent = localStorage.getItem(storageKey); } catch (e) {}

    window.dataLayer = window.dataLayer || [];
    window.gtag = window.gtag || function () { window.dataLayer.push(arguments); };

    function googleConsent(value, isDefault) {
        var state = value === 'yes' ? 'granted' : 'denied';
        var settings = {
            ad_storage: state,
            analytics_storage: state,
            ad_user_data: state,
            ad_personalization: state
        };
        if (isDefault && consent === null) settings.wait_for_update = 500;
        window.gtag('consent', isDefault ? 'default' : 'update', settings);
    }

    function load(src) {
        var tag = document.createElement('script');
        tag.async = true;
        tag.src = src;
        document.head.appendChild(tag);
    }

    function loadMeta() {
        if (!meta || metaLoaded) return;
        metaLoaded = true;
        window.fbq = window.fbq || function () { (window.fbq.q = window.fbq.q || []).push(arguments); };
        load('https://connect.facebook.net/en_US/fbevents.js');
        window.fbq('init', meta);
        window.fbq('track', 'PageView');
    }

    function sendEvent(eventName) {
        if (!eventName) return;
        window.gtag('event', eventName);
        if (eventName === 'generate_lead' && ads && script.dataset.googleAdsLabel) {
            window.gtag('event', 'conversion', { send_to: ads + '/' + script.dataset.googleAdsLabel });
        }
        if (consent === 'yes' && window.fbq && ['generate_lead', 'sign_up', 'create_order'].indexOf(eventName) !== -1) {
            window.fbq('track', 'Lead');
        }
    }

    googleConsent(consent, true);
    window.gtag('set', 'ads_data_redaction', true);
    window.gtag('set', 'user_properties', { user_type: userType });
    window.gtag('js', new Date());
    if (ga4) window.gtag('config', ga4);
    if (ads) window.gtag('config', ads);
    if (ga4 || ads) load('https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(ga4 || ads));
    if (consent === 'yes') loadMeta();

    document.querySelectorAll('[data-marketing-event]').forEach(function (element) {
        var type = element.tagName === 'FORM' ? 'submit' : 'click';
        element.addEventListener(type, function () { sendEvent(element.dataset.marketingEvent); });
    });
    sendEvent(script.dataset.pageEvent || '');

    function setConsent(value) {
        consent = value === 'yes' ? 'yes' : 'no';
        try { localStorage.setItem(storageKey, consent); } catch (e) {}
        googleConsent(consent, false);
        if (consent === 'yes') loadMeta();
        var current = document.getElementById('gfMarketingConsent');
        if (current) current.remove();
    }

    function consentBox() {
        if (document.getElementById('gfMarketingConsent')) return;
        var box = document.createElement('aside');
        box.id = 'gfMarketingConsent';
        box.setAttribute('role', 'dialog');
        box.setAttribute('aria-label', 'Preferências de privacidade');
        box.style.cssText = 'position:fixed;z-index:9999;left:16px;right:16px;bottom:16px;max-width:620px;margin:auto;padding:16px 18px;background:#142018;color:#e8fcea;border:1px solid #2fb34a;border-radius:14px;box-shadow:0 12px 40px rgba(0,0,0,.3);font:14px system-ui,sans-serif';
        box.innerHTML = '<strong>Privacidade e melhoria do atendimento</strong><p style="margin:6px 0 12px;opacity:.85">Usamos dados de navegação para medir campanhas e melhorar o GuinchaFácil. Você escolhe.</p><button type="button" data-consent="yes" style="margin-right:8px;padding:8px 14px;border:0;border-radius:8px;background:#2fb34a;color:#fff;font-weight:700">Aceitar</button><button type="button" data-consent="no" style="padding:8px 14px;border:1px solid #8fb89a;border-radius:8px;background:transparent;color:#e8fcea">Recusar</button>';
        document.body.appendChild(box);
        box.addEventListener('click', function (event) {
            if (event.target.dataset.consent) setConsent(event.target.dataset.consent);
        });
    }

    window.GFMarketingConsent = { set: setConsent, open: consentBox };
    if (consent === null) consentBox();
}());

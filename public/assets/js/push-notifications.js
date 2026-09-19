(function () {
  var basePath = document.body && document.body.dataset ? (document.body.dataset.basePath || '') : '';
  var swUrl = basePath + '/sw.js';
  var registrationPromise = null;

  function supportsPush() {
    return 'serviceWorker' in navigator && 'Notification' in window && 'PushManager' in window;
  }

  function getConfig() {
    return window.GFPushConfig || {};
  }

  function ensureServiceWorker() {
    if (!supportsPush()) {
      return Promise.resolve(null);
    }
    if (!registrationPromise) {
      registrationPromise = navigator.serviceWorker.register(swUrl, { scope: '/' });
    }
    return registrationPromise;
  }

  function requestPermission() {
    if (!supportsPush()) {
      return Promise.reject(new Error('Notificacoes nao suportadas neste navegador.'));
    }
    return ensureServiceWorker().then(function () {
      return Notification.requestPermission();
    });
  }

  function getSubscription() {
    return ensureServiceWorker().then(function (registration) {
      if (!registration || !registration.pushManager) {
        return null;
      }
      return registration.pushManager.getSubscription();
    });
  }

  function base64ToUint8Array(base64String) {
    var padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    var base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    var rawData = window.atob(base64);
    var outputArray = new Uint8Array(rawData.length);
    for (var i = 0; i < rawData.length; ++i) {
      outputArray[i] = rawData.charCodeAt(i);
    }
    return outputArray;
  }

  function postJson(url, payload) {
    return fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      },
      credentials: 'same-origin',
      body: JSON.stringify(payload)
    }).then(function (response) {
      return response.json().catch(function () {
        return {};
      }).then(function (data) {
        if (!response.ok || !data || data.ok === false) {
          var message = (data && (data.erro || data.mensagem)) || ('HTTP ' + response.status);
          throw new Error(message);
        }
        return data;
      });
    });
  }

  async function subscribe() {
    var config = getConfig();
    if (!config.publicKey) {
      throw new Error('Chave VAPID publica nao configurada.');
    }

    var permission = Notification.permission;
    if (permission !== 'granted') {
      permission = await requestPermission();
    }
    if (permission !== 'granted') {
      throw new Error('Permissao de notificacao nao concedida.');
    }

    var registration = await ensureServiceWorker();
    if (!registration || !registration.pushManager) {
      throw new Error('Push nao disponivel neste navegador.');
    }

    var subscription = await registration.pushManager.subscribe({
      userVisibleOnly: true,
      applicationServerKey: base64ToUint8Array(config.publicKey)
    });

    await postJson(config.subscribeUrl, {
      csrf_token: config.csrfToken || '',
      subscription: subscription.toJSON(),
      user_agent: navigator.userAgent || ''
    });

    return subscription;
  }

  async function unsubscribe() {
    var config = getConfig();
    var registration = await ensureServiceWorker();
    if (!registration || !registration.pushManager) {
      return null;
    }

    var subscription = await registration.pushManager.getSubscription();
    if (!subscription) {
      return null;
    }

    await postJson(config.unsubscribeUrl, {
      csrf_token: config.csrfToken || '',
      endpoint: subscription.endpoint
    });

    await subscription.unsubscribe();
    return null;
  }

  async function syncToggle(button) {
    var config = getConfig();
    var scope = button.closest('section') || button.closest('.card') || button.parentElement;
    var state = scope ? scope.querySelector('[data-push-state]') : null;
    var subscription = await getSubscription();
    var subscribed = !!subscription;

    button.textContent = subscribed ? 'Desativar alertas' : 'Ativar alertas';
    button.title = config.publicKey ? '' : 'Configure PUSH_VAPID_PUBLIC_KEY no .env';
    if (state) {
      state.textContent = subscribed ? 'Ativo' : 'Inativo';
      state.className = 'badge ' + (subscribed ? 'text-bg-success' : 'text-bg-secondary');
    }
  }

  function bindToggle(button) {
    button.addEventListener('click', async function () {
      try {
        button.disabled = true;
        var subscription = await getSubscription();
        if (subscription) {
          await unsubscribe();
        } else {
          await subscribe();
        }
      } catch (error) {
        window.console && console.error(error);
        alert(error && error.message ? error.message : 'Nao foi possivel atualizar as notificacoes.');
      } finally {
        await syncToggle(button);
      }
    });

    syncToggle(button).catch(function () {
      button.disabled = false;
    });
  }

  window.GFPush = {
    supportsPush: supportsPush,
    ensureServiceWorker: ensureServiceWorker,
    requestPermission: requestPermission,
    getSubscription: getSubscription,
    subscribe: subscribe,
    unsubscribe: unsubscribe,
    syncToggle: syncToggle
  };

  document.addEventListener('DOMContentLoaded', function () {
    var buttons = document.querySelectorAll('[data-push-toggle]');
    if (!buttons.length) {
      return;
    }
    buttons.forEach(bindToggle);
  });
})();

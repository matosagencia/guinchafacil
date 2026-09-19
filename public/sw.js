self.addEventListener('install', function (event) {
  self.skipWaiting();
});

self.addEventListener('activate', function (event) {
  event.waitUntil(self.clients.claim());
});

async function showFallbackNotification(eventData) {
  var payload = {};
  try {
    payload = eventData ? eventData.json() : {};
  } catch (e) {
    payload = {};
  }

  var title = payload.title || 'GuinchaF\u00e1cil';
  var body = payload.body || 'Novo chamado dispon\u00edvel.';

  await self.registration.showNotification(title, {
    body: body,
    icon: payload.icon || '/public/assets/img/favicon-32.png',
    badge: payload.badge || '/public/assets/img/favicon-32.png',
    data: payload.data || {},
    tag: payload.tag || 'guinchafacil-call',
    renotify: true,
    actions: Array.isArray(payload.actions) ? payload.actions : []
  });
}

async function showOfferNotification() {
  var response = await fetch('/especialista/notificacoes', {
    credentials: 'include',
    cache: 'no-store'
  });
  var data = await response.json().catch(function () {
    return null;
  });

  var ofertas = data && Array.isArray(data.ofertas) ? data.ofertas : [];
  var oferta = null;
  for (var i = 0; i < ofertas.length; i++) {
    if ((ofertas[i] && ofertas[i].status) === 'ofertado') {
      oferta = ofertas[i];
      break;
    }
  }

  if (!oferta) {
    return false;
  }

  await self.registration.showNotification('Chamado dispon\u00edvel', {
    body: (oferta.servico_nome || 'Novo atendimento') + ' - ' + (oferta.endereco_origem || 'Ver detalhes no painel'),
    icon: '/public/assets/img/favicon-32.png',
    badge: '/public/assets/img/favicon-32.png',
    tag: 'guinchafacil-atendimento-' + String(oferta.id || '0'),
    renotify: true,
    data: {
      url: '/especialista/dashboard',
      atendimentoId: oferta.id || 0,
      push_accept_url: oferta.push_accept_url || '',
      push_decline_url: oferta.push_decline_url || '',
      push_accept_token: oferta.push_accept_token || '',
      push_decline_token: oferta.push_decline_token || ''
    },
    actions: [
      { action: 'aceitar', title: 'Aceitar' },
      { action: 'recusar', title: 'Recusar' }
    ]
  });

  return true;
}

self.addEventListener('push', function (event) {
  event.waitUntil((async function () {
    try {
      if (await showOfferNotification()) {
        return;
      }
    } catch (e) {}

    await showFallbackNotification(event.data);
  })());
});

self.addEventListener('notificationclick', function (event) {
  event.notification.close();

  var data = event.notification.data || {};
  var action = event.action || '';
  var url = data.url || '/';
  var actionUrl = null;
  var actionToken = '';

  if (action === 'aceitar') {
    actionUrl = data.push_accept_url || null;
    actionToken = data.push_accept_token || '';
  } else if (action === 'recusar') {
    actionUrl = data.push_decline_url || null;
    actionToken = data.push_decline_token || '';
  }

  event.waitUntil((async function () {
    if (actionUrl) {
      try {
        await fetch(actionUrl, {
          method: 'POST',
          credentials: 'include',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
          body: new URLSearchParams({
            token: actionToken,
            atendimento_id: String(data.atendimentoId || ''),
            origem: 'push'
          })
        });
      } catch (e) {}
    }

    var clients = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
    for (var i = 0; i < clients.length; i++) {
      var client = clients[i];
      if ('focus' in client) {
        try {
          await client.focus();
          if (url && 'navigate' in client) {
            await client.navigate(url);
          }
          return;
        } catch (e) {}
      }
    }

    if (self.clients.openWindow) {
      await self.clients.openWindow(url);
    }
  })());
});

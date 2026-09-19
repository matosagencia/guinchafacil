(function () {
  var form = document.querySelector('form[action*="/especialista/"]');
  var bp = form && form.action ? form.action.split('/especialista/')[0] : '';
  var seenOffers = new Set();
  var notificationPromise = null;

  function canNotify() {
    return 'Notification' in window && 'serviceWorker' in navigator;
  }

  function ensurePermission() {
    if (!canNotify()) {
      return Promise.resolve(false);
    }
    if (Notification.permission === 'granted') {
      return Promise.resolve(true);
    }
    return Notification.requestPermission().then(function (result) {
      return result === 'granted';
    });
  }

  function ensureRegistration() {
    if (!canNotify()) {
      return Promise.resolve(null);
    }
    if (!notificationPromise) {
      notificationPromise = navigator.serviceWorker.ready;
    }
    return notificationPromise;
  }

  function showOfferNotification(offer) {
    if (!offer || !offer.id || seenOffers.has(String(offer.id))) {
      return Promise.resolve();
    }
    seenOffers.add(String(offer.id));

    return ensurePermission().then(function (granted) {
      if (!granted) {
        return null;
      }
      return ensureRegistration().then(function (registration) {
        if (!registration || !registration.showNotification) {
          return null;
        }
        return registration.showNotification('Chamado disponível', {
          body: (offer.servico_nome || 'Novo chamado') + ' - ' + (offer.endereco_origem || 'local não informado'),
          icon: '/public/assets/img/favicon-32.png',
          badge: '/public/assets/img/favicon-32.png',
          tag: 'especialista-oferta-' + offer.id,
          renotify: true,
          data: {
            url: bp + '/especialista/dashboard',
            pedidoId: offer.id,
            acceptUrl: bp + '/especialista/atendimento/aceitar/' + offer.id,
            declineUrl: bp + '/especialista/notificacoes',
            csrfToken: document.querySelector('input[name="csrf_token"]') ? document.querySelector('input[name="csrf_token"]').value : ''
          },
          actions: [
            { action: 'aceitar', title: 'Aceitar' },
            { action: 'recusar', title: 'Recusar' }
          ]
        });
      });
    }).catch(function () {});
  }

  function pollNotificacoes() {
    if (!bp) {
      return;
    }
    fetch(bp + '/especialista/notificacoes', { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d || !d.ok) {
          return;
        }
        var ofertas = Array.isArray(d.ofertas) ? d.ofertas : [];
        var ofertados = ofertas.filter(function (x) { return x && x.status === 'ofertado'; });
        document.title = ofertados.length ? '(' + ofertados.length + ') Chamado disponível — Especialista' : 'Painel do especialista';
        ofertados.slice(0, 3).forEach(showOfferNotification);
      })
      .catch(function () {});
  }

  setInterval(pollNotificacoes, 10000);
  pollNotificacoes();

  var forms = document.querySelectorAll('form[action*="/especialista/atendimento/"]');
  if (!navigator.geolocation) {
    return;
  }
  var csrf = document.querySelector('input[name="csrf_token"]') ? document.querySelector('input[name="csrf_token"]').value : '';
  forms.forEach(function (f) {
    var m = f.action.match(/atendimento\/(?:aceitar|status|diagnostico|chegada|localizacao)\/(\d+)/);
    if (!m) return;
    var id = m[1];
    setInterval(function () {
      navigator.geolocation.getCurrentPosition(function (p) {
        fetch(f.action.split('/atendimento/')[0] + '/atendimento/localizacao/' + id, {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams({
            csrf_token: csrf,
            lat: p.coords.latitude,
            lng: p.coords.longitude,
            accuracy: p.coords.accuracy || 0
          })
        });
      }, function () {}, { enableHighAccuracy: true, maximumAge: 10000, timeout: 8000 });
    }, 15000);
  });
})();

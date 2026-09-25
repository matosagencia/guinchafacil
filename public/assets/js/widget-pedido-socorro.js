(function () {
  class WidgetPedidoSocorro extends HTMLElement {
    connectedCallback() {
      this.config = this.readConfig();
      this.render();
      this.bind();
    }
    readConfig() {
      try { return JSON.parse(this.dataset.config || '{}'); } catch (_) { return {}; }
    }
    render() {
      this.innerHTML = '<form class="gf-rescue-widget">' +
        '<label>Local<input name="endereco_origem" required autocomplete="street-address"></label>' +
        '<label>Problema<select name="tipo_problema"><option value="pneu">Pneu furado</option><option value="bateria">Bateria descarregada</option><option value="pane seca">Pane seca / gasolina</option><option value="motor ferveu">Motor ferveu / mecanica</option><option value="roda travada">Batida / roda travada</option></select></label>' +
        '<label>Destino<input name="endereco_destino" autocomplete="street-address"></label>' +
        '<input name="lat_origem" type="hidden"><input name="lng_origem" type="hidden"><input name="lat_destino" type="hidden"><input name="lng_destino" type="hidden"><input name="distancia_km" type="hidden" value="5">' +
        '<button type="button" data-action="locate">Usar GPS</button><button type="button" data-action="quote">Cotar</button><button type="submit">Confirmar</button><output aria-live="polite"></output></form>';
    }
    bind() {
      this.form = this.querySelector('form');
      this.output = this.querySelector('output');
      this.querySelector('[data-action="locate"]').addEventListener('click', () => this.locate());
      this.querySelector('[data-action="quote"]').addEventListener('click', () => this.quote());
      this.form.addEventListener('submit', (event) => { event.preventDefault(); this.create(); });
    }
    locate() {
      if (!navigator.geolocation) return this.say('GPS indisponivel neste navegador.');
      navigator.geolocation.getCurrentPosition((pos) => {
        this.form.lat_origem.value = pos.coords.latitude;
        this.form.lng_origem.value = pos.coords.longitude;
        this.say('Localizacao capturada.');
      }, () => this.say('Nao foi possivel obter o GPS.'));
    }
    async quote() {
      var response = await this.post((this.config.api || {}).quote || '/pedido/cotar', this.payload());
      if (!response.ok) return this.say((response.error && response.error.message) || 'Nao foi possivel cotar.');
      this.say('Total estimado: ' + this.money(response.data.total));
    }
    async create() {
      var data = this.payload();
      data.csrf_token = this.config.csrf || '';
      var response = await this.post((this.config.api || {}).create || '/pedido/criar', data);
      if (!response.ok) return this.say((response.error && response.error.message) || 'Nao foi possivel criar.');
      this.say('Pedido #' + response.data.pedido_id + ' criado.');
      this.dispatchEvent(new CustomEvent('pedido-criado', { detail: response.data, bubbles: true }));
    }
    payload() {
      var data = Object.fromEntries(new FormData(this.form).entries());
      data.distancia_km = parseFloat(data.distancia_km || '5');
      return data;
    }
    async post(url, data) {
      var res = await fetch(url, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }, body: JSON.stringify(data) });
      return res.json();
    }
    say(message) { this.output.textContent = message; }
    money(value) { return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(Number(value || 0)); }
  }
  if (!customElements.get('widget-pedido-socorro')) customElements.define('widget-pedido-socorro', WidgetPedidoSocorro);
})();

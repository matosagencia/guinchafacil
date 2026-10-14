/* Cadastro: máscara/validação CPF-CNPJ e ViaCEP. Não decide fluxo de negócio. */
(function () {
  'use strict';

  function digits(value) { return String(value || '').replace(/\D/g, ''); }

  function validCpf(value) {
    var d = digits(value);
    if (!/^\d{11}$/.test(d) || /^(\d)\1{10}$/.test(d)) return false;
    for (var len = 9; len <= 10; len++) {
      var sum = 0;
      for (var i = 0; i < len; i++) sum += Number(d.charAt(i)) * ((len + 1) - i);
      var check = (sum * 10) % 11;
      if (check === 10) check = 0;
      if (check !== Number(d.charAt(len))) return false;
    }
    return true;
  }

  function validCnpj(value) {
    var d = digits(value);
    if (!/^\d{14}$/.test(d) || /^(\d)\1{13}$/.test(d)) return false;
    var weights = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
    for (var digit = 12; digit <= 13; digit++) {
      var sum = 0;
      for (var i = 0; i < digit; i++) sum += Number(d.charAt(i)) * weights[i + (13 - digit)];
      var check = sum % 11 < 2 ? 0 : 11 - (sum % 11);
      if (check !== Number(d.charAt(digit))) return false;
    }
    return true;
  }

  function formatDocument(value) {
    var d = digits(value).slice(0, 14);
    if (d.length <= 11) {
      return d.replace(/^(\d{3})(\d)/, '$1.$2')
        .replace(/^(\d{3})\.(\d{3})(\d)/, '$1.$2.$3')
        .replace(/\.(\d{3})(\d)/, '.$1-$2');
    }
    return d.replace(/^(\d{2})(\d)/, '$1.$2')
      .replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3')
      .replace(/\.(\d{3})(\d)/, '.$1/$2')
      .replace(/(\d{4})(\d)/, '$1-$2');
  }

  function documentMessage(value) {
    var d = digits(value);
    if (d.length === 11) return validCpf(d) ? '' : 'CPF inválido.';
    if (d.length === 14) return validCnpj(d) ? '' : 'CNPJ inválido.';
    return 'Informe um CPF (11 dígitos) ou CNPJ (14 dígitos) válido.';
  }

  function setDocumentValidity(input) {
    var message = documentMessage(input.value);
    input.setCustomValidity(message);
    input.classList.toggle('is-invalid', Boolean(message) && digits(input.value).length >= 11);
    input.classList.toggle('is-valid', !message && digits(input.value).length > 0);
  }

  function bindDocument(input) {
    if (!input) return;
    input.setAttribute('inputmode', 'numeric');
    input.setAttribute('maxlength', '18');
    input.setAttribute('autocomplete', 'off');
    input.addEventListener('input', function (event) {
      // As telas legadas já registravam uma máscara exclusiva de CPF que
      // truncava o valor em 11 dígitos. Bloqueia somente esse listener legado.
      event.stopImmediatePropagation();
      input.value = formatDocument(input.value);
      setDocumentValidity(input);
    }, true);
    input.addEventListener('blur', function () { setDocumentValidity(input); });
  }

  function findField(names) {
    for (var i = 0; i < names.length; i++) {
      var field = document.getElementById(names[i]) || document.querySelector('[name="' + names[i] + '"]');
      if (field) return field;
    }
    return null;
  }

  function setStatus(status, text, error) {
    if (!status) return;
    status.textContent = text;
    status.classList.toggle('text-danger', Boolean(error));
    status.classList.toggle('text-success', !error && Boolean(text));
  }

  function bindCep(input) {
    if (!input) return;
    var inFlight = 0;
    input.setAttribute('inputmode', 'numeric');
    input.addEventListener('input', function (event) {
      // Centraliza a consulta; evita a dupla chamada ViaCEP das telas legadas.
      event.stopImmediatePropagation();
      var cep = digits(input.value).slice(0, 8);
      input.value = cep.length > 5 ? cep.slice(0, 5) + '-' + cep.slice(5) : cep;
      if (cep.length !== 8) return;

      var requestId = ++inFlight;
      var status = document.getElementById('cepStatus') || document.getElementById('g_cepStatus');
      setStatus(status, 'Buscando endereço…', false);
      fetch('https://viacep.com.br/ws/' + cep + '/json/', { headers: { Accept: 'application/json' } })
        .then(function (response) {
          if (!response.ok) throw new Error('HTTP ' + response.status);
          return response.json();
        })
        .then(function (address) {
          if (requestId !== inFlight) return;
          if (address.erro) throw new Error('CEP não encontrado');
          var mapping = {
            logradouro: ['logradouro', 'g_logradouro'],
            bairro: ['bairro', 'g_bairro'],
            localidade: ['cidade', 'g_cidade'],
            uf: ['estado', 'g_estado']
          };
          Object.keys(mapping).forEach(function (key) {
            var field = findField(mapping[key]);
            if (field && address[key]) field.value = address[key];
          });
          setStatus(status, 'Endereço preenchido. Informe o número.', false);
          var number = findField(['numero']);
          if (number) number.focus();
        })
        .catch(function (error) {
          if (requestId !== inFlight) return;
          setStatus(status, error.message === 'CEP não encontrado' ? error.message : 'Não foi possível consultar o CEP. Preencha o endereço manualmente.', true);
          window.console.warn('[registro][CEP] consulta falhou:', error.message);
        });
    }, true);
  }

  function bindForm(form) {
    form.addEventListener('submit', function (event) {
      var input = form.querySelector('input[name="cpf"]');
      if (!input || !input.required) return;
      setDocumentValidity(input);
      if (!input.checkValidity()) {
        event.preventDefault();
        input.reportValidity();
        window.console.warn('[registro][DOCUMENTO] CPF/CNPJ inválido; envio bloqueado.');
      }
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('input[name="cpf"]').forEach(bindDocument);
    document.querySelectorAll('input[name="cep"]').forEach(bindCep);
    document.querySelectorAll('form[action*="/registro/"]').forEach(bindForm);
  });
}());

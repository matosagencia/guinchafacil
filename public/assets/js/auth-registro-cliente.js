(function (window, document) {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var cpf = document.getElementById('cpf');
        var telefone = document.getElementById('telefone');
        var confirmarSenha = document.getElementById('confirmar_senha');
        var senha = document.getElementById('senha');
        var senhaFeedback = document.getElementById('senhaFeedback');
        var cep = document.getElementById('cep');
        var cepStatus = document.getElementById('cepStatus');

        if (window.MaskUtils) {
            window.MaskUtils.applyCPF(cpf);
            window.MaskUtils.applyPhone(telefone);
        }

        if (confirmarSenha && senha && senhaFeedback) {
            confirmarSenha.addEventListener('input', function () {
                var ok = confirmarSenha.value === senha.value;
                senhaFeedback.classList.toggle('d-none', ok || !confirmarSenha.value);
                confirmarSenha.classList.toggle('is-invalid', !ok && confirmarSenha.value !== '');
                confirmarSenha.classList.toggle('is-valid', ok && confirmarSenha.value !== '');
            });
        }

        if (cep) {
            cep.addEventListener('input', function () {
                var value = cep.value.replace(/\D/g, '').slice(0, 8);
                if (value.length > 5) value = value.slice(0, 5) + '-' + value.slice(5);
                cep.value = value;

                if (value.replace(/\D/g, '').length !== 8) {
                    return;
                }

                if (cepStatus) {
                    cepStatus.textContent = 'Buscando...';
                }

                window.AddressService.searchByCEP(value, function (data) {
                        if (data.erro) {
                            if (cepStatus) cepStatus.textContent = 'CEP não encontrado';
                            return;
                        }

                        var logradouro = document.getElementById('logradouro');
                        var bairro = document.getElementById('bairro');
                        var cidade = document.getElementById('cidade');
                        var estado = document.getElementById('estado');

                        if (logradouro) logradouro.value = data.logradouro || '';
                        if (bairro) bairro.value = data.bairro || '';
                        if (cidade) cidade.value = data.localidade || '';
                        if (estado) {
                            for (var i = 0; i < estado.options.length; i++) {
                                if (estado.options[i].value === data.uf) {
                                    estado.selectedIndex = i;
                                    break;
                                }
                            }
                        }

                        if (cepStatus) cepStatus.textContent = 'Endereço encontrado';
                });
            });
        }
    });
})(window, document);

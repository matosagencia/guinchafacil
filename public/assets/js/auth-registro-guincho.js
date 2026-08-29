(function (window, document) {
    'use strict';

    function previewUpload(input, previewId) {
        var file = input.files && input.files[0];
        var preview = document.getElementById(previewId);
        if (!file || !preview) {
            return;
        }

        if (file.type.indexOf('image/') === 0) {
            var reader = new FileReader();
            reader.onload = function (event) {
                preview.innerHTML = '<img src="' + event.target.result + '" class="upload-preview"><div style="color:#2fb34a;font-size:.8rem;margin-top:.4rem"><i class="fas fa-check me-1"></i>' + file.name + '</div>';
            };
            reader.readAsDataURL(file);
            return;
        }

        preview.innerHTML = '<i class="fas fa-file-pdf fa-2x" style="color:#2fb34a"></i><div style="color:#2fb34a;font-size:.8rem;margin-top:.4rem">' + file.name + '</div>';
    }

    document.addEventListener('DOMContentLoaded', function () {
        var form = document.getElementById('formGuincho');

        window.irStep = function (step) {
            if (step === 2 && !validateStep1()) return;
            if (step === 3 && !validateStep2()) return;

            [1, 2, 3].forEach(function (current) {
                var section = document.getElementById('step' + current);
                var dot = document.getElementById('dot' + current);
                if (section) section.style.display = current === step ? 'block' : 'none';
                if (dot) dot.classList.toggle('active', current <= step);
            });
            window.scrollTo({ top: 0, behavior: 'smooth' });
        };

        function ofereceReboque() {
            var c = document.getElementById('chkReboque');
            return !!(c && c.checked);
        }
        function servicosSelecionados() {
            return document.querySelectorAll('.servico-chk:checked').length;
        }

        function validateStep1() {
            if (servicosSelecionados() < 1) {
                var av = document.getElementById('servicoAviso');
                if (av) av.classList.remove('d-none');
                window.alert('Selecione ao menos um serviço que você oferece.');
                return false;
            }
            var nome = document.getElementById('f_nome');
            var email = document.getElementById('f_email');
            var senha = document.getElementById('f_senha');
            var conf = document.getElementById('f_conf');
            var cpf = document.getElementById('f_cpf');
            var tel = document.getElementById('f_tel');

            if (!nome || nome.value.trim().length < 3) { window.alert('Informe seu nome completo.'); return false; }
            if (!email || email.value.indexOf('@') < 0) { window.alert('Informe um e-mail válido.'); return false; }
            if (!tel || tel.value.replace(/\D/g, '').length < 10) { window.alert('Informe um telefone válido.'); return false; }
            if (!cpf || cpf.value.replace(/\D/g, '').length < 11) { window.alert('Informe um CPF válido.'); return false; }
            if (!senha || senha.value.length < 8) { window.alert('Senha mínima de 8 caracteres.'); return false; }
            if (!conf || senha.value !== conf.value) { window.alert('As senhas não conferem.'); return false; }
            return true;
        }

        function validateStep2() {
            return true;
            /*
            // Especialista (não marcou reboque) não informa placa/CNH — pula.
            if (!ofereceReboque()) return true;
            var placa = document.getElementById('f_placa');
            var cnh = document.getElementById('f_cnh');
            if (!placa || placa.value.trim().length < 7) { window.alert('Informe a placa do veículo.'); return false; }
            if (!cnh || cnh.value.trim().length < 9) { window.alert('Informe o número da CNH.'); return false; }
            return true; */
        }

        var cpf = document.getElementById('f_cpf');
        if (window.MaskUtils) {
            window.MaskUtils.applyCPF(cpf);
        }

        var tel = document.getElementById('f_tel');
        if (window.MaskUtils) {
            window.MaskUtils.applyPhone(tel);
        }

        var placa = document.getElementById('f_placa');
        if (placa) {
            placa.addEventListener('input', function () {
                placa.value = placa.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
            });
        }

        var ufPlaca = document.querySelector('input[name="uf_placa"]');
        if (ufPlaca) {
            ufPlaca.addEventListener('input', function () {
                ufPlaca.value = ufPlaca.value.toUpperCase().replace(/[^A-Z]/g, '').slice(0, 2);
            });
        }

        var senhaConfirm = document.getElementById('f_conf');
        var senha = document.getElementById('f_senha');
        var senhaFb = document.getElementById('senhaFb');
        if (senhaConfirm && senha && senhaFb) {
            senhaConfirm.addEventListener('input', function () {
                var ok = senhaConfirm.value === senha.value;
                senhaFb.classList.toggle('d-none', ok || !senhaConfirm.value);
            });
        }

        document.querySelectorAll('[data-go-step]').forEach(function (button) {
            button.addEventListener('click', function () {
                var step = parseInt(button.getAttribute('data-go-step') || '1', 10);
                window.irStep(step);
            });
        });

        // Aviso na etapa de guincho quando o prestador não marcou reboque, e
        // some o aviso de "selecione um serviço" assim que ele marca algo.
        function atualizarAvisosServico() {
            var towAviso = document.getElementById('towOnlyAviso');
            if (towAviso) towAviso.classList.toggle('d-none', ofereceReboque());
            if (servicosSelecionados() >= 1) {
                var av = document.getElementById('servicoAviso');
                if (av) av.classList.add('d-none');
            }
        }
        document.querySelectorAll('.servico-chk').forEach(function (chk) {
            chk.addEventListener('change', atualizarAvisosServico);
        });
        atualizarAvisosServico();

        var raioRange = document.getElementById('raioRange');
        var raioVal = document.getElementById('raioVal');
        if (raioRange && raioVal) {
            raioRange.addEventListener('input', function () {
                raioVal.textContent = raioRange.value;
            });
        }

        document.querySelectorAll('[data-upload-trigger]').forEach(function (zone) {
            zone.addEventListener('click', function () {
                var target = zone.getAttribute('data-upload-trigger');
                var input = document.getElementById(target);
                if (input) input.click();
            });
        });

        document.querySelectorAll('input[type="file"][data-preview-target]').forEach(function (input) {
            input.addEventListener('change', function () {
                previewUpload(input, input.getAttribute('data-preview-target'));
            });
        });

        var cep = document.getElementById('g_cep');
        if (cep) {
            cep.addEventListener('input', function () {
                var value = cep.value.replace(/\D/g, '').slice(0, 8);
                if (value.length > 5) value = value.slice(0, 5) + '-' + value.slice(5);
                cep.value = value;
                if (value.replace(/\D/g, '').length !== 8) return;

                window.AddressService.searchByCEP(value, function (data) {
                        if (data.erro) return;
                        var logradouro = document.getElementById('g_logradouro');
                        var bairro = document.getElementById('g_bairro');
                        var cidade = document.getElementById('g_cidade');
                        var estado = document.getElementById('g_estado');
                        if (logradouro) logradouro.value = data.logradouro || '';
                        if (bairro) bairro.value = data.bairro || '';
                        if (cidade) cidade.value = data.localidade || '';
                        if (estado) estado.value = data.uf || '';
                });
            });
        }

        if (form) {
            form.addEventListener('submit', function (event) {
                if (!validateStep1() || !validateStep2()) {
                    event.preventDefault();
                }
            });
        }
    });
})(window, document);

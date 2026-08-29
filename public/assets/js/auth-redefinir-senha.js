(function (window, document) {
    'use strict';

    function togglePassword(inputId) {
        var input = document.getElementById(inputId);
        if (!input) {
            return;
        }
        input.type = input.type === 'password' ? 'text' : 'password';
    }

    function evaluateStrength(password) {
        var score = 0;
        if (password.length >= 8) score++;
        if (password.length >= 12) score++;
        if (/[A-Z]/.test(password)) score++;
        if (/[0-9]/.test(password)) score++;
        if (/[^A-Za-z0-9]/.test(password)) score++;
        return score;
    }

    document.addEventListener('DOMContentLoaded', function () {
        var senhaInput = document.getElementById('senha');
        var confirmInput = document.getElementById('confirmacao');
        var form = document.getElementById('formRedefinir');
        var forcaBar = document.getElementById('forca-bar');
        var forcaLabel = document.getElementById('forca-label');
        var matchMsg = document.getElementById('match-msg');
        var btnSalvar = document.getElementById('btnSalvar');
        var colorMap = ['', '#dc2626', '#f59e0b', '#eab308', '#22c55e', '#16a34a'];
        var labelMap = ['', 'Muito fraca', 'Fraca', 'Média', 'Forte', 'Muito forte'];

        document.querySelectorAll('[data-toggle-password]').forEach(function (button) {
            button.addEventListener('click', function () {
                togglePassword(button.getAttribute('data-toggle-password'));
            });
        });

        function verifyMatch() {
            if (!confirmInput || !senhaInput || !matchMsg || !btnSalvar) {
                return;
            }
            if (!confirmInput.value) {
                matchMsg.textContent = '';
                btnSalvar.disabled = false;
                return;
            }
            if (senhaInput.value === confirmInput.value) {
                matchMsg.textContent = 'Senhas coincidem';
                matchMsg.style.color = '#16a34a';
                btnSalvar.disabled = false;
            } else {
                matchMsg.textContent = 'Senhas não coincidem';
                matchMsg.style.color = '#dc2626';
                btnSalvar.disabled = true;
            }
        }

        if (senhaInput) {
            senhaInput.addEventListener('input', function () {
                var score = evaluateStrength(senhaInput.value);
                var pct = Math.min(score * 20, 100);
                if (forcaBar) {
                    forcaBar.style.width = pct + '%';
                    forcaBar.style.backgroundColor = colorMap[score] || '#dc2626';
                }
                if (forcaLabel) {
                    forcaLabel.textContent = labelMap[score] || '';
                    forcaLabel.style.color = colorMap[score] || '#dc2626';
                }
                verifyMatch();
            });
        }

        if (confirmInput) {
            confirmInput.addEventListener('input', verifyMatch);
        }

        if (form) {
            form.addEventListener('submit', function (event) {
                if (senhaInput && confirmInput && senhaInput.value !== confirmInput.value) {
                    event.preventDefault();
                    if (matchMsg) {
                        matchMsg.textContent = 'Senhas não coincidem';
                        matchMsg.style.color = '#dc2626';
                    }
                }
            });
        }
    });
})(window, document);

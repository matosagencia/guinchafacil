(function () {
    'use strict';

    function boot() {
        var shell = document.querySelector('[data-admin-global-search]');
        if (!shell) return;

        // A Central já possui seu próprio campo, exatamente acima das três
        // colunas. Evita duplicar a busca nessa tela.
        if (document.querySelector('.shell-ops, .admin-page-toolbar')) {
            shell.hidden = true;
            return;
        }

        var existingInput = document.querySelector('.admin-dashboard-topbar input');
        var input = existingInput || shell.querySelector('input');
        if (existingInput) shell.hidden = true;
        var main = document.querySelector('main.main-content');
        if (!input || !main) return;

        function candidates() {
            var selectors = [
                'tbody tr',
                '.list-group-item',
                '.admin-stat',
                '.stat-grid__item',
                '.main-content > .row > [class*="col-"]',
                '.main-content > .card'
            ];
            var seen = [];
            selectors.forEach(function (selector) {
                main.querySelectorAll(selector).forEach(function (element) {
                    if (!element.closest('.admin-global-search') && seen.indexOf(element) < 0) seen.push(element);
                });
            });
            return seen;
        }

        input.addEventListener('input', function () {
            var query = input.value.trim().toLocaleLowerCase();
            candidates().forEach(function (element) {
                // Filtra somente unidades que representam itens. A linha de
                // detalhe de um grupo acompanha a linha-resumo e não some
                // isoladamente.
                if (element.classList.contains('admin-alert-details') || element.classList.contains('cap-group-details')) return;
                var match = !query || element.textContent.toLocaleLowerCase().indexOf(query) >= 0;
                element.hidden = !match;
                var sibling = element.nextElementSibling;
                if (sibling && (sibling.classList.contains('admin-alert-details') || sibling.classList.contains('cap-group-details')) && !match) sibling.hidden = true;
            });
        });
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
    else boot();
})();

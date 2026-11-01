/* Comportamento de navegação do shell Lovable. Não altera regras de negócio. */
(function () {
  'use strict';
  var root = document.querySelector('.bo[data-gf-profile]');
  if (!root) return;
  var collapse = root.querySelector('[data-bo-collapse]');
  var theme = root.querySelector('[data-bo-theme]');
  if (collapse) {
    collapse.addEventListener('click', function () {
      var collapsed = root.classList.toggle('bo-collapsed');
      collapse.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
      collapse.setAttribute('aria-label', collapsed ? 'Expandir menu lateral' : 'Recolher menu lateral');
    });
  }
  if (theme && !theme.disabled && root.dataset.gfProfile === 'admin') {
    theme.addEventListener('change', function () {
      ['operator', 'driver', 'client', 'workshop'].forEach(function (token) {
        root.classList.remove('bo-' + token);
      });
      var token = theme.value;
      if (['operator', 'driver', 'client', 'workshop'].indexOf(token) < 0) token = 'operator';
      root.classList.add('bo-' + token);
      // Preview SOMENTE visual. O perfil autenticado e as permissões não mudam.
    });
  }
})();

(function () {
    'use strict';
    function bindOption(selector) {
        document.querySelectorAll(selector).forEach(function (box) {
            var control = box.querySelector('input[type="radio"], input[type="checkbox"]');
            if (!control) return;
            box.addEventListener('click', function (event) {
                if (event.target === control || event.target.closest('label')) return;
                control.checked = control.type === 'radio' ? true : !control.checked;
                control.dispatchEvent(new Event('change', { bubbles: true }));
            });
            box.setAttribute('role', control.type === 'radio' ? 'radio' : 'switch');
            box.addEventListener('keydown', function (event) {
                if (event.key !== 'Enter' && event.key !== ' ') return;
                event.preventDefault();
                control.click();
            });
            box.tabIndex = 0;
        });
    }
    function init() {
        bindOption('.config-mode-option');
        bindOption('.config-payment-option');
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
}());

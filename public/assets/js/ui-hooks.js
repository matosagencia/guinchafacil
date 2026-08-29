(function (window, document) {
    'use strict';

    function bindLogoutConfirm() {
        document.querySelectorAll('[data-confirm-logout]').forEach(function (link) {
            link.addEventListener('click', function (event) {
                if (!window.confirm('Sair da conta?')) {
                    event.preventDefault();
                }
            });
        });
    }

    function bindSessionModal() {
        var button = document.querySelector('[data-session-login]');
        if (!button || !window.SessionManager) {
            return;
        }
        button.addEventListener('click', function () {
            window.SessionManager.redirectToLogin();
        });
    }

    function bindConfirmActions() {
        document.addEventListener('click', function (event) {
            var trigger = event.target.closest('[data-confirm-message]');
            if (!trigger) {
                return;
            }

            if (!window.confirm(trigger.getAttribute('data-confirm-message') || 'Confirmar ação?')) {
                event.preventDefault();
                event.stopPropagation();
            }
        });
    }

    function bindSubmitTargets() {
        document.addEventListener('click', function (event) {
            var trigger = event.target.closest('[data-submit-form]');
            if (!trigger) {
                return;
            }

            var formId = trigger.getAttribute('data-submit-form');
            if (!formId) {
                return;
            }

            var form = document.getElementById(formId);
            if (!form) {
                return;
            }

            event.preventDefault();
            form.submit();
        });
    }

    function bindDismissParent() {
        document.addEventListener('click', function (event) {
            var trigger = event.target.closest('[data-dismiss-parent]');
            if (!trigger) {
                return;
            }

            var parentSelector = trigger.getAttribute('data-dismiss-parent');
            if (!parentSelector) {
                return;
            }

            var parent = trigger.closest(parentSelector);
            if (!parent) {
                return;
            }

            event.preventDefault();
            parent.remove();
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        bindLogoutConfirm();
        bindSessionModal();
        bindConfirmActions();
        bindSubmitTargets();
        bindDismissParent();
    });
})(window, document);

 (function () {
    'use strict';

    function money(value) {
        return new Intl.NumberFormat('pt-BR', {
            style: 'currency',
            currency: 'BRL'
        }).format(value);
    }

    function setupSimulator(simulator) {
        var calls = simulator.querySelector('input[type="range"]');
        var ticket = simulator.querySelector(
            'input[type="number"], input[type="range"]:not(:first-of-type)'
        );
        var labels = simulator.querySelectorAll('output');
        var values = simulator.querySelectorAll('.gf-sim-result b');

        if (!calls || !ticket || calls === ticket || values.length < 3) {
            return;
        }

        ticket.type = 'range';
        ticket.min = '100';
        ticket.max = simulator.id === 'gfSimulatorOficina' ? '2000' : '600';
        ticket.step = '10';
        ticket.classList.add('gf-ticket-slider');

        var pix = document.createElement('p');
        pix.className = 'gf-sim-pix';
        pix.textContent = '✓ Repasse via PIX sem mensalidade';
        simulator.appendChild(pix);

        function update() {
            var count = Number(calls.value) || 0;
            var amount = Number(ticket.value) || 0;
            var netPerService = amount * 0.764;

            if (labels[0]) {
                labels[0].textContent = count;
            }

            if (labels[1]) {
                labels[1].textContent = money(amount);
            }

            values[0].textContent = money(netPerService);
            values[1].textContent = money(netPerService * count);
            values[2].textContent = money(netPerService * count * 4);
        }

        calls.addEventListener('input', update);
        ticket.addEventListener('input', update);
        update();
    }

    function setupFaq(accordion) {
        accordion.querySelectorAll('article').forEach(function (item, index) {
            var title = item.querySelector('h3');
            var answer = item.querySelector('p');

            if (!title || !answer) {
                return;
            }

            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'gf-faq-question';
            button.setAttribute('aria-expanded', 'false');
            button.innerHTML =
                '<span>' + title.textContent + '</span>' +
                '<b aria-hidden="true">+</b>';

            answer.hidden = true;
            title.replaceWith(button);

            button.addEventListener('click', function () {
                var expanded = button.getAttribute('aria-expanded') === 'true';

                button.setAttribute('aria-expanded', String(!expanded));
                button.querySelector('b').textContent = expanded ? '+' : '−';
                answer.hidden = expanded;
            });

            if (index === 0) {
                button.click();
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.gf-simulator').forEach(setupSimulator);
        document.querySelectorAll('.gf-faq-accordion').forEach(setupFaq);
    });
}());
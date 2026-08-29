(function () {
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('[data-communications]').forEach((wrap) => {
            const cards = Array.from(wrap.querySelectorAll('.communication-card'));
            if (cards.length <= 1) return;
            let idx = 0;
            cards.forEach((card, i) => { card.style.display = i === 0 ? '' : 'none'; });
            setInterval(() => {
                cards[idx].style.display = 'none';
                idx = (idx + 1) % cards.length;
                cards[idx].style.display = '';
            }, 8000);
        });
    });
})();

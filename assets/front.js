/* PréCommande — calcul en direct du nombre d'articles et du total estimé. */
(function () {
    'use strict';

    var currency = (window.PreCommande && window.PreCommande.currency) || 'EUR';
    var formatter = new Intl.NumberFormat(document.documentElement.lang || 'fr-FR', { style: 'currency', currency: currency });

    document.querySelectorAll('.pc-form').forEach(function (form) {
        var countEl = form.querySelector('[data-pc-count]');
        var totalEl = form.querySelector('[data-pc-total]');

        function update() {
            var count = 0;
            var total = 0;
            form.querySelectorAll('input[data-price]').forEach(function (input) {
                var qty = Math.max(0, parseInt(input.value, 10) || 0);
                count += qty;
                total += qty * (parseFloat(input.dataset.price) || 0);
            });
            if (countEl) countEl.textContent = count;
            if (totalEl) totalEl.textContent = formatter.format(total);
        }

        form.addEventListener('input', update);
        update();
    });
})();

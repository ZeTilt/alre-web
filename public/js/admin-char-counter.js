/**
 * Live character counter for inputs/textareas with data-char-min / data-char-max attributes.
 * Shows "X / 120-155" with color coding: green = in range, orange = close, red = out of range.
 */
(function () {
    function initCounters() {
        document.querySelectorAll('textarea[data-char-min], textarea[data-char-max], input[data-char-min], input[data-char-max]').forEach(function (field) {
            if (field.dataset.charCounterInit) return;
            field.dataset.charCounterInit = '1';

            var min = parseInt(field.dataset.charMin) || 0;
            var max = parseInt(field.dataset.charMax) || 9999;

            var counter = document.createElement('div');
            counter.style.cssText = 'font-size: 0.75rem; margin-top: 0.25rem; font-weight: 600;';
            field.parentNode.insertBefore(counter, field.nextSibling);

            function update() {
                var len = field.value.length;
                var label = len + ' / ' + min + '-' + max;

                if (len === 0) {
                    counter.style.color = '#9ca3af';
                    counter.textContent = '0 car. (vide)';
                } else if (len >= min && len <= max) {
                    counter.style.color = '#10b981';
                    counter.textContent = label + ' \u2714';
                } else if (len < min && len >= min * 0.8) {
                    counter.style.color = '#f59e0b';
                    counter.textContent = label + ' (un peu court)';
                } else if (len > max && len <= max * 1.15) {
                    counter.style.color = '#f59e0b';
                    counter.textContent = label + ' (un peu long)';
                } else if (len < min) {
                    counter.style.color = '#ef4444';
                    counter.textContent = label + ' (trop court)';
                } else {
                    counter.style.color = '#ef4444';
                    counter.textContent = label + ' (trop long)';
                }
            }

            field.addEventListener('input', update);
            update();
        });
    }

    function startObserver() {
        var observer = new MutationObserver(function () {
            initCounters();
        });
        observer.observe(document.body, { childList: true, subtree: true });
    }

    // Run on page load + re-run when EasyAdmin loads new content (AJAX navigation)
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            initCounters();
            startObserver();
        });
    } else {
        initCounters();
        startObserver();
    }
})();

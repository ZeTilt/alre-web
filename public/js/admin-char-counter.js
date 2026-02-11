/**
 * Live character counter for textareas with data-char-min / data-char-max attributes.
 * Shows "X / 120-155" with color coding: green = in range, orange = close, red = out of range.
 */
(function () {
    function initCounters() {
        document.querySelectorAll('textarea[data-char-min], textarea[data-char-max]').forEach(function (textarea) {
            if (textarea.dataset.charCounterInit) return;
            textarea.dataset.charCounterInit = '1';

            var min = parseInt(textarea.dataset.charMin) || 0;
            var max = parseInt(textarea.dataset.charMax) || 9999;

            var counter = document.createElement('div');
            counter.style.cssText = 'font-size: 0.75rem; margin-top: 0.25rem; font-weight: 600;';
            textarea.parentNode.insertBefore(counter, textarea.nextSibling);

            function update() {
                var len = textarea.value.length;
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

            textarea.addEventListener('input', update);
            update();
        });
    }

    // Run on page load
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initCounters);
    } else {
        initCounters();
    }

    // Re-run when EasyAdmin loads new content (AJAX navigation)
    var observer = new MutationObserver(function () {
        initCounters();
    });
    observer.observe(document.body, { childList: true, subtree: true });
})();

/**
 * Adds a server-side search bar above EasyAdmin CRUD tables.
 * Searches across ALL records (not just the current page).
 * Uses EasyAdmin's built-in search with debounce for smooth UX.
 */
(function () {
    var debounceTimer = null;

    function init() {
        var table = document.querySelector('table.datagrid');
        if (!table) return;
        if (document.querySelector('.admin-table-filter')) return;

        // Read current search query from URL
        var params = new URLSearchParams(window.location.search);
        var currentQuery = params.get('query') || '';

        var wrapper = document.createElement('div');
        wrapper.className = 'admin-table-filter';
        wrapper.style.cssText = 'margin-bottom: 1rem; position: relative;';

        var input = document.createElement('input');
        input.type = 'text';
        input.value = currentQuery;
        input.placeholder = 'Rechercher dans tous les mots-cl\u00e9s\u2026';
        input.style.cssText = 'width: 100%; padding: 0.6rem 1rem 0.6rem 2.4rem; border: 1px solid #dee2e6; border-radius: 8px; font-size: 0.9rem; outline: none; transition: border-color 0.2s;';
        input.addEventListener('focus', function () { this.style.borderColor = '#6366f1'; });
        input.addEventListener('blur', function () { this.style.borderColor = '#dee2e6'; });

        var icon = document.createElement('i');
        icon.className = 'fas fa-search';
        icon.style.cssText = 'position: absolute; left: 0.8rem; top: 50%; transform: translateY(-50%); color: #9ca3af; font-size: 0.85rem; pointer-events: none;';

        var hint = document.createElement('span');
        hint.style.cssText = 'position: absolute; right: 0.8rem; top: 50%; transform: translateY(-50%); color: #9ca3af; font-size: 0.75rem;';

        wrapper.appendChild(icon);
        wrapper.appendChild(input);
        wrapper.appendChild(hint);

        var panel = table.closest('.content-panel') || table.parentNode;
        panel.parentNode.insertBefore(wrapper, panel);

        // Hide EasyAdmin's default search form (redundant)
        var eaSearch = document.querySelector('.form-action-search');
        if (eaSearch) eaSearch.style.display = 'none';

        input.addEventListener('input', function () {
            var query = this.value.trim();
            hint.textContent = '';

            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function () {
                var url = new URL(window.location.href);
                // Reset to page 1 when searching
                url.searchParams.delete('page');
                if (query) {
                    url.searchParams.set('query', query);
                } else {
                    url.searchParams.delete('query');
                }
                window.location.href = url.toString();
            }, 400);
        });

        // Clear button (show only when there's a query)
        if (currentQuery) {
            var clear = document.createElement('span');
            clear.innerHTML = '&times;';
            clear.style.cssText = 'position: absolute; right: 0.8rem; top: 50%; transform: translateY(-50%); color: #9ca3af; font-size: 1.2rem; cursor: pointer; line-height: 1;';
            clear.title = 'Effacer la recherche';
            clear.addEventListener('click', function () {
                input.value = '';
                var url = new URL(window.location.href);
                url.searchParams.delete('query');
                url.searchParams.delete('page');
                window.location.href = url.toString();
            });
            wrapper.appendChild(clear);
            hint.remove();
        }

        // Focus the input and place cursor at end
        if (currentQuery) {
            input.focus();
            input.setSelectionRange(input.value.length, input.value.length);
        }
    }

    function startObserver() {
        var target = document.body || document.documentElement;
        if (!target) return;
        var observer = new MutationObserver(function () { init(); });
        observer.observe(target, { childList: true, subtree: true });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            init();
            startObserver();
        });
    } else {
        init();
        startObserver();
    }
})();

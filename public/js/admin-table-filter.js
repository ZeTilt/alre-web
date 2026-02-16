/**
 * Adds a live text filter input above EasyAdmin CRUD tables.
 * Filters rows instantly as the user types, matching against all visible text.
 */
(function () {
    function init() {
        var table = document.querySelector('table.datagrid');
        if (!table) return;
        // Avoid double init
        if (document.querySelector('.admin-table-filter')) return;

        var wrapper = document.createElement('div');
        wrapper.className = 'admin-table-filter';
        wrapper.style.cssText = 'margin-bottom: 1rem; position: relative;';

        var input = document.createElement('input');
        input.type = 'text';
        input.placeholder = 'Filtrer dans cette page\u2026';
        input.style.cssText = 'width: 100%; padding: 0.6rem 1rem 0.6rem 2.4rem; border: 1px solid #dee2e6; border-radius: 8px; font-size: 0.9rem; outline: none; transition: border-color 0.2s;';
        input.addEventListener('focus', function () { this.style.borderColor = '#6366f1'; });
        input.addEventListener('blur', function () { this.style.borderColor = '#dee2e6'; });

        var icon = document.createElement('i');
        icon.className = 'fas fa-search';
        icon.style.cssText = 'position: absolute; left: 0.8rem; top: 50%; transform: translateY(-50%); color: #9ca3af; font-size: 0.85rem; pointer-events: none;';

        var count = document.createElement('span');
        count.style.cssText = 'position: absolute; right: 0.8rem; top: 50%; transform: translateY(-50%); color: #9ca3af; font-size: 0.8rem;';

        wrapper.appendChild(icon);
        wrapper.appendChild(input);
        wrapper.appendChild(count);

        // Insert before the table's parent content-panel
        var panel = table.closest('.content-panel') || table.parentNode;
        panel.parentNode.insertBefore(wrapper, panel);

        var rows = table.querySelectorAll('tbody tr[data-id]');
        var totalRows = rows.length;

        input.addEventListener('input', function () {
            var filter = this.value.toLowerCase().trim();
            if (!filter) {
                rows.forEach(function (row) { row.style.display = ''; });
                count.textContent = '';
                return;
            }

            var visible = 0;
            rows.forEach(function (row) {
                // Search across all data cells (skip checkbox + actions columns)
                var cells = row.querySelectorAll('td[data-label]');
                var text = '';
                cells.forEach(function (td) { text += ' ' + td.textContent; });

                if (text.toLowerCase().indexOf(filter) !== -1) {
                    row.style.display = '';
                    visible++;
                } else {
                    row.style.display = 'none';
                }
            });

            count.textContent = visible + '/' + totalRows;
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Re-init after EasyAdmin AJAX reloads (pagination, sort, etc.)
    var observer = new MutationObserver(function () { init(); });
    observer.observe(document.body, { childList: true, subtree: true });
})();

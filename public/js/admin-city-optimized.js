/**
 * Handles "Optimisé" button clicks on the City CRUD list.
 * Sends a POST to mark all SEO keywords containing the city name as optimized.
 */
(function () {
    function initButtons() {
        document.querySelectorAll('.city-mark-optimized:not([data-init])').forEach(function (btn) {
            btn.dataset.init = '1';
            btn.addEventListener('click', function () {
                var cityId = btn.dataset.cityId;
                var token = btn.dataset.token;
                var resultSpan = document.querySelector('.city-optimized-result[data-city-id="' + cityId + '"]');

                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ...';

                fetch('/saeiblauhjc/city/' + cityId + '/mark-optimized', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: '_token=' + encodeURIComponent(token)
                })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.success) {
                        btn.innerHTML = '<i class="fas fa-check"></i> Fait';
                        btn.classList.remove('btn-outline-success');
                        btn.classList.add('btn-success');
                        resultSpan.style.color = '#10b981';
                        resultSpan.textContent = data.count + ' mot(s)-clé(s) marqué(s) le ' + data.date;

                        // Mettre à jour la colonne "Dernière optimisation"
                        var row = btn.closest('tr');
                        if (row) {
                            var cells = row.querySelectorAll('td');
                            cells.forEach(function (td) {
                                if (td.textContent.trim() === 'jamais') {
                                    td.innerHTML = data.date;
                                }
                            });
                        }
                    } else {
                        btn.innerHTML = '<i class="fas fa-times"></i> Erreur';
                        btn.classList.remove('btn-outline-success');
                        btn.classList.add('btn-outline-danger');
                    }
                })
                .catch(function () {
                    btn.innerHTML = '<i class="fas fa-times"></i> Erreur';
                    btn.classList.remove('btn-outline-success');
                    btn.classList.add('btn-outline-danger');
                });
            });
        });
    }

    // Department buttons
    function initDeptButtons() {
        document.querySelectorAll('.dept-mark-optimized:not([data-init])').forEach(function (btn) {
            btn.dataset.init = '1';
            btn.addEventListener('click', function () {
                var deptId = btn.dataset.deptId;
                var token = btn.dataset.token;
                var resultSpan = document.querySelector('.dept-optimized-result[data-dept-id="' + deptId + '"]');

                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> ...';

                fetch('/saeiblauhjc/department/' + deptId + '/mark-optimized', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: '_token=' + encodeURIComponent(token)
                })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.success) {
                        btn.innerHTML = '<i class="fas fa-check"></i> Fait';
                        btn.classList.remove('btn-outline-success');
                        btn.classList.add('btn-success');
                        resultSpan.style.color = '#10b981';
                        resultSpan.textContent = data.count + ' mot(s)-clé(s) marqué(s) le ' + data.date;

                        var row = btn.closest('tr');
                        if (row) {
                            var cells = row.querySelectorAll('td');
                            cells.forEach(function (td) {
                                if (td.textContent.trim() === 'jamais') {
                                    td.innerHTML = data.date;
                                }
                            });
                        }
                    } else {
                        btn.innerHTML = '<i class="fas fa-times"></i> Erreur';
                        btn.classList.remove('btn-outline-success');
                        btn.classList.add('btn-outline-danger');
                    }
                })
                .catch(function () {
                    btn.innerHTML = '<i class="fas fa-times"></i> Erreur';
                    btn.classList.remove('btn-outline-success');
                    btn.classList.add('btn-outline-danger');
                });
            });
        });
    }

    function initAll() {
        initButtons();
        initDeptButtons();
    }

    function startObserver() {
        if (!document.body) return;
        var observer = new MutationObserver(function () { initAll(); });
        observer.observe(document.body, { childList: true, subtree: true });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            initAll();
            startObserver();
        });
    } else {
        initAll();
        startObserver();
    }
})();

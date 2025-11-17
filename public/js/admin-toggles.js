/**
 * Gestion des toggles en temps réel dans EasyAdmin
 */
document.addEventListener('DOMContentLoaded', function() {
    // Sélectionner tous les toggles pour les champs boolean
    const toggles = document.querySelectorAll('.form-switch input[type="checkbox"]');

    toggles.forEach(toggle => {
        toggle.addEventListener('change', function(e) {
            // Récupérer la ligne du tableau
            const row = this.closest('tr');
            if (!row) return;

            // Récupérer l'ID de l'entité depuis le lien d'édition
            const editLink = row.querySelector('a[href*="crudAction=edit"]');
            if (!editLink) return;

            const url = new URL(editLink.href);
            const entityId = url.searchParams.get('entityId');
            const controller = url.searchParams.get('crudControllerFqcn');

            if (!entityId || !controller) return;

            // Récupérer le nom du champ depuis l'attribut name ou id
            const fieldName = this.name || this.id;
            const fieldMatch = fieldName.match(/\[(\w+)\]/);
            const field = fieldMatch ? fieldMatch[1] : null;

            if (!field) return;

            const newValue = this.checked;

            // Désactiver temporairement le toggle
            this.disabled = true;

            // Construire l'URL de mise à jour
            const updateUrl = new URL(window.location.origin + window.location.pathname);
            updateUrl.searchParams.set('crudAction', 'toggleField');
            updateUrl.searchParams.set('crudControllerFqcn', controller);
            updateUrl.searchParams.set('entityId', entityId);
            updateUrl.searchParams.set('field', field);
            updateUrl.searchParams.set('value', newValue ? '1' : '0');

            // Envoyer la requête AJAX
            fetch(updateUrl, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Succès - réactiver le toggle
                    this.disabled = false;
                } else {
                    // Erreur - remettre l'ancienne valeur
                    this.checked = !newValue;
                    this.disabled = false;
                    alert('Erreur lors de la mise à jour: ' + (data.error || 'Erreur inconnue'));
                }
            })
            .catch(error => {
                // Erreur - remettre l'ancienne valeur
                this.checked = !newValue;
                this.disabled = false;
                console.error('Erreur:', error);
                alert('Erreur lors de la mise à jour');
            });
        });
    });
});

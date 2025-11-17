/**
 * Gestion dynamique des domaines de partenaires dans les formulaires de projets
 */
document.addEventListener('DOMContentLoaded', function() {
    // Fonction pour gérer un select de partenaire
    function handlePartnerSelect(selectElement) {
        const collectionItem = selectElement.closest('.field-collection-item') || selectElement.closest('form');
        if (!collectionItem) return;

        selectElement.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const domains = selectedOption && selectedOption.dataset.domains
                ? JSON.parse(selectedOption.dataset.domains)
                : [];

            // Trouver le wrapper du champ selectedDomains dans le même collection item
            let domainsWrapper = collectionItem.querySelector('.domains-field-wrapper');
            if (!domainsWrapper) {
                // Essayer avec l'ID
                domainsWrapper = collectionItem.querySelector('[id*="selectedDomains"]');
            }
            if (!domainsWrapper) {
                // Dernière tentative : chercher par label
                domainsWrapper = Array.from(collectionItem.querySelectorAll('.form-group')).find(el => {
                    const label = el.querySelector('label');
                    return label && label.textContent.includes('Domaines');
                });
            }

            if (!domainsWrapper) {
                console.warn('Conteneur des domaines non trouvé');
                return;
            }

            // Trouver le conteneur exact des checkboxes
            let checkboxContainer = domainsWrapper.querySelector('.form-widget');
            if (!checkboxContainer) {
                checkboxContainer = domainsWrapper.querySelector('div[id*="selectedDomains"]');
            }
            if (!checkboxContainer) {
                checkboxContainer = domainsWrapper;
            }

            // Supprimer toutes les checkboxes existantes
            const existingCheckboxes = checkboxContainer.querySelectorAll('.form-check');
            existingCheckboxes.forEach(cb => cb.remove());

            // Déterminer le nom de base du champ
            const partnerSelectName = selectElement.getAttribute('name');
            const baseName = partnerSelectName ? partnerSelectName.replace('[partner]', '[selectedDomains]') : 'selectedDomains';

            // Créer de nouvelles checkboxes pour chaque domaine
            domains.forEach((domain, index) => {
                const checkboxId = `${selectElement.id.replace('_partner', '')}_selectedDomains_${index}`;

                const checkboxDiv = document.createElement('div');
                checkboxDiv.className = 'form-check';

                const checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.className = 'form-check-input';
                checkbox.id = checkboxId;
                checkbox.name = `${baseName}[${index}]`;
                checkbox.value = domain;
                checkbox.checked = true; // Tous sélectionnés par défaut

                const label = document.createElement('label');
                label.className = 'form-check-label';
                label.htmlFor = checkboxId;
                label.textContent = domain;

                checkboxDiv.appendChild(checkbox);
                checkboxDiv.appendChild(label);
                checkboxContainer.appendChild(checkboxDiv);
            });
        });

        // Déclencher le changement au chargement si un partenaire est déjà sélectionné
        if (selectElement.value) {
            setTimeout(() => selectElement.dispatchEvent(new Event('change')), 100);
        }
    }

    // Gérer tous les selects de partenaires existants
    document.querySelectorAll('.partner-select').forEach(handlePartnerSelect);

    // Observer l'ajout de nouveaux éléments dans les collections (EasyAdmin CollectionField)
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            mutation.addedNodes.forEach(function(node) {
                if (node.nodeType === 1) { // Element node
                    const newSelects = node.querySelectorAll ? node.querySelectorAll('.partner-select') : [];
                    newSelects.forEach(handlePartnerSelect);

                    // Si le node lui-même est un select
                    if (node.classList && node.classList.contains('partner-select')) {
                        handlePartnerSelect(node);
                    }
                }
            });
        });
    });

    // Observer le body pour les changements
    observer.observe(document.body, {
        childList: true,
        subtree: true
    });
});

# Tableau de Bord - Spécifications

## 📊 Analyse des données existantes

### Ce qui EXISTE déjà

Les données actuelles permettent déjà beaucoup de métriques utiles :

- ✅ **CA encaissé** : factures avec `datePaiement` renseignée
- ✅ **CA facturé vs encaissé** : `totalTtc` des factures par statut
- ✅ **Factures en attente/retard** : via les statuts et `dateEcheance`
- ✅ **Taux de transformation** : devis acceptés → factures
- ✅ **Répartition par client** : tous les montants sont liés aux clients
- ✅ **Délai de paiement** : `dateFacture` vs `datePaiement`

## 🔧 Fonctionnalités manquantes

### 1. Paramètres auto-entrepreneur

Extension de l'entité `Company` pour stocker :

```php
- plafondCaAnnuel (ex: 77700€ pour services)
- tauxCotisationsUrssaf (ex: 21.2%)
- objectifCaMensuel
- objectifCaAnnuel
- anneeFiscaleEnCours (2025)
```

**Utilité** : Calculer la progression, les cotisations estimées, alerter sur le plafond

### 2. Dépenses professionnelles (OPTIONNEL)

Nouvelle entité `Expense` :

```php
- dateDepense: DateTimeImmutable
- montant: decimal(10,2)
- categorie: string (abonnements, matériel, formation, déplacement)
- description: text
- justificatif: string (nom fichier)
- createdBy: User
```

**Utilité** : Calculer le bénéfice net = CA - dépenses

### 3. Méthodes d'agrégation

#### Dans `FactureRepository`

```php
/**
 * Finances
 */
public function getRevenueByPeriod(
    \DateTimeImmutable $startDate,
    \DateTimeImmutable $endDate,
    bool $paid = true
): float;

public function getRevenueByMonth(int $year): array;

public function getRevenueByClient(int $year): array;

public function getOverdueInvoices(): array;

public function getAveragePaymentDelay(): int;

/**
 * Prévisions
 */
public function getPendingRevenue(): float; // factures non payées

public function getUpcomingPayments(int $days = 30): array;
```

#### Dans `DevisRepository`

```php
/**
 * Conversion
 */
public function getConversionRate(
    \DateTimeImmutable $startDate,
    \DateTimeImmutable $endDate
): float;

public function getQuotesByStatus(int $year): array;

public function getPendingQuotes(): array;
```

### 4. Page Dashboard

Nouveau `DashboardController` avec sections :

#### Section Financière

- 💰 **CA encaissé**
  - Ce mois
  - Cette année
  - Variation vs mois précédent

- 📈 **Graphique évolution mensuelle**
  - CA encaissé par mois
  - CA facturé par mois

- 🎯 **Progression**
  - vs objectif mensuel/annuel
  - vs plafond auto-entrepreneur

- ⚠️ **Cotisations URSSAF estimées**
  - Montant à prévoir
  - Prochaine échéance

- 💳 **En attente d'encaissement**
  - Montant total
  - Liste des factures

#### Section Activité

- 📋 **Devis**
  - En cours (à envoyer, envoyés, à relancer)
  - Taux de conversion
  - Montant total des devis en cours

- 🧾 **Factures**
  - Payées ce mois
  - En attente de paiement
  - En retard
  - Délai moyen de paiement

#### Section Clients

- 👥 **Top 5 clients** (par CA annuel)
- 📊 **Répartition du CA** (camembert)
- 🆕 **Nouveaux clients** (ce mois, cette année)
- 📈 **Croissance par client**

#### Alertes & Actions rapides

- 🔴 **Factures en retard**
  - Nombre + montant total
  - Liste avec actions (relancer, voir détail)

- 🟡 **Devis à relancer**
  - Liste avec date d'envoi
  - Action directe

- 🟢 **Factures à envoyer**
  - Nombre
  - Accès rapide

- 📅 **Échéances URSSAF**
  - Prochaine date
  - Montant estimé

### 5. Bibliothèque de graphiques

**Recommandation : Chart.js**

Avantages :
- Gratuit et open source
- Léger (~60KB)
- Bien documenté
- Responsive
- Compatible avec tous navigateurs

Types de graphiques utilisés :
- **Ligne** : évolution CA mensuel
- **Camembert** : répartition CA par client
- **Barres** : comparaisons (CA facturé vs encaissé)
- **Jauge** : progression vs plafond/objectif

### 6. Exports (BONUS)

- **Export CSV** : factures/devis filtrées
- **Export Excel** : récapitulatif annuel avec graphiques
- **Export PDF** : document pour comptable/URSSAF
- **Déclarations** : génération automatique formulaires URSSAF

## 🎨 Mockup du Dashboard

```
┌─────────────────────────────────────────────────────────────────┐
│  📊 Tableau de Bord - 2025                        [Période ▼]   │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  💰 CHIFFRE D'AFFAIRES                                          │
│  ┌──────────────┬──────────────┬──────────────┬──────────────┐ │
│  │   Ce mois    │    Année     │   Objectif   │   Plafond    │ │
│  │   4 500 €    │   42 300 €   │   60 000 €   │   77 700 €   │ │
│  │   +12% ↗     │   Prog.      │     71%      │     54%      │ │
│  └──────────────┴──────────────┴──────────────┴──────────────┘ │
│                                                                  │
│  ⚠️ Cotisations URSSAF estimées : ~8 970 € (21.2%)              │
│  💳 En attente d'encaissement : 7 250 €                         │
│                                                                  │
│  📈 ÉVOLUTION DU CHIFFRE D'AFFAIRES                             │
│  ┌────────────────────────────────────────────────────────────┐ │
│  │                     [Graphique ligne]                       │ │
│  │   CA encaissé ━━━━   CA facturé ┄┄┄┄                       │ │
│  │   €                                                         │ │
│  │   8k│                                    ●                  │ │
│  │   6k│              ●              ●                         │ │
│  │   4k│        ●                                   ●          │ │
│  │   2k│  ●                                                    │ │
│  │   0k└──┬──┬──┬──┬──┬──┬──┬──┬──┬──┬──┬──                  │ │
│  │      Jan Feb Mar Apr May Jun Jul Aug Sep Oct Nov Dec       │ │
│  └────────────────────────────────────────────────────────────┘ │
│                                                                  │
├─────────────────────────────────────────────────────────────────┤
│  🔔 ALERTES & ACTIONS                                           │
│  ┌─────────────────────────────────────────────────────────┐   │
│  │ 🔴 3 factures en retard (2 450 €)      [Voir tout →]    │   │
│  │ 🟡 2 devis à relancer                  [Relancer →]     │   │
│  │ 📅 Déclaration URSSAF dans 15 jours    [Préparer →]    │   │
│  └─────────────────────────────────────────────────────────┘   │
│                                                                  │
├─────────────────────────────────────────────────────────────────┤
│  📊 ACTIVITÉ                                                    │
│  ┌──────────────────────────┬──────────────────────────┐       │
│  │        DEVIS             │       FACTURES           │       │
│  │  • 5 en cours            │  • 3 payées ce mois     │       │
│  │  • 2 acceptés (générer   │  • 2 en attente (7250€) │       │
│  │    facture)              │  • 1 en retard (1200€)  │       │
│  │  • Taux conversion: 65%  │  • Délai moyen: 18j     │       │
│  │                          │                          │       │
│  │  [Nouveau devis]         │  [Nouvelle facture]     │       │
│  └──────────────────────────┴──────────────────────────┘       │
│                                                                  │
├─────────────────────────────────────────────────────────────────┤
│  👥 CLIENTS                                                     │
│  ┌──────────────────────────┬──────────────────────────┐       │
│  │  TOP 5 CLIENTS (2025)    │  RÉPARTITION CA          │       │
│  │                          │                          │       │
│  │  1. Client A  12 450 €   │      [Camembert]        │       │
│  │  2. Client B   8 900 €   │                          │       │
│  │  3. Client C   7 200 €   │   A: 29%                │       │
│  │  4. Client D   5 500 €   │   B: 21%                │       │
│  │  5. Client E   4 800 €   │   C: 17%                │       │
│  │                          │   D: 13%                │       │
│  │  🆕 3 nouveaux clients    │   E: 11%                │       │
│  │                          │   Autres: 9%            │       │
│  └──────────────────────────┴──────────────────────────┘       │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

## 🚀 Plan d'implémentation

### Phase 1 - Dashboard basique (MVP)

**Priorité : HAUTE**

1. **Extension entité Company**
   - Ajouter champs auto-entrepreneur
   - Migration BDD
   - Formulaire dans CompanyCrudController

2. **Méthodes d'agrégation**
   - FactureRepository : CA, factures en retard, pending
   - DevisRepository : conversion, pending

3. **DashboardController & Template**
   - Route `/admin/dashboard`
   - Métriques de base (cartes)
   - Listes simples (sans graphiques)

4. **Navigation**
   - Ajouter dans menu admin
   - Définir comme page d'accueil

**Durée estimée** : 2-3h de dev

### Phase 2 - Visualisations

**Priorité : MOYENNE**

5. **Intégration Chart.js**
   - Installation via CDN
   - Configuration de base

6. **Graphiques**
   - Évolution CA mensuel (ligne)
   - Répartition clients (camembert)
   - CA facturé vs encaissé (barres)

7. **Amélioration UI**
   - Design responsive
   - Couleurs cohérentes
   - Icônes

**Durée estimée** : 2-3h de dev

### Phase 3 - Fonctionnalités avancées

**Priorité : BASSE (optionnel)**

8. **Entité Expense**
   - Création entité + CRUD
   - Upload justificatifs
   - Calcul bénéfice net

9. **Exports**
   - CSV factures/devis
   - PDF récapitulatif
   - Modèles déclarations URSSAF

10. **Prévisions**
    - Projection CA fin d'année
    - Tendances
    - Alertes prédictives

**Durée estimée** : 4-5h de dev

## 📝 Notes techniques

### Calcul CA encaissé

```php
// CA encaissé = somme des factures avec datePaiement renseignée
$qb = $this->createQueryBuilder('f')
    ->select('SUM(f.totalTtc)')
    ->where('f.datePaiement BETWEEN :start AND :end')
    ->andWhere('f.status = :status')
    ->setParameter('start', $startDate)
    ->setParameter('end', $endDate)
    ->setParameter('status', Facture::STATUS_PAYE);

return (float) $qb->getQuery()->getSingleScalarResult();
```

### Calcul taux de conversion

```php
// Taux = (devis acceptés / devis envoyés) * 100
$sent = count of devis with status IN (ENVOYE, RELANCE, ACCEPTE, REFUSE)
$accepted = count of devis with status = ACCEPTE

return $sent > 0 ? ($accepted / $sent) * 100 : 0;
```

### Alerte plafond auto-entrepreneur

```php
$plafond = $company->getPlafondCaAnnuel();
$caAnnuel = $this->getRevenueByYear($year);
$pourcentage = ($caAnnuel / $plafond) * 100;

if ($pourcentage >= 90) {
    // Alerte rouge : risque de dépassement
} elseif ($pourcentage >= 75) {
    // Alerte orange : surveiller
}
```

## 🎯 Objectifs business

Le tableau de bord doit permettre de :

1. **Piloter l'activité** : vision claire du CA et de l'activité
2. **Anticiper** : factures en retard, devis à relancer, échéances
3. **Optimiser** : identifier les meilleurs clients, délais de paiement
4. **Déclarer** : URSSAF, TVA (si applicable), impôts
5. **Décider** : augmenter les tarifs, relancer des clients, ajuster les objectifs

## 📚 Ressources

- [Chart.js Documentation](https://www.chartjs.org/docs/latest/)
- [EasyAdmin Dashboard](https://symfony.com/bundles/EasyAdminBundle/current/dashboards.html)
- [Auto-entrepreneur : plafonds 2025](https://www.autoentrepreneur.urssaf.fr/)
- [Cotisations URSSAF](https://www.autoentrepreneur.urssaf.fr/portail/accueil/sinformer-sur-le-statut/lessentiel-du-statut.html)

---

**Date de création** : 2025-01-19
**Dernière mise à jour** : 2025-01-19
**Version** : 1.0

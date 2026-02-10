# Scoring SEO Dashboard - Documentation technique

## Architecture du scoring

Le scoring est dans `DashboardSeoService::rankSeoKeywords()`. Deux tableaux distincts avec des logiques differentes.

---

## Tableau "Top 10" - Score de visibilite

**Formule :** `baseScore x ctrFactor x momentum x monthlyVelocity`

| Composant | Formule | Plage |
|-----------|---------|-------|
| Base | `(impressions / position) x (1 + 2*clicks) x pageBonus` | socle |
| pageBonus | pos <= 10 : 1.5, pos <= 20 : 1.25, sinon 1.0 | 1.0 - 1.5 |
| ctrFactor | CTR reel vs benchmark par position (interpole) | 0.75 - 1.08 |
| momentum | Tendance 7j (3 derniers jours vs 3 precedents) | 0.7 - 1.3 |
| monthlyVelocity | Variation mensuelle | 0.92 - 1.15 |

---

## Tableau "A travailler" - Potentiel inexploite

### Filtres d'eligibilite

Un mot-cle doit passer TOUS ces filtres pour apparaitre :

1. **Relevance HIGH** uniquement
2. **Impressions > 0** sur les 2 derniers jours avec donnees
3. **Non optimise recemment** : `lastOptimizedAt` null ou > 30 jours (bouton "Fait" dans le tableau)
4. **Position <= 20** (sauf si declin M-1 <= -5, alors jusqu'a position > 20)
5. **Position stable** : ecart-type de position sur 7 jours < 3
6. **Pas en hausse forte** : momentum < 1.15 (sauf critere declin)
7. **Impressions minimum** selon le critere (30 a 100)

### Definition de "zone stable"

L'ecart-type (stddev) de la position sur les 7 derniers jours avec donnees en base.
- **stddev < 3** = stable (la position ne varie pas de plus de ~3 places)
- **stddev >= 3** = instable (le mot-cle est en mouvement, on attend)

Exemple :
- Positions [8.2, 7.5, 8.0, 7.8, 8.1, 7.9, 8.3] -> stddev = 0.26 -> STABLE
- Positions [45, 32, 18, 12, 15, 11, 9] -> stddev = 12.3 -> INSTABLE (en mouvement)

### Les 4 criteres declencheurs

| # | Critere | Conditions | Score | Action recommandee |
|---|---------|------------|-------|--------------------|
| 1 | CTR faible en page 1 | pos <= 10, CTR < 50% benchmark, impr >= 10% du max, stable, pas en hausse | ctrGap x 3.0 | Optimiser title et meta description |
| 2 | Proche du top 10 | pos 11-15, impr >= 15% du max, stable, pas en hausse | ctrGap x 1.5 | Enrichir le contenu pour passer en page 1 |
| 3 | En declin | pos <= 20, M-1 <= -5, pas en hausse (quelle que soit la stabilite) | declin x impr x 0.1 | Analyser la concurrence et rafraichir le contenu |
| 4 | Fort volume en page 2 | pos 16-20, impr >= 30% du max, stable, pas en hausse | ctrGap x 0.8 | Backlinks et contenu approfondi |

**Note :** "max" = impressions du mot-cle le plus visible parmi les eligibles (HIGH, actifs, impr > 0 sur les 2 derniers jours). Les seuils relatifs s'adaptent automatiquement a la taille du site.

### Multiplicateur volume

Apres le score du critere, on applique (ratio = impressions / max impressions) :
- ratio >= 75% : x 1.5
- ratio >= 40% : x 1.2
- ratio >= 15% : x 1.0
- ratio < 15% : x 0.8

---

## Benchmarks CTR par position

Moyennes sectorielles utilisees (interpolation lineaire entre les bornes) :

| Position | CTR attendu |
|----------|-------------|
| 1 | 28% |
| 2 | 22% |
| 3 | 18% |
| 4 | 15% |
| 5 | 12% |
| 6 | 10% |
| 7 | 9% |
| 8 | 8% |
| 9 | 7% |
| 10 | 6% |
| 15 | 3% |
| 20 | 2% |
| 30 | 0.8% |
| 50 | 0.2% |

---

## Momentum 7 jours

Calcul : position moyenne des 3 derniers jours avec donnees vs 3 jours precedents.
Necessite au minimum 4 jours avec donnees (sinon momentum = 1.0).

| Tendance (positions gagnees) | Facteur |
|------------------------------|---------|
| >= +5 | 1.3 (forte hausse) |
| >= +2 | 1.15 (hausse moderee) |
| -1 a +1 | 1.0 (stable) |
| -1 a -4 | 0.9 (baisse moderee) |
| <= -5 | 0.7 (forte baisse) |

**Dans le Top 10** : le momentum booste les mots-cles en hausse.
**Dans A travailler** : le momentum est INVERSE. Un mot-cle qui monte n'a pas besoin d'action.

---

## Seuils a ajuster dans le temps

### Quand les donnees grandissent (> 25 requetes a 50+ impressions)

Fichier : `src/Service/DashboardSeoService.php`, methode `rankSeoKeywords()`

#### 1. Seuils d'impressions (actuellement relatifs)

Les seuils sont calcules en pourcentage du max d'impressions des mots-cles eligibles.
Quand le site aura suffisamment de trafic (> 25 requetes a 50+ impressions),
repasser a des seuils absolus :
```php
// Critere 1 : 10% du max -> passer a $impressions >= 50
// Critere 2 : 15% du max -> passer a $impressions >= 100
// Critere 4 : 30% du max -> passer a $impressions >= 200
```

#### 2. Seuil CTR ratio

Actuellement on alerte quand CTR < 50% du benchmark :
```php
$ctrRatio < 0.5  // Critere 1
```

Avec plus de donnees, on peut etre plus exigeant :
```php
$ctrRatio < 0.7  // Alerter des que le CTR est 30% sous le benchmark
```

#### 3. Seuil de stabilite

Actuellement stddev < 3 :
```php
$isStable = $stddev < 3;
```

Si le site mature et les positions se stabilisent, baisser a 2 pour etre plus strict :
```php
$isStable = $stddev < 2;
```

#### 4. Multiplicateur volume

Actuellement base sur le ratio impressions/max (75%, 40%, 15%).
Avec plus de trafic, repasser a des seuils absolus :
```php
// Actuel (ratio)    -> Futur (trafic > 500 clics/mois)
// ratio >= 75%      -> $impressions >= 1000
// ratio >= 40%      -> $impressions >= 500
// ratio >= 15%      -> $impressions >= 200
```

### Signaux non encore implementes (a envisager plus tard)

1. **Featured snippets** : si un concurrent a un snippet enrichi en position 0, la position effective est decalee
2. **Intent match** : verifier que la page cible correspond a l'intention de recherche
3. **Analyse concurrents** : comparer les titles/meta des concurrents (necessite API SEMrush/Ahrefs)
4. **Volume de recherche** : tendance mensuelle du volume de recherche par mot-cle
5. **Volatilite SERP** : certains jours Google est plus volatile (algorithm updates), dampener les variations ces jours-la

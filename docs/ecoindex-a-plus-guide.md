# Guide EcoIndex A+ pour Symfony

Guide complet pour obtenir un score EcoIndex A+ sur n'importe quelle application Symfony.

## Table des matières

1. [Architecture Frontend](#1-architecture-frontend)
2. [CSS](#2-css)
3. [JavaScript](#3-javascript)
4. [Images et médias](#4-images-et-médias)
5. [Polices](#5-polices)
6. [Configuration serveur](#6-configuration-serveur)
7. [Configuration Symfony](#7-configuration-symfony)
8. [Validation et mesure](#8-validation-et-mesure)

---

## 1. Architecture Frontend

### ✅ Pas de framework CSS lourd
- ❌ Éviter Bootstrap, Tailwind, Bulma
- ✅ CSS custom sur mesure
- ✅ Utiliser les variables CSS natives (`--var-name`)

### ✅ Pas de bibliothèques JavaScript inutiles
- ❌ Pas de jQuery sauf si absolument nécessaire
- ❌ Pas de React/Vue pour un site vitrine
- ✅ JavaScript Vanilla natif
- ✅ Charger uniquement ce qui est nécessaire par page

### ✅ Structure HTML sémantique et légère
- Minimiser la complexité DOM (moins de 800 éléments)
- Utiliser les balises HTML5 sémantiques (`<header>`, `<nav>`, `<main>`, `<section>`, `<article>`, `<footer>`)
- Pas de `<div>` inutiles

---

## 2. CSS

### ✅ Externaliser tout le CSS inline
```twig
{# ❌ Mauvais #}
<div style="color: red; margin: 10px;">...</div>

{# ✅ Bon #}
<div class="error-message">...</div>
```

### ✅ Minifier le CSS en production
```bash
# Installer csso
npm install -g csso-cli

# Minifier
npx csso public/css/style.css -o public/css/style.min.css
```

### ✅ Charger la version minifiée en prod
```twig
<link rel="stylesheet" href="{{ asset('css/style' ~ (app.environment == 'prod' ? '.min' : '') ~ '.css') }}">
```

### ✅ Éviter les @import CSS
```css
/* ❌ Mauvais - génère des requêtes HTTP supplémentaires */
@import url('fonts.css');
@import url('components.css');

/* ✅ Bon - tout dans un seul fichier ou concaténé */
/* Contenu de fonts.css */
/* Contenu de components.css */
```

### ✅ CSS critique inline (optionnel pour performance extrême)
Pour les très grosses optimisations, extraire le CSS critique et l'inliner dans `<head>`.

---

## 3. JavaScript

### ✅ Minifier le JavaScript
```bash
# Installer terser
npm install -g terser

# Minifier
npx terser public/js/main.js -o public/js/main.min.js -c -m
```

### ✅ Charger la version minifiée en prod
```twig
<script src="{{ asset('js/main' ~ (app.environment == 'prod' ? '.min' : '') ~ '.js') }}"></script>
```

### ✅ Utiliser defer ou async
```html
<script src="script.js" defer></script>
```

### ✅ Éviter les CDN externes (sauf exceptions)
```html
<!-- ❌ Mauvais - requête externe, cookies tiers -->
<script src="https://cdn.example.com/library.js"></script>

<!-- ✅ Bon - auto-hébergé -->
<script src="{{ asset('js/library.js') }}" defer></script>
```

**Exception** : FontAwesome, polices Google (avec preconnect).

---

## 4. Images et médias

### ✅ Optimiser toutes les images

#### JPG/PNG
```bash
# ImageMagick - Réduire qualité et dimensions
convert input.jpg -quality 85 -resize 1920x1080\> output.jpg

# Optimisation agressive
convert input.jpg -strip -quality 75 -sampling-factor 4:2:0 output.jpg
```

#### SVG
```bash
# Installer SVGO
npm install -g svgo

# Optimiser
svgo input.svg -o output.svg
```

### ✅ Utiliser les formats modernes
```html
<picture>
  <source srcset="image.webp" type="image/webp">
  <source srcset="image.jpg" type="image/jpeg">
  <img src="image.jpg" alt="Description" loading="lazy">
</picture>
```

### ✅ Lazy loading
```html
<img src="image.jpg" alt="Description" loading="lazy">
```

### ✅ Dimensions explicites
```html
<!-- Évite le layout shift -->
<img src="image.jpg" alt="Description" width="800" height="600" loading="lazy">
```

### ✅ Règles de poids cibles
- Logo : < 50 Ko
- Photo de profil : < 200 Ko
- Photo plein écran : < 500 Ko
- Favicon : < 10 Ko

---

## 5. Polices

### ✅ Limiter le nombre de polices
- Maximum 2 familles de polices
- Maximum 4 variantes (normal, bold, italic, bold-italic)

### ✅ Utiliser font-display: swap
```css
@font-face {
  font-family: 'CustomFont';
  src: url('/fonts/custom.woff2') format('woff2');
  font-display: swap;
}
```

Ou pour Google Fonts :
```html
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap">
```

### ✅ Preconnect pour les polices externes
```html
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
```

### ✅ Sous-ensemble de caractères
Pour Google Fonts, limiter aux caractères français :
```
?family=Roboto&subset=latin&text=ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyzÀÁÂÃÄÅÇÈÉÊËÌÍÎÏÑÒÓÔÕÖÙÚÛÜÝàáâãäåçèéêëìíîïñòóôõöùúûüýÿ0123456789
```

---

## 6. Configuration serveur

### ✅ Compression Gzip/Brotli

**Apache (.htaccess)**
```apache
# Compression Gzip
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/html text/plain text/xml text/css
    AddOutputFilterByType DEFLATE application/javascript application/json application/xml
    AddOutputFilterByType DEFLATE image/svg+xml
</IfModule>

# Compression Brotli (si disponible)
<IfModule mod_brotli.c>
    AddOutputFilterByType BROTLI_COMPRESS text/html text/plain text/xml text/css
    AddOutputFilterByType BROTLI_COMPRESS application/javascript application/json
</IfModule>
```

**Nginx**
```nginx
gzip on;
gzip_vary on;
gzip_types text/plain text/css text/xml text/javascript application/javascript application/json application/xml+rss;
gzip_min_length 1000;
gzip_comp_level 6;
```

### ✅ Cache headers

**Apache (.htaccess)**
```apache
<IfModule mod_expires.c>
    ExpiresActive On

    # Images
    ExpiresByType image/jpeg "access plus 1 year"
    ExpiresByType image/png "access plus 1 year"
    ExpiresByType image/webp "access plus 1 year"
    ExpiresByType image/svg+xml "access plus 1 year"
    ExpiresByType image/x-icon "access plus 1 year"

    # CSS et JavaScript
    ExpiresByType text/css "access plus 1 year"
    ExpiresByType application/javascript "access plus 1 year"

    # Polices
    ExpiresByType font/woff2 "access plus 1 year"
    ExpiresByType font/woff "access plus 1 year"

    # HTML
    ExpiresByType text/html "access plus 0 seconds"
</IfModule>

# Cache-Control headers
<IfModule mod_headers.c>
    <FilesMatch "\.(jpg|jpeg|png|gif|webp|svg|woff2|woff|css|js)$">
        Header set Cache-Control "max-age=31536000, public, immutable"
    </FilesMatch>
    <FilesMatch "\.(html)$">
        Header set Cache-Control "no-cache, no-store, must-revalidate"
    </FilesMatch>
</IfModule>
```

### ✅ HTTP/2
Activer HTTP/2 sur le serveur pour le multiplexage des requêtes.

---

## 7. Configuration Symfony

### ✅ Cache APCu en production
```yaml
# config/packages/prod/cache.yaml
framework:
    cache:
        app: cache.adapter.apcu
        default_redis_provider: '%env(REDIS_URL)%'
```

### ✅ OPCache activé
```ini
; php.ini
opcache.enable=1
opcache.memory_consumption=256
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0 ; En production
```

### ✅ Désactiver les profilers en prod
```yaml
# config/packages/prod/web_profiler.yaml
web_profiler:
    toolbar: false
    intercept_redirects: false
```

### ✅ Asset versioning
```yaml
# config/packages/framework.yaml
framework:
    assets:
        version_strategy: 'Symfony\Component\Asset\VersionStrategy\JsonManifestVersionStrategy'
        json_manifest_path: '%kernel.project_dir%/public/build/manifest.json'
```

### ✅ Twig cache strict
```yaml
# config/packages/twig.yaml
twig:
    cache: '%kernel.cache_dir%/twig'
    auto_reload: false # en production
```

---

## 8. Validation et mesure

### ✅ Outils de mesure

#### EcoIndex
- Site : https://www.ecoindex.fr/
- Mesure : Poids, complexité DOM, requêtes HTTP
- Objectif : Score A (< 1.5)

#### Website Carbon
- Site : https://www.websitecarbon.com/
- Mesure : Émissions CO₂ par visite
- Objectif : < 0.1g CO₂

#### PageSpeed Insights
- Site : https://pagespeed.web.dev/
- Mesure : Performance globale
- Objectif : Score > 90

#### GTmetrix
- Site : https://gtmetrix.com/
- Mesure : Performance, structure
- Objectif : Grade A

### ✅ Checklist finale

**Complexité DOM**
- [ ] Moins de 800 éléments DOM total
- [ ] Profondeur maximale < 15 niveaux
- [ ] Pas de nœuds enfants > 60

**Poids de page**
- [ ] Poids total < 500 Ko (idéal < 300 Ko)
- [ ] HTML < 50 Ko
- [ ] CSS < 50 Ko
- [ ] JS < 50 Ko
- [ ] Images < 300 Ko cumulé

**Requêtes HTTP**
- [ ] Moins de 25 requêtes total (idéal < 15)
- [ ] Combiner les fichiers CSS/JS
- [ ] Utiliser les sprites CSS si nécessaire

**Performance**
- [ ] First Contentful Paint < 1.5s
- [ ] Largest Contentful Paint < 2.5s
- [ ] Cumulative Layout Shift < 0.1
- [ ] Time to Interactive < 3.5s

**Serveur**
- [ ] Gzip/Brotli activé
- [ ] Cache headers configurés
- [ ] HTTP/2 activé
- [ ] CDN configuré (optionnel)

---

## Résumé : Actions critiques pour EcoIndex A+

1. **CSS/JS minifié** et chargement conditionnel en prod
2. **Pas de frameworks CSS** lourds
3. **Images optimisées** (< 200 Ko par image, lazy loading)
4. **Moins de 15 requêtes HTTP** total
5. **Poids total < 300 Ko** (idéal < 200 Ko)
6. **Complexité DOM < 800 éléments**
7. **Compression Gzip** activée sur le serveur
8. **Cache headers** agressifs (1 an pour assets)
9. **Polices limitées** (max 2 familles, font-display: swap)
10. **Pas de scripts tiers** non essentiels (analytics minimaliste)

---

**Auteur** : Fabrice Dhuicque - Alré Web
**Date** : Novembre 2025
**Version** : 1.0

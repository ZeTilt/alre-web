# Configuration HTTPS

## Problème résolu : "Site non sécurisé"

Le site affichait "non sécurisé" malgré un certificat SSL valide. Voici les corrections apportées :

## Modifications effectuées

### 1. Redirection forcée vers HTTPS (`.htaccess`)

Ajout d'une règle de redirection automatique de HTTP vers HTTPS :

```apache
# Force HTTPS
RewriteCond %{HTTPS} !=on
RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

**Effet** : Toutes les requêtes HTTP sont automatiquement redirigées vers HTTPS avec un code 301 (redirection permanente).

### 2. Configuration des proxies de confiance (`config/packages/framework.yaml`)

Ajout de la configuration pour que Symfony reconnaisse correctement les connexions HTTPS derrière un reverse proxy :

```yaml
framework:
    trusted_proxies: '127.0.0.1,REMOTE_ADDR'
    trusted_headers: ['x-forwarded-for', 'x-forwarded-proto', 'x-forwarded-port']
```

**Effet** : Symfony fait confiance aux en-têtes du proxy et génère correctement les URLs en HTTPS.

### 3. Routes forcées en HTTPS en production (`config/routes/prod/secure.yaml`)

Toutes les routes sont configurées pour utiliser uniquement HTTPS en production :

```yaml
controllers:
    resource: ../../../src/Controller/
    type: attribute
    schemes: [https]
```

**Effet** : Les routes ne répondent qu'en HTTPS en environnement de production.

### 4. En-têtes de sécurité HTTP (`src/EventListener/SecurityHeadersListener.php`)

Ajout automatique d'en-têtes de sécurité à toutes les réponses :

- **HSTS (Strict-Transport-Security)** : Force le navigateur à toujours utiliser HTTPS pendant 1 an
- **upgrade-insecure-requests** : Force le chargement de toutes les ressources en HTTPS
- **X-Content-Type-Options** : Protection contre le MIME sniffing
- **X-Frame-Options** : Protection contre le clickjacking
- **X-XSS-Protection** : Protection XSS
- **Referrer-Policy** : Contrôle des informations de référence

## Vérifications post-déploiement

### 1. Tester la redirection HTTP → HTTPS

```bash
curl -I http://alre-web.bzh
```

Vous devriez voir :
```
HTTP/1.1 301 Moved Permanently
Location: https://alre-web.bzh/
```

### 2. Vérifier les en-têtes de sécurité

```bash
curl -I https://alre-web.bzh
```

Vous devriez voir :
```
Strict-Transport-Security: max-age=31536000; includeSubDomains
Content-Security-Policy: upgrade-insecure-requests
X-Content-Type-Options: nosniff
X-Frame-Options: SAMEORIGIN
```

### 3. Test dans le navigateur

1. Videz le cache du navigateur (Ctrl+Shift+Delete)
2. Accédez à http://alre-web.bzh (sans le s)
3. Vous devriez être automatiquement redirigé vers https://alre-web.bzh
4. Le cadenas vert devrait apparaître dans la barre d'adresse

### 4. Vérifier l'absence de contenu mixte

Ouvrez la console développeur (F12) et vérifiez qu'il n'y a pas d'avertissements du type :
- "Mixed Content: The page was loaded over HTTPS but requested an insecure resource"

## Outils de diagnostic

### SSL Labs

Testez votre configuration SSL :
https://www.ssllabs.com/ssltest/analyze.html?d=alre-web.bzh

### Security Headers

Vérifiez vos en-têtes de sécurité :
https://securityheaders.com/?q=https://alre-web.bzh

### Why No Padlock?

Diagnostic du contenu mixte :
https://www.whynopadlock.com/results/alre-web.bzh

## Dépannage

### Le problème persiste après déploiement

1. **Videz le cache Symfony en production** :
   ```bash
   APP_ENV=prod php bin/console cache:clear
   ```

2. **Rechargez la configuration Apache** :
   ```bash
   sudo service apache2 reload
   ```

3. **Videz le cache du navigateur** :
   - Chrome/Edge : Ctrl+Shift+Delete
   - Firefox : Ctrl+Shift+Delete
   - Safari : Cmd+Option+E

4. **Testez en navigation privée** :
   Cela élimine les problèmes de cache

### Contenu mixte détecté

Si la console indique du contenu mixte :

1. Cherchez les ressources HTTP dans le code :
   ```bash
   grep -r "http://" templates/ public/
   ```

2. Remplacez par des URLs relatives ou HTTPS

3. Pour les ressources externes, assurez-vous qu'elles sont en HTTPS

### Problème avec un CDN ou proxy

Si vous utilisez Cloudflare, Nginx ou un autre proxy :

1. Vérifiez que le proxy envoie bien les en-têtes `X-Forwarded-Proto: https`

2. Ajoutez l'IP du proxy dans `trusted_proxies` :
   ```yaml
   framework:
       trusted_proxies: '127.0.0.1,REMOTE_ADDR,IP_DU_PROXY'
   ```

## Bonnes pratiques

1. ✅ **Toujours utiliser des URLs relatives** dans les templates
2. ✅ **Utiliser `asset()` pour les ressources** statiques
3. ✅ **Utiliser `path()` pour les routes** internes
4. ✅ **Vérifier les ressources externes** (CDN, images, API)
5. ✅ **Tester régulièrement** avec SSL Labs et Security Headers

## Maintenance

- Le certificat SSL doit être renouvelé avant expiration (Let's Encrypt : tous les 90 jours)
- Vérifiez régulièrement les en-têtes de sécurité
- Mettez à jour le `max-age` de HSTS progressivement (actuellement 1 an)

## Références

- [Symfony - Trusting Proxies](https://symfony.com/doc/current/deployment/proxies.html)
- [MDN - HSTS](https://developer.mozilla.org/fr/docs/Web/HTTP/Headers/Strict-Transport-Security)
- [MDN - Content Security Policy](https://developer.mozilla.org/fr/docs/Web/HTTP/CSP)
- [OWASP - Transport Layer Protection](https://cheatsheetseries.owasp.org/cheatsheets/Transport_Layer_Protection_Cheat_Sheet.html)

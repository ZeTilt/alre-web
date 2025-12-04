.PHONY: help dev dev-stop install deploy cache migrate assets db-reset test tests coverage coverage-html optimize-images admin-user dirs

# Variables
CONSOLE = php bin/console
COMPOSER = composer

help: ## Affiche cette aide
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-20s\033[0m %s\n", $$1, $$2}'

dev: ## Démarre le serveur Symfony en arrière-plan (port 8000)
	@symfony server:start -d --port=8000
	@echo "🚀 Serveur démarré sur http://127.0.0.1:8000"

dev-stop: ## Arrête le serveur Symfony
	@symfony server:stop
	@echo "🛑 Serveur arrêté"

install: ## Installe les dépendances (composer install)
	$(COMPOSER) install

install-prod: ## Installe les dépendances de production
	$(COMPOSER) install --no-dev --optimize-autoloader

cache: ## Nettoie le cache Symfony
	$(CONSOLE) cache:clear

cache-prod: ## Nettoie le cache en production
	$(CONSOLE) cache:clear --env=prod --no-debug

migrate: ## Lance les migrations de base de données
	$(CONSOLE) doctrine:migrations:migrate --no-interaction

db-reset: ## Reset la base de données (DEV SEULEMENT!)
	$(CONSOLE) doctrine:database:drop --force --if-exists
	$(CONSOLE) doctrine:database:create
	$(CONSOLE) doctrine:migrations:migrate --no-interaction

assets: ## Installe les assets
	$(CONSOLE) assets:install public

optimize-images: ## Optimise toutes les images des projets
	$(CONSOLE) app:optimize-project-images

optimize-logo: ## Optimise le logo et génère les variantes
	$(CONSOLE) app:optimize-logo

generate-favicons: ## Génère tous les favicons
	$(CONSOLE) app:generate-favicons images/favicon.png

test: ## Lance les tests PHPUnit
	php bin/phpunit

tests: test ## Alias pour 'make test'

coverage: ## Lance les tests avec coverage (texte)
	XDEBUG_MODE=coverage php -d zend_extension=xdebug bin/phpunit --coverage-text

coverage-html: ## Lance les tests avec coverage (HTML dans var/coverage/)
	XDEBUG_MODE=coverage php -d zend_extension=xdebug bin/phpunit --coverage-html var/coverage
	@echo "📊 Rapport disponible dans var/coverage/index.html"

dirs: ## Crée les dossiers nécessaires (uploads, etc.)
	@mkdir -p public/uploads/profile
	@mkdir -p public/uploads/projects
	@mkdir -p var/log
	@chmod -R 755 public/uploads
	@echo "✅ Dossiers créés"

deploy: ## Déploie sur le serveur de production (git pull + composer + cache + migrate)
	@echo "🚀 Déploiement en cours..."
	@git pull origin main
	@echo "📁 Création des dossiers..."
	@mkdir -p public/uploads/profile public/uploads/projects var/log
	@chmod -R 755 public/uploads
	@echo "📦 Installation des dépendances..."
	@$(COMPOSER) install --no-dev --optimize-autoloader
	@echo "🧹 Nettoyage du cache..."
	@$(CONSOLE) cache:clear --env=prod --no-debug
	@echo "🗄️  Exécution des migrations..."
	@$(CONSOLE) doctrine:migrations:migrate --no-interaction --env=prod
	@echo "✅ Déploiement terminé avec succès!"

deploy-force: ## Déploie en forçant le git pull (git reset --hard + pull)
	@echo "⚠️  Déploiement forcé en cours..."
	@git fetch origin
	@git reset --hard origin/main
	@echo "📁 Création des dossiers..."
	@mkdir -p public/uploads/profile public/uploads/projects var/log
	@chmod -R 755 public/uploads
	@echo "📦 Installation des dépendances..."
	@$(COMPOSER) install --no-dev --optimize-autoloader
	@echo "🧹 Nettoyage du cache..."
	@$(CONSOLE) cache:clear --env=prod --no-debug
	@echo "🗄️  Exécution des migrations..."
	@$(CONSOLE) doctrine:migrations:migrate --no-interaction --env=prod
	@echo "✅ Déploiement forcé terminé!"

setup: install migrate ## Setup complet du projet (install + migrate)
	@echo "✅ Projet configuré avec succès!"

admin-user: ## Crée un utilisateur admin de manière interactive
	$(CONSOLE) app:create-admin-user

chocapics: ## 🥣 Des chocapics pour le dev!
	@echo "🥣 Mmmh des chocapics..."
	@echo "   _______________"
	@echo "  /               \\"
	@echo " |  🥣 CHOCAPICS! |"
	@echo "  \_______________/"
	@echo ""
	@echo "🎉 Bon appétit!"

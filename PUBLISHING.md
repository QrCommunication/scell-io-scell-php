# Publication sur Packagist

Ce guide decrit les etapes pour publier le SDK PHP Scell.io sur Packagist.

## Prerequisites

- Compte GitHub avec acces a l'organisation `scell-io`
- Compte Packagist (https://packagist.org)
- Git configure avec les droits de push

## Etape 1: Creer le repository GitHub

### 1.1 Creer le repository

1. Aller sur https://github.com/organizations/scell-io/repositories/new
2. Nom du repository: `sdk-php`
3. Description: `SDK PHP officiel pour l'API Scell.io - Facturation electronique et signature`
4. Visibilite: **Public** (requis pour Packagist gratuit)
5. Ne pas initialiser avec README (on a deja le notre)
6. Cliquer "Create repository"

### 1.2 Pousser le code

Depuis le dossier `sdk/php/` du projet principal:

```bash
# Initialiser le repo git local
cd /path/to/FacturationSignature-api-saas/sdk/php
git init

# Ajouter tous les fichiers
git add .

# Premier commit
git commit -m "feat: initial release of Scell.io PHP SDK v1.0.0

- Electronic invoicing support (Factur-X, UBL, CII)
- Electronic signatures support (eIDAS EU-SES)
- Laravel 11/12 integration with auto-discovery
- Fluent builders for invoices and signatures
- Webhook verification
- Comprehensive error handling"

# Ajouter le remote GitHub
git remote add origin git@github.com:scell-io/sdk-php.git

# Pousser sur main
git push -u origin main
```

### 1.3 Creer le premier tag de version

```bash
# Creer le tag v1.0.0
git tag -a v1.0.0 -m "Release v1.0.0 - Initial release"

# Pousser le tag
git push origin v1.0.0
```

## Etape 2: Soumettre sur Packagist

### 2.1 Se connecter a Packagist

1. Aller sur https://packagist.org
2. Se connecter avec le compte GitHub de l'organisation

### 2.2 Soumettre le package

1. Cliquer sur "Submit" dans le menu
2. Entrer l'URL du repository: `https://github.com/scell-io/sdk-php`
3. Cliquer "Check"
4. Verifier que le nom du package est `scell/sdk`
5. Cliquer "Submit"

### 2.3 Configurer le webhook (auto-update)

Pour que Packagist se mette a jour automatiquement lors de nouveaux tags:

1. Sur GitHub, aller dans Settings > Webhooks
2. Cliquer "Add webhook"
3. Payload URL: `https://packagist.org/api/github?username=VOTRE_USERNAME`
4. Content type: `application/json`
5. Secret: (copier depuis Packagist > Profile > Show API Token)
6. Events: "Just the push event"
7. Cliquer "Add webhook"

Ou utiliser le service GitHub de Packagist:

1. Sur Packagist, aller dans le package `scell/sdk`
2. Cliquer sur "Enable GitHub Service"
3. Suivre les instructions

## Etape 3: Verifier la publication

### 3.1 Verifier sur Packagist

1. Aller sur https://packagist.org/packages/scell/sdk
2. Verifier que:
   - La description est correcte
   - Les dependances sont listees
   - La version v1.0.0 apparait

### 3.2 Tester l'installation

Dans un nouveau projet:

```bash
# Creer un projet de test
mkdir test-scell-sdk && cd test-scell-sdk
composer init --name=test/test --no-interaction

# Installer le SDK
composer require scell/sdk

# Verifier l'installation
composer show scell/sdk
```

### 3.3 Tester l'integration Laravel

```bash
# Creer un projet Laravel
composer create-project laravel/laravel test-laravel
cd test-laravel

# Installer le SDK
composer require scell/sdk

# Verifier l'auto-discovery
php artisan package:discover

# Publier la config
php artisan vendor:publish --tag=scell-config
```

## Workflow de release

Pour les futures versions:

### Mise a jour mineure (1.0.x)

```bash
# Mettre a jour CHANGELOG.md
# Commit des changements
git add .
git commit -m "fix: description du fix"

# Creer le tag
git tag -a v1.0.1 -m "Release v1.0.1 - Bug fixes"
git push origin main --tags
```

### Mise a jour majeure (1.x.0)

```bash
# Mettre a jour CHANGELOG.md
git add .
git commit -m "feat: nouvelle fonctionnalite"

git tag -a v1.1.0 -m "Release v1.1.0 - New features"
git push origin main --tags
```

## Checklist pre-release

Avant chaque release, verifier:

- [ ] `composer validate` passe sans erreur
- [ ] Tous les tests passent (`composer test`)
- [ ] PHPStan ne detecte pas d'erreur (`composer analyse`)
- [ ] CHANGELOG.md est a jour
- [ ] La version dans CHANGELOG.md correspond au tag
- [ ] README.md est a jour avec les nouvelles fonctionnalites

## Badges pour le README

Une fois publie, ajouter ces badges au README.md:

```markdown
[![Latest Version on Packagist](https://img.shields.io/packagist/v/scell/sdk.svg?style=flat-square)](https://packagist.org/packages/scell/sdk)
[![Total Downloads](https://img.shields.io/packagist/dt/scell/sdk.svg?style=flat-square)](https://packagist.org/packages/scell/sdk)
[![License](https://img.shields.io/packagist/l/scell/sdk.svg?style=flat-square)](https://packagist.org/packages/scell/sdk)
[![PHP Version](https://img.shields.io/packagist/php-v/scell/sdk.svg?style=flat-square)](https://packagist.org/packages/scell/sdk)
```

## Support

En cas de probleme avec Packagist:
- Documentation: https://packagist.org/about
- Support: https://github.com/composer/packagist/issues

# Outil de génération de tests unitaires par un LLM

Le présent projet vise à créer un outil de génération automatique de tests unitaires PHPUnit en s'appuyant sur un modèle de langage (LLM).

Dans un souci de flexibilité, l'application prend en charge trois fournisseurs de LLM : 
- un modèle local via **Ollama**
- un modèle distant via **OpenRouter** 
- l'**API Google Gemini**.

La configuration du fournisseur et du modèle s'effectue directement dans les variables d'environnement (`.env` / `.env.local`).

---

### 1. Configuration pour un LLM local (Ollama)

Pour utiliser un modèle exécuté en local via Ollama :

```env
LLM_PROVIDER="ollama"
LLM_MODEL="qwen2.5-coder:14b" # Ou tout autre modèle chargé dans l'instance Ollama
OLLAMA_API_URL="http://localhost:11434"
```

>Note : Si Ollama est hébergé sur une machine distante sur le réseau local, 
> remplacer localhost par l'adresse IP (ex : http://192.168.1.XX:11434).

### 2. Configuration pour l'API Google Gemini (Recommandé)

Pour utiliser directement l'API native de Google Gemini :

```env
LLM_PROVIDER="gemini"
LLM_MODEL="gemini-flash-latest" # Alias stable pointant vers la version Flash la plus récente
GEMINI_API_KEY="AIzaSyXXXXX"
```

> Note : Une clé API peut être générée sur [Google Studio](https://aistudio.google.com/).

### 3. Configuration pour un LLM distant (OpenRouter)

Pour externaliser la génération via la plateforme OpenRouter :

```env
LLM_PROVIDER="openrouter"
LLM_MODEL="google/gemini-2.0-flash-001"
OPENROUTER_API_KEY="sk-or-v1-XXXXX"
```

> Note : Une clé API doit être générée sur [OpenRouter](https://openrouter.ai/).

---

## Création du projet

```bash
C:\laragon\www
symfony new test_generator
cd test_generator

# Ajout des dépendances
composer require --dev symfony/test-pack
composer require --dev symfony/maker-bundle
composer require symfony/http-client
composer require --dev phpstan/phpstan
composer require --dev friendsofphp/php-cs-fixer
composer require symfony/process
composer require nikic/php-parser
# Le bundle Symfony
composer require symfony/ai-bundle
# Les bridges des fournisseurs supportés
composer require symfony/ai-open-ai-platform
composer require symfony/ai-anthropic-platform
composer require symfony/ai-ollama-platform
# Bridge de Google Gelini
composer require symfony/ai-vertex-ai-platform
#Bridge de OpenRouter
composer require symfony/ai-open-router-platform
```

## Ajout de raccourcis pour PhpStan et PHP-CS-fixer dans le composer.json

```
    "scripts": {
        "auto-scripts": {
            "cache:clear": "symfony-cmd",
            "assets:install %PUBLIC_DIR%": "symfony-cmd"
        },
        "post-install-cmd": [
            "@auto-scripts"
        ],
        "post-update-cmd": [
            "@auto-scripts"
        ],
        "phpstan": "vendor/bin/phpstan analyse",
        "cs-check": "vendor/bin/php-cs-fixer fix --dry-run --diff",
        "cs-fix": "vendor/bin/php-cs-fixer fix"
    },
```

## Installation du projet

```bash
git clone git@github.com:mickael-Bula/test_generator.git
cd test_generator
composer install
```

## Utiliser le projet

```bash
# Pour tester toute une classe (comportement par défaut) :
php bin/console app:generate-test src\Service\VatCalculator.php

# Pour cibler une méthode précise (forme longue ou courte)
php bin/console app:generate-test src\Service\VatCalculator.php --method calculateNetAmountFromGross # ou -m calculateNetAmountFromGross
```

## Fonctionnalités

Lors du test d'une méthode, ce dernier est ajouté au fichier de la classe testée si elle existe, sinon il est créé.
Avant de valider les modifications, il incombe au développeur de vérifier les ajouts et suppressions avant de commiter.

Un **Arbre Syntaxique Abstrait** (AST) est fourni en contexte de chaque requête au LLM, 
afin d'offir une vue complète de la structure du code.
Il s'agit d'un fichier texte léger qui récapitule la structure des classes, 
interfaces et méthodes du projet (la signature des méthodes sans leur corps).

Avec ce **repo-map**, le LLM est en mesure de résoudre les dépendances de toute classe fournie à la commande de test.

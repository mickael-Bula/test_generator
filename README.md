# Outil de génération de tests unitaires par un LLM

Le présent projet vise à créer un bundle de génération de tests unitaires par un LLM.

Dans un souci de flexibilité, le LLM configuré peut être appelé localement via Ollama, ou à distance via l'API OpenRouter.

La déclaration du LLM se fait dans les variables d'environnement.

### Configuration pour un LLM local (Ollama)

Pour choisir un LLM local, il faut déclarer les variables suivantes :

```
LLM_PROVIDER_SERVICE="App\Llm\OllamaClient"
MODEL="qwen2.5-coder:14b" # ou tout autre modèle préalablement chargé dans Ollama
```

Il faut également spécifier l'adresse réseau de l'instance Ollama (en incluant le protocole http://) :

```
OLLAMA_API_URL="http://localhost:11434" # pour contéacté l'instance Ollama installé sur le poste qui accueille le projet
# OLLAMA_API_URL="http://192.168.1.XX:11434" # Pour contacter l'instance Ollama installé sur un serveur dédié (réseau local ici)
```
### Configuration pour un LLM distant (OpenRouter)

Pour externaliser la génération via OpenRouter, il faut configurer ces variables :

```
LLM_PROVIDER_SERVICE="App\Llm\OpenRouterClient"
MODEL="google/gemini-2.5-flash-lite"
```

Dans ce second cas, il faut obligatoirement fournir une clé d'API valide :

```
OPENROUTER_API_KEY=sk-or-v1-XXXXX
```

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

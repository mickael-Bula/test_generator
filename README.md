# Outil de génération de tests unitaires par un LLM

Le présent projet vise à créer un outil de génération automatique de tests unitaires PHPUnit en s'appuyant sur un modèle de langage (LLM).

Dans un souci de flexibilité, l'application s'appuie sur le client **Symfony AI** (`symfony/ai-bundle`). 
Il est ainsi possible d'interconnecter facilement plusieurs fournisseurs de LLM :
- **Ollama** (modèles locaux)
- **Anthropic** (Claude)
- **Google Gemini**
- **OpenRouter**
- **OpenAI** (ChatGPT)

L'ajout ou le retrait d'un provider s'effectue simplement par l'installation du paquet dédié via Composer 
(ex : `composer require symfony/ai-open-router-platform`). 
Les configurations spécifiques à chaque fournisseur s'effectuent ensuite directement dans le dossier `config/packages/` 
et via les variables d'environnement (`.env` / `.env.local`).

---

## Configuration des fournisseurs (LLM Providers)

Grâce à **Symfony AI**, vous pouvez basculer d'un fournisseur à un autre ou en combiner plusieurs selon vos besoins.

### 1. Configuration pour un LLM local (Ollama)

Pour utiliser un modèle exécuté en local via Ollama :

```env
LLM_PROVIDER="ollama"
LLM_MODEL="qwen2.5-coder:14b" # Ou tout autre modèle chargé dans l'instance Ollama
OLLAMA_HOST="http://localhost:11434"
```

>Note : Si Ollama est hébergé sur une machine distante sur le réseau local, 
> remplacer localhost par l'adresse IP (ex : http://192.168.1.XX:11434).

### 2. Configuration pour l'API Google Gemini

Pour utiliser directement l'API native de Google Gemini :

```env
LLM_PROVIDER="gemini"
LLM_MODEL="gemini-flash-latest" # Alias stable pointant vers la version Flash la plus récente
GEMINI_API_KEY="AIzaSyXXXXX"
```

> Note : Une clé API peut être générée sur [Google Studio](https://aistudio.google.com/).

### 3. Configuration pour un agrégateur de LLM distants (OpenRouter)

Pour externaliser la génération via la plateforme OpenRouter :

```env
LLM_PROVIDER="openrouter"
LLM_MODEL="google/gemini-2.0-flash-001"
OPENROUTER_API_KEY="sk-or-v1-XXXXX"
```

> Note : Une clé API doit être générée sur [OpenRouter](https://openrouter.ai/).

### 4. Configuration pour Anthropic ou OpenAI

```bash
# Anthropic
ANTHROPIC_API_KEY="sk-ant-api03-XXXXX"

# OpenAI
OPENAI_API_KEY="sk-proj-XXXXX"
```

>NOTE : Les paramètres de chaque provider sont déclarés et personnalisables 
> dans leurs fichiers de configuration respectifs sous `config/packages/ai.yaml `
> (ou dans des fichiers dédiés par plateforme).

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

Pour faciliter l'utilisation des outils de qualité de code (PhpStan, PHP-CS-Fixer, PhpUnit), 
il est possible d'ajouter des raccourcis dans le fichier composer.json :

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

## Ajouter un fichier de contexte pour les tests

Afin d'obtenir un test très précis, il est possible de fournir un fichier de spécification (contexte) au format Markdown. 
Ce fichier décrit les règles métier et les scénarios attendus.

Un fichier de contexte peut être ajouté lors de la génération à l'aide de l'option `**--spec**`.

### 1. Génération du squelette de spécification

Pour vous aider à rédiger ce fichier, 
vous pouvez générer automatiquement un modèle (template) pour une classe entière ou une méthode ciblée :

```bash
# Générer le fichier de spécification pour toute une classe :
php bin/console app:test-spec App\Service\VatCalculator

# Générer le fichier de spécification ciblant une méthode précise :
php bin/console app:test-spec App\Service\VatCalculator --method=applyDiscountAndCalculateGross
```

### 2. Renseignement du fichier de contexte

Une fois le fichier Markdown généré dans votre dossier de spécifications (ex : tests/Specs/), 
éditez-le en respectant les étapes suivantes :

1. Supprimer les crochets générés automatiquement dans le squelette.
2. Renseigner le nom de la méthode testée pour chaque scénario.
3. Remplir la structure BDD (Given / When / Then ou Étant donné / Lorsque / Alors) avec les prérequis et les résultats attendus.

Correspondance des concepts BDD & Tests :

- Given (Étant donné) : Préparation des données, instanciation et configuration des Mocks (Arrange)
- When (Lorsque) : Exécution de la méthode à tester (Act)
- Then (Alors) : Contrôle du résultat ou exceptions levées via PHPUnit (Assert)

L'avantage de cette approche est que la structure BDD Given / When / Then 
correspond exactement au pattern classique d'un test unitaire : Arrange / Act / Assert.

| BDD                     | Test unitaire                                                                   |
|-------------------------|---------------------------------------------------------------------------------|
| **Given** (Étant donné) | Préparation des données, instanciation et configuration des Mocks (**Arrange**) |
| **When** (Lorsque)      | Exécution de la méthode à tester (**Act**)                                      |
| **Then** (Alors)        | Contrôle du résultat ou exceptions levées via PHPUnit (**Assert**)              |

### 3. Exécution de la génération avec le fichier de contexte

Lancez la commande de génération en spécifiant le chemin vers votre fichier de contexte à l'aide des commandes idoines :

```bash
# Génération pour toute la classe en passant la spécification :
php bin/console app:generate-test App\Service\VatCalculator --spec=tests/Specs/VatCalculatorSpec.md

# Génération pour une méthode spécifique avec sa spécification :
php bin/console app:generate-test App\Service\VatCalculator --method=applyDiscountAndCalculateGross --spec=tests/Specs/VatCalculator_applyDiscountAndCalculateGrossSpec.md
```

## Fonctionnalités & Architecture

Lors du test d'une méthode, le code de test produit est automatiquement injecté dans le fichier de test de la classe ciblée s'il existe déjà, ou le crée si nécessaire.

Note : Avant de valider les modifications, il incombe au développeur de relire et de vérifier le code généré avant de le commiter.

### Utilisation de la Repo-Map (AST)

Un Arbre Syntaxique Abstrait (AST) est automatiquement fourni en contexte de chaque requête au LLM 
afin d'offrir une vue globale et fidèle de la structure du code.

Il s'agit d'une cartographie légère récapitulant les namespaces, classes, interfaces et signatures de méthodes du projet 
(sans leur corps exécutable). Grâce à cette Repo-Map, le LLM résout tout seul les dépendances requises, 
instancie les Mocks appropriés dans setUp() et utilise les bons types sans hallucination.

### Optimisation et gestion du cache de la Repo-Map

Afin d'optimiser les performances et d'éviter un re-parsing coûteux des fichiers source à chaque requête, 
la **Repo-Map** est mise en cache de manière automatique.

Son invalidation est gérée de manière dynamique : 
une empreinte (hash MD5) basée sur les chemins et les dates de modification (`mtime`) des fichiers du dossier `src/` 
est calculée à chaque exécution. 
Si le code source n'a subi aucune modification, la structure est immédiatement restituée depuis le cache.

Si vous souhaitez forcer la régénération complète du Repo-Map 
(par exemple après un changement d'environnement ou une réorganisation majeure), 
vous pouvez réinitialiser le pool de cache applicatif à l'aide de la commande Symfony dédiée :

```bash
php bin/console cache:pool:clear cache.app
```

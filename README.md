# Outil de génération de tests unitaires par un LLM

Le présent projet vise à créer un outil de génération automatique de tests unitaires PHPUnit en s'appuyant sur un modèle de langage (LLM).

Dans un souci de flexibilité, l'application s'appuie sur le composant **Symfony AI Platform** (`symfony/ai-platform`).
Il est ainsi possible d'interconnecter facilement plusieurs fournisseurs de LLM via leurs ponts (*bridges*) respectifs :
- **Ollama** (modèles locaux)
- **Anthropic** (Claude)
- **Google Gemini**
- **OpenRouter**
- **OpenAI** (ChatGPT)

L'ajout ou le retrait d'un provider s'effectue simplement par l'installation du pont dédié via Composer
(ex : `composer require symfony/ai-gemini-platform`).
La gestion des plateformes est centralisée via une fabrique personnalisée (`AiPlatformFactory`)
et configurée dans `config/services.yaml` ainsi que les variables d'environnement (`.env` / `.env.local`).

---

## Configuration des fournisseurs (LLM Providers)

Grâce à **Symfony AI Platform**,
vous pouvez basculer d'un fournisseur à un autre ou en combiner plusieurs selon vos besoins en modifiant la variable `LLM_PROVIDER`.
>TODO : peu clair, car donne l'impression qu'il est possible de combiner plusieurs fournisseurs. Or, un seul peut être actif.

### 1. Configuration pour un LLM local (Ollama)

Pour utiliser un modèle exécuté en local via Ollama :

```env
LLM_PROVIDER="ollama"
LLM_MODEL="qwen2.5-coder:14b" # Ou tout autre modèle chargé dans l'instance Ollama
OLLAMA_HOST="http://localhost:11434"
```

> **Note :** Si Ollama est hébergé sur une machine distante sur le réseau local, 
> remplacer `localhost` par l'adresse IP (ex : `http://192.168.1.XX:11434`).

### 2. Configuration pour l'API Google Gemini

Pour utiliser directement l'API native de Google Gemini :

```env
LLM_PROVIDER="gemini"
LLM_MODEL="gemini-flash-latest" # Alias stable pointant vers la version Flash la plus récente
GEMINI_API_KEY="AIzaSyXXXXX"
```

> **Note :** Une clé API peut être générée sur [Google AI Studio](https://aistudio.google.com/).

### 3. Configuration pour un agrégateur de LLM distants (OpenRouter)

Pour externaliser la génération via la plateforme OpenRouter :

```env
LLM_PROVIDER="openrouter"
LLM_MODEL="google/gemini-2.0-flash-001"
OPENROUTER_API_KEY="sk-or-v1-XXXXX"
```

> **Note :** Une clé API doit être générée sur [OpenRouter](https://openrouter.ai/).

### 4. Configuration pour Anthropic ou OpenAI

```env
# Pour Anthropic
LLM_PROVIDER="anthropic"
LLM_MODEL="claude-3-5-sonnet-latest"
ANTHROPIC_API_KEY="sk-ant-api03-XXXXX"

# Pour OpenAI
LLM_PROVIDER="openai"
LLM_MODEL="gpt-4o-mini"
OPENAI_API_KEY="sk-proj-XXXXX"
```

### 5. Architecture technique de l'intégration

L'intégration repose sur trois éléments clés :

1. `App\Service\AiPlatformFactory` : Instancie et configure les différents providers Symfony AI (`Platform`) 
    avec leurs dépendances (clés d'API, `HttpClientInterface`).
2. `config/services.yaml` : Déclare et étiquette chaque plateforme pour alimenter un `ServiceLocator` 
    (`tags: [{ name: 'ai.platform', index: 'gemini' }]`).
3. `App\Llm\SymfonyAiClient` : Client unifié injectant le `ServiceLocator` pour consommer les plateformes 
    et parser la réponse grâce au DTO `GeneratedTestResult` et au `SerializerInterface`.

---

## Initialisation et installation du projet

### Création initiale du projet et dépendances

```bash
C:\laragon\www
symfony new test_generator
cd test_generator

# Dépendances de développement et de qualité de code
composer require --dev symfony/test-pack
composer require --dev symfony/maker-bundle
composer require --dev phpstan/phpstan
composer require --dev friendsofphp/php-cs-fixer

# Composants Symfony requis
composer require symfony/http-client
composer require symfony/process
composer require symfony/serializer
composer require nikic/php-parser

# Composant de base Symfony AI Platform
composer require symfony/ai-platform

# Ponts (Bridges) officiels supportés
composer require symfony/ai-open-ai-platform
composer require symfony/ai-anthropic-platform
composer require symfony/ai-ollama-platform
composer require symfony/ai-gemini-platform
```

### Raccourcis de qualité de code dans `composer.json`

Pour faciliter l'utilisation des outils de qualité (PHPStan, PHP-CS-Fixer), ajoutez les scripts suivants dans votre `composer.json` :

```json
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
}
```
>TODO : comment corriger l'erreur signalée sur "scripts": et qui indique <value> expected, got ':' ?

### Installation depuis le dépôt Git

```bash
git clone git@github.com:mickael-Bula/test_generator.git
cd test_generator
composer install
```

---

## Utiliser la commande de génération (`app:generate-test`)

La commande s'utilise en fournissant en argument la classe à tester.

### 1. Ciblage de la classe ou de la méthode

La classe peut être ciblée de plusieurs manières (chemin relatif, FQCN/namespace ou nom court) :

```bash
# 1. Par chemin relatif vers le fichier :
php bin/console app:generate-test src/Service/VatCalculator.php

# 2. Par Namespace / FQCN :
php bin/console app:generate-test "App\Service\VatCalculator"

# 3. Par nom court de classe (recherche automatique dans le dossier src/, avec ou sans ::class) :
php bin/console app:generate-test VatCalculator
php bin/console app:generate-test VatCalculator::class

# 4. Pour cibler une méthode précise (forme courte -m ou longue --method) :
php bin/console app:generate-test VatCalculator -m calculateNetAmountFromGross
```

### 2. Spécification du type de test (Unitaire ou Fonctionnel)

```bash
# Par défaut ou explicite : Test unitaire
php bin/console app:generate-test "App\Service\VatCalculator"
php bin/console app:generate-test "App\Service\VatCalculator" -u
php bin/console app:generate-test "App\Service\VatCalculator" --unit

# Test fonctionnel
php bin/console app:generate-test "App\Controller\InvoiceController" -f
php bin/console app:generate-test "App\Controller\InvoiceController" --functional

# Erreur explicite si les deux options sont combinées :
php bin/console app:generate-test "App\Service\VatCalculator" -u -f
# [ERROR] Vous ne pouvez pas spécifier à la fois --unit (-u) et --functional (-f). 
```

---

## 📝 Guide des spécifications (BDD)

Afin d'obtenir un test très précis, 
il est possible de fournir un fichier de spécification rédigé en Markdown 
(approche **BDD** avec la syntaxe *Étant donné / Lorsque / Alors*).

L'avantage de cette approche est que la structure BDD correspond exactement 
au pattern classique d'un test unitaire **AAA (Arrange / Act / Assert)** :

| BDD                     | Test unitaire                                                                   |
|-------------------------|---------------------------------------------------------------------------------|
| **Given** (Étant donné) | Préparation des données, instanciation et configuration des Mocks (**Arrange**) |
| **When** (Lorsque)      | Exécution de la méthode à tester (**Act**)                                      |
| **Then** (Alors)        | Contrôle du résultat ou exceptions levées via PHPUnit (**Assert**)              |

### 1. Génération du squelette de spécification (`app:test-spec`)

Vous pouvez générer automatiquement un modèle (template) pré-rempli pour une classe entière ou une méthode ciblée :

```bash
# Générer le fichier de spécification pour toute une classe :
php bin/console app:test-spec VatCalculator

# Générer le fichier de spécification ciblant une méthode précise :
php bin/console app:test-spec VatCalculator --method=applyDiscountAndCalculateGross
```

### 2. Renseignement du fichier de contexte

Une fois le fichier créé dans votre dossier de spécifications (ex : `tests/Specs/VatCalculatorSpec.md`), 
éditez-le en respectant les étapes suivantes :
1. Supprimer les crochets générés automatiquement dans le squelette (`[ ... ]`).
2. Renseigner le nom de la méthode testée pour chaque scénario.
3. Remplir la structure BDD avec les prérequis et les résultats attendus.

### 3. Exécution de la génération avec le fichier de contexte (`--spec` / `-s`)

L'option `--spec` (ou `-s`) s'adapte à vos besoins :

#### A. Convention automatique (recommandé)
Si l'option est fournie sans valeur, 
la commande cherche automatiquement un fichier nommé `<NomDeLaClasse>Spec.md` dans le projet (ex : `VatCalculatorSpec.md`) :

```bash
php bin/console app:generate-test VatCalculator --spec
php bin/console app:generate-test VatCalculator -s
```

#### B. Chemin ou nom de fichier explicite
Vous pouvez spécifier un chemin relatif/absolu ou simplement le nom du fichier. 
L'extension `.md` est automatiquement ajoutée si elle est omise :

```bash
# Recherche automatique avec ajout implicite de l'extension .md :
php bin/console app:generate-test VatCalculator --spec=VatCalculatorSpec

# Génération en fournissant le chemin complet :
php bin/console app:generate-test VatCalculator --spec=tests/Specs/VatCalculatorSpec.md

# Cibler une méthode spécifique avec sa spécification dédiée :
php bin/console app:generate-test VatCalculator -m applyDiscountAndCalculateGross -s tests/Specs/VatCalculator_applyDiscountAndCalculateGross
```

#### C. Consigne sous forme de texte libre

Si vous souhaitez passer une consigne ponctuelle sans créer de fichier Markdown :

```bash
php bin/console app:generate-test VatCalculator --spec="S'assurer de lever une exception si le montant hors taxe est négatif"
```

---

## 📂 Arborescence recommandée du projet

Pour tirer le meilleur parti des conventions de l'outil, voici l'organisation conseillée au sein de votre projet Symfony :

```text
mon-projet-symfony/
├── config/
│   └── packages/
│       └── mika_test_generator.yaml   # Configuration (modèle LLM, options)
├── Resources/
│   └── spec-templates/                # (Optionnel) Vos surcharges personnalisées de templates
├── src/
│   └── Service/
│       └── VatCalculator.php          # Vos services métiers à tester
└── tests/
    ├── Service/
    │   └── VatCalculatorTest.php      # Tests générés par le projet
    └── Specs/                         # Fichiers de spécification Markdown (BDD)
        ├── VatCalculatorSpec.md
        └── TextFormatterSpec.md
```

### 📖 Documentation détaillée des spécifications

Pour en savoir plus sur la rédaction des scénarios BDD, le fonctionnement du nettoyage automatique des commentaires HTML (`SpecTemplateCleaner`) et consulter des exemples complets Avant / Après :  
👉 **[Consulter le guide complet de rédaction des spécifications](docs/specifications.md)**

---

## Architecture et Fonctionnalités avancées

### Injection de code et Boucle itérative

Lors du test d'une méthode, 
le code de test produit est automatiquement injecté dans le fichier de test de la classe ciblée s'il existe déjà, 
ou le crée si nécessaire.
* L'outil s'appuie sur un dossier `Dynamic/` pour conserver et isoler les essais du LLM.
* Une **boucle itérative** soumet le résultat de l'exécution de PHPUnit à chaque nouvel appel au LLM 
* pour corriger d'éventuelles erreurs jusqu'à l'obtention d'un test passant.

> **Note :** Avant de valider les modifications, 
> il incombe au développeur de relire et de vérifier le code généré avant de le commiter.

### Utilisation de la Repo-Map (AST)

Un Arbre Syntaxique Abstrait (AST) est automatiquement fourni en contexte de chaque requête au LLM 
afin d'offrir une vue globale et fidèle de la structure du code.

Il s'agit d'une cartographie légère récapitulant les namespaces, classes, interfaces et signatures de méthodes du projet 
(sans leur corps exécutable). Grâce à cette Repo-Map, le LLM résout tout seul les dépendances requises, 
instancie les Mocks appropriés dans `setUp()` et utilise les bons types sans hallucination.

#### Optimisation et gestion du cache de la Repo-Map

Afin d'optimiser les performances et d'éviter un re-parsing coûteux des fichiers source à chaque requête, 
la Repo-Map est mise en cache de manière automatique.

Son invalidation est gérée de manière dynamique : 
une empreinte (hash MD5) basée sur les chemins 
et les dates de modification (`mtime`) des fichiers du dossier `src/` est calculée à chaque exécution. 
Si le code source n'a subi aucune modification, la structure est immédiatement restituée depuis le cache.

Pour forcer la régénération complète du Repo-Map (par exemple après une réorganisation majeure), 
réinitialisez le pool de cache applicatif :

```bash
php bin/console cache:pool:clear cache.app
```

---

## Système de Skills (Directives Dynamiques)

Afin d'éviter d'alourdir le prompt principal et de préserver des performances optimales, 
l'outil s'appuie sur une architecture de **Skills ciblés** (situés dans `src/Resources/skills/`).

Un **Skill** est un fichier Markdown contenant des règles de qualité et des bonnes pratiques spécialisées 
(ex : tests de commandes Symfony, manipulation du système de fichiers).

### Fonctionnement
1. **Détection automatique :** Le `SkillResolver` analyse la classe cible (via son FQCN et son code source) avant l'envoi au LLM.
2. **Injection contextuelle :** Seules les directives pertinentes pour la classe à tester sont injectées à la volée dans le prompt système.

**Exemples de skills intégrés :**

```text
resources/skills/
├── phpunit_attributes.md  # Règles sur #[Test], #[CoversClass]
├── symfony_commands.md    # Règle : Force l'utilisation de `CommandTester` et la vérification des sorties console.
└── filesystem_tests.md    # Règle : Impose un dossier temporaire unique (bin2hex) dans setUp() et le nettoyage dans tearDown().
```

### Exemple de contenu d'un Skill (`symfony_commands.md`)

```md
# Skill : Symfony Command Testing Guidelines

Quand tu génères un test pour une classe qui hérite de `Symfony\Component\Console\Command\Command` :
1. **CommandTester :** Utilise EXCLUSIVEMENT `Symfony\Component\Console\Tester\CommandTester` pour exécuter la commande. 
   N'utilise JAMAIS `ReflectionMethod` ou de mocks manuels sur `InputInterface`/`OutputInterface`.
2. **Assertions :** Vérifie le code de retour (`Command::SUCCESS`) ET le contenu de la console avec `$commandTester->getDisplay()`.
3. **Filesystem :** Si la commande crée des fichiers, utilise `Symfony\Component\Filesystem\Filesystem` pour les vérifications et le nettoyage dans `tearDown()`.
```

### Mécanisme d'injection (`SkillResolver`)

```php
$skills = [];

// 1. Détection automatique : Est-ce une commande Symfony ?
if (is_subclass_of($fqcn, Command::class)) {
    $skills[] = file_get_contents($this->skillsDir . '/symfony_commands.md');
}

// 2. Détection : Est-ce un service interagissant avec le Système de Fichiers ?
if (str_contains($classCode, 'file_put_contents') || str_contains($classCode, 'Finder')) {
    $skills[] = file_get_contents($this->skillsDir . '/filesystem_tests.md');
}

// On injecte uniquement les skills pertinents dans le prompt système
$systemPrompt = $this->baseSystemPrompt;
if (!empty($skills)) {
    $systemPrompt .= "\n\n### RÈGLES DE QUALITÉ DÉDIÉES :\n" . implode("\n\n", $skills);
}
```

### Avantages de cette approche
1. **Modularité :** Le prompt de base reste très court, rapide et économe en tokens pour les classes simples (DTO, Calculateurs, Handlers).
2. **Précision chirurgicale :** Le LLM ne reçoit la règle `CommandTester` que lorsqu'il teste une commande Symfony.
3. **Évolutivité :** Il est très simple d'ajouter de nouvelles règles métier ou de qualité sous forme de fichiers Markdown isolés.

---

## Environnement et règles de tests du projet

* **Structure miroir :** Réplication exacte de la structure du dossier `src/` au sein du dossier `tests/`.
* **Tests unitaires (`tests/Unit/`) :** Isolation totale, absence de base de données, utilisation systématique des Mocks PHPUnit.
* **Qualité du code :** PHPStan au **Level 6** avec typage strict (`declare(strict_types=1);`).
* **Lisibilité :** Pattern **AAA (Arrange-Act-Assert)** systématique dans chaque test.
* **Tests d'intégration légers pour l'AST :** Pour les analyseurs AST (`RepoMapBuilder`) et le cache, 
    privilégier des tests d'intégration légers manipulant de vrais répertoires temporaires 
    plutôt que de mocker les parseurs internes de la bibliothèque (`PHP-Parser`), 
    garantissant ainsi une architecture de test robuste et maintenable.

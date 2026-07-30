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

>Note : Une clé API peut être générée sur [Google Studio](https://aistudio.google.com/).

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
# Pour Anthropic
LLM_PROVIDER="anthropic"
LLM_MODEL="claude-3-5-sonnet-latest"
ANTHROPIC_API_KEY="sk-ant-api03-XXXXX"

# Pour OpenAI
LLM_PROVIDER="openai"
LLM_MODEL="gpt-4o-mini"
OPENAI_API_KEY="sk-proj-XXXXX"
```

### 5. Architecture technique

L'intégration repose sur trois éléments clés :

1. `App\Service\AiPlatformFactory` : Instancie et configure les différents providers Symfony AI (`Platform`) 
    avec leurs dépendances (clefs d'API, `HttpClientInterface`).
2. `config/services.yaml` : Déclare et étiquette chaque plateforme pour alimenter un `ServiceLocator`
   (`tags: [{ name: 'ai.platform', index: 'gemini' }]`).
3. `App\Llm\SymfonyAiClient` : Client unifié injectant le `ServiceLocator` pour consommer les plateformes 
    et parser la réponse grâce au DTO `GeneratedTestResult` et au `SerializerInterface`.

---

## Création du projet

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

La commande s'utilise en fournissant en argument la classe à tester, 
qui peut être ciblée de plusieurs manières (chemin relatif, FQCN/namespace ou nom court) :

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

## Ajouter un fichier de contexte pour les tests

Afin d'obtenir un test très précis, il est possible de fournir un fichier de spécification (contexte) au format Markdown. 
Ce fichier décrit les règles métier et les scénarios attendus.

Le fichier de contexte peut être ajouté lors de la génération à l'aide de l'option `**--spec**` (ou `**-s**`).

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

Une fois le fichier Markdown généré dans votre dossier de spécifications (ex : `tests/Specs/`), 
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

L'option `**--spec**` (ou `**-s**`) s'adapte à vos besoins :

#### A. Convention automatique (recommandé)

Si l'option est fournie sans valeur, la commande cherche automatiquement un fichier nommé <NomDeLaClasse>Spec.md dans le projet (ex: VatCalculatorSpec.md) :

```bash
# Détection automatique du fichier VatCalculatorSpec.md :
php bin/console app:generate-test App\Service\VatCalculator --spec

# Utilisation du raccourci -s :
php bin/console app:generate-test App\Service\VatCalculator -s
```

#### B. Chemin ou nom de fichier explicite

Vous pouvez spécifier un chemin relatif/absolu ou simplement le nom du fichier. 
L'extension .md est optionnelle : elle est automatiquement ajoutée si elle est omise. 
Notez que seul le format Markdown (.md) est pris en charge pour les fichiers de spécification.

```bash
# Recherche automatique avec ajout implicite de l'extension .md (VatCalculator -> VatCalculatorSpec.md) :
php bin/console app:generate-test App\Service\VatCalculator --spec=VatCalculatorSpec

# Génération en fournissant le chemin complet :
php bin/console app:generate-test App\Service\VatCalculator --spec=tests/Specs/VatCalculatorSpec.md

# Cibler une méthode spécifique avec sa spécification dédiée :
php bin/console app:generate-test App\Service\VatCalculator -m applyDiscountAndCalculateGross -s tests/Specs/VatCalculator_applyDiscountAndCalculateGross
```

#### C. Texte libre instantané

Si vous souhaitez passer une consigne ponctuelle sans créer de fichier Markdown :

```bash
# Passage d'une consigne sous forme de texte brut :
php bin/console app:generate-test App\Service\VatCalculator --spec="S'assurer de lever une exception si le montant hors taxe est négatif"
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

## Système de Skills (Directives Dynamiques)

Afin d'éviter d'alourdir le prompt principal et de préserver des performances optimales, 
l'outil s'appuie sur une architecture de **Skills ciblés** (situés dans `src/Resources/skills/`).

Un **Skill** est un fichier Markdown contenant des règles de qualité et des bonnes pratiques spécialisées 
(ex : tests de commandes Symfony, manipulation du système de fichiers).

### Fonctionnement :
- **Détection automatique :** Le `SkillResolver` analyse la classe cible (via son FQCN et son code source) avant l'envoi au LLM.
- **Injection contextuelle :** Seules les directives pertinentes pour la classe à tester sont injectées à la volée dans le prompt système.

**Exemples de skills intégrés :**

```
resources/skills/
├── phpunit_attributes.md  # Règles sur #[Test], #[CoversClass]
├── symfony_commands.md    # Règle : Force l'utilisation de `CommandTester` et la vérification des codes de retour/sorties console pour les commandes.
└── filesystem_tests.md    # Règle : Impose la création d'un dossier temporaire unique (`bin2hex(random_bytes(8))`) dans `setUp()` et son nettoyage systématique dans `tearDown()`.
```

### Détail d'un fichier Skill

```md
# Skill : Symfony Command Testing Guidelines

Quand tu génères un test pour une classe qui hérite de `Symfony\Component\Console\Command\Command` :
1. **CommandTester :** Utilise EXCLUSIVEMENT `Symfony\Component\Console\Tester\CommandTester` pour exécuter la commande. 
   N'utilise JAMAIS `ReflectionMethod` ou de mocks manuels sur `InputInterface'/`OutputInterface`.
2. **Assertions :** Vérifie le code de retour (`Command::SUCCESS`) 
   ET le contenu de la console avec `$commandTester->getDisplay()`.
3. **Filesystem :** Si la commande crée des fichiers, 
   utilise `Symfony\Component\Filesystem\Filesystem` pour les vérifications et le nettoyage dans `tearDown()`.
```

### Injection des Skills

L'astuce consiste à n'injecter le Skill que lorsque c'est pertinent (injections conditionnelles).

Dans la classe **SkillResolver**, on détecte le type de classe à tester avant de construire le prompt final :

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

### Intérêt de cette approche

1. **Modularité** : On conserve un prompt de base très court,
   rapide et économe en tokens pour les classes simples (DTO, Calculateurs, Handlers).

2. **Précision chirurgicale** : Le LLM ne reçoit la règle CommandTester que lorsqu'il teste une commande Symfony.

3. **Évolutivité** : Les utilisateurs du bundle pourront ajouter leurs propres fichiers de règles dans leur projet
   (ex : config/packages/generate_test/skills/my_custom_rules.md)
   pour adapter la génération de tests à leurs propres standards d'entreprise.

## Règles de tests

Pour les analyseurs d'AST (RepoMapBuilder) et le cache avec système de fichiers, 
privilégier des tests d'intégration légers avec des répertoires temporaires réels 
plutôt que de mocker les parseurs internes de la bibliothèque (ce qui est difficile à maintenir).
Utiliser le parsing AST original (PHP-Parser) plutôt que des mocks est la décision d'architecture de test 
la plus pragmatique et la plus solide, évitant d'avoir à mocker ParserFactory, Parser, NodeTraverser, NameResolver, etc.

## Description de l'environnement de test

- Tests unitaires dans tests/Unit/ : isolation totale, pas de DB, mocks phpUnit
- Réplication de la structure de projet du dossier src/ dans le dossier tests/
- PHPStan Level 6 — typage strict (évolution possible vers Level 8 ou 10 ?)
- Pattern AAA (Arrange-Act-Assert) dans chaque test
- Utilsation d'un dossier Dynamic pour conserver les essais du LLM avant validation par phpUnit
- boucle itérative pour générer les tests, avec soumission du résultat de phpUnit à chaque nouvel appel


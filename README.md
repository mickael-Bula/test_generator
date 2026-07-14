# Création du projet

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

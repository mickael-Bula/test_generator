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


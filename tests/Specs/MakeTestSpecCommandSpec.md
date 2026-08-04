<!--
================================================================================
CONSIGNES POUR LA RÉDACTION DE LA SPÉCIFICATION :
1. Remplacez toutes les variables {variableName} par vos vraies valeurs métiers.
2. Supprimez les scénarios, méthodes ou sections non utilisés.
3. Conservez la structure "Étant donné / Lorsque / Alors".
================================================================================
-->

# Spécification de test : MakeTestSpecCommand

## Contexte & Directives du test
<!-- Préciser ici le contexte technique et métier global pour la classe à tester -->
- **Service ciblé :** `MakeTestSpecCommand`
- **Mocks & Dépendances :**
    - Mocker `InputInterface` et `OutputInterface` pour simuler l'exécution en console.
    - Le répertoire temporaire de test doit être injecté via `$projectDir`.
- **Prérequis métiers :**
    - Si aucun template n'est présent dans `$projectDir/spec-templates/`, la commande échoue avec `Command::FAILURE`.
    - Si l'option `--method` est fournie, le fichier `test_spec_template.md` doit être chargé.
    - Si aucune méthode n'est fournie, le fichier `test_spec_class_template.md` doit être chargé.

---

## Méthode : execute

### Scénario 1 : Génération réussie d'une spécification globale de classe
- **Étant donné** un dossier `$projectDir` contenant `spec-templates/test_spec_class_template.md` avec le jeton `{className}`
- **Lorsque** j'exécute la commande `app:test-spec` avec l'argument `class = "App\Service\VatCalculator"` sans option `--method`
- **Alors** le code de retour doit être `Command::SUCCESS`
- **Et** le fichier `tests/Specs/VatCalculatorSpec.md` doit être créé et contenir "VatCalculator"

### Scénario 2 : Génération réussie d'une spécification ciblée sur une méthode
- **Étant donné** un dossier `$projectDir` contenant `spec-templates/test_spec_template.md` avec les jetons `{className}` et `{methodName}`
- **Lorsque** j'exécute la commande `app:test-spec` avec l'argument `class = "VatCalculator"` et l'option `--method = "calculateVat"`
- **Alors** le code de retour doit être `Command::SUCCESS`
- **Et** le fichier `tests/Specs/VatCalculator_calculateVatSpec.md` doit être créé et contenir "VatCalculator" et "calculateVat"

### Scénario 3 : Échec de la commande en cas de template inexistant
- **Étant donné** un dossier `$projectDir` ne contenant pas le répertoire `spec-templates`
- **Lorsque** j'exécute la commande `app:test-spec` avec l'argument `class = "VatCalculator"`
- **Alors** le code de retour doit être `Command::FAILURE`
- **Et** un message d'erreur indiquant l'absence du template doit être affiché dans la sortie console

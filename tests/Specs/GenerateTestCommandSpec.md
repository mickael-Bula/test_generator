# Spécification BDD pour GenerateTestCommand (`app:generate-test`)

## Configuration de la commande
- **Étant donné** la commande `app:generate-test`
- **Alors** elle doit être enregistrée avec l'argument obligatoire `class` et les options optionnelles `method` (`-m`), `model` et `spec` (`-s`).

## Scénario 1 : Échec si la classe ou le fichier cible n'existe pas
- **Étant donné** une entrée utilisateur pour l'argument `class` que `ClassResolver` ne peut pas résoudre ou dont le fichier n'existe pas sur le disque
- **Lorsque** l'on exécute la commande
- **Alors** un message d'erreur est affiché à l'utilisateur
- **Et** la commande s'arrête immédiatement en retournant le code de statut `Command::FAILURE` (`1`).

## Scénario 2 : Génération d'un nouveau fichier de test avec succès
- **Étant donné** un fichier de classe PHP valide et l'absence de fichier de test préexistant
- **Et** l'exécution réussie de `SpecResolver` et `TestGenerator`
- **Lorsque** l'on exécute la commande
- **Alors** le dossier cible (`tests/...`) est créé si nécessaire
- **Et** le fichier de test (`<ClassName>Test.php`) est écrit sur le disque avec le namespace et le nom de classe correctement réajustés
- **Et** la commande affiche un message de succès et retourne `Command::SUCCESS` (`0`).

## Scénario 3 : Fichier de test existant et annulation par l'utilisateur
- **Étant donné** un fichier de test qui existe déjà sur le disque pour la classe ciblée (sans option `--method`)
- **Et** l'utilisateur choisit **Non** (`false`) lors de la confirmation de fusion interactive
- **Lorsque** l'on exécute la commande
- **Alors** un message indique que la génération est annulée pour préserver les tests existants
- **Et** le fichier n'est pas modifié et la commande retourne `Command::SUCCESS` (`0`).

## Scénario 4 : Fusion avec un fichier de test existant propre
- **Étant donné** un fichier de test existant sans modifications Git en cours (`git status` propre)
- **Et** l'utilisateur confirme la fusion
- **Lorsque** l'on exécute la commande
- **Alors** les en-têtes du fichier existant sont temporairement préparés pour le LLM (`App\Tests\Dynamic`)
- **Et** le code fusionné retourné par `TestGenerator` a ses en-têtes rétablis vers le namespace et la classe cibles définitifs
- **Et** le fichier est mis à jour sur le disque avec un message d'avertissement de sécurité Git.

## Scénario 5 : Interruption si le fichier de test existant contient des modifications Git non commitées
- **Étant donné** un fichier de test existant modifié localement (`git status --porcelain` retourne une sortie non vide)
- **Et** l'utilisateur refuse d'écraser les modifications temporaires lors de la demande de confirmation
- **Lorsque** l'on exécute la commande
- **Alors** un avertissement est affiché et la commande s'arrête en retournant `Command::FAILURE` (`1`).

## Scénario 6 : Gestion des exceptions du générateur ou du LLM
- **Étant donné** que `TestGenerator::generateForClass()` lève une `TestCorrectionException` ou une `RuntimeException` (ex: échec de correction PHPUnit ou erreur de Repo-Map)
- **Lorsque** l'on exécute la commande
- **Alors** le message d'erreur de l'exception est affiché dans le terminal
- **Et** la commande s'arrête proprement en retournant `Command::FAILURE` (`1`).

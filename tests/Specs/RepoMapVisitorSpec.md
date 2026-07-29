# Spécification BDD pour RepoMapVisitor

## Scénario 1 : Extraction du FQCN et formatage des signatures de méthodes
- **Étant donné** un nœud `ClassLike` possédant un nom pleinement qualifié (`namespacedName`) et contenant des méthodes (`ClassMethod`) avec différentes visibilités (`public`, `protected`, `private`)
- **Et** des méthodes ayant des paramètres typés et un type de retour défini
- **Lorsque** le visiteur parcourt ces nœuds (`enterNode` puis `leaveNode`)
- **Alors** `getMap()` doit retourner un tableau contenant le FQCN de la classe en clé
- **Et** la valeur doit être la liste des signatures formatées sous la forme `  - <visibilité> function <nom>(<paramètres>): <typeRetour>`.

## Scénario 2 : Capture d'une classe ne contenant aucune méthode
- **Étant donné** un nœud `ClassLike` représentatif d'une classe sans aucune méthode
- **Lorsque** le visiteur parcourt ce nœud
- **Alors** `getMap()` doit contenir une entrée pour cette classe associée à un tableau vide de signatures.

## Scénario 3 : Réinitialisation du contexte lors de la sortie d'une classe
- **Étant donné** un nœud `ClassLike` quitté via `leaveNode()`
- **Et** un nœud `ClassMethod` visité en dehors de tout contexte de classe
- **Lorsque** le visiteur traite cette méthode
- **Alors** cette méthode ne doit pas être rattachée à la classe précédente ni être ajoutée dans la cartographie finale.

## Scénario 4 : Formatage des méthodes sans type de retour et paramètres non typés
- **Étant donné** une méthode sans type de retour explicite et possédant des paramètres sans type défini
- **Lorsque** le visiteur parcourt ce nœud
- **Alors** la signature produite ne doit pas contenir la partie `: <typeRetour>` et doit afficher uniquement les noms de variables précédés de `$` pour les arguments.

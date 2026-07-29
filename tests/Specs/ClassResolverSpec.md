# Spécification BDD pour App\Resolver\ClassResolver

## Scénario 1 : Résolution d'une classe par son FQCN exact
- **Étant donné** un FQCN complet valide existant dans le projet (ex : `App\Service\VatCalculator`)
- **Lorsque** la méthode `resolve()` est appelée avec ce FQCN
- **Alors** le résolveur doit retourner un tableau contenant le nom complet de la classe (`className`) et le chemin absolu vers son fichier source (`filePath`).

## Scénario 2 : Résolution d'une classe par son nom court unique
- **Étant donné** un nom court de classe unique dans l'application (ex : `VatCalculator`)
- **Lorsque** la méthode `resolve()` est appelée avec ce nom court
- **Alors** le résolveur doit retrouver la classe correspondante et retourner son `className` et son `filePath`.

## Scénario 3 : Échec en cas de classe introuvable
- **Étant donné** un nom de classe ou un FQCN qui n'existe pas dans le projet
- **Lorsque** la méthode `resolve()` est appelée avec cet identifiant
- **Alors** une exception `\InvalidArgumentException` doit être levée indiquant que la classe est introuvable.

## Scénario 4 : Échec en cas d'ambiguïté (doublons de noms courts)
- **Étant donné** un nom court de classe partagé par plusieurs classes dans différents namespaces
- **Lorsque** la méthode `resolve()` est appelée avec ce nom court ambigu
- **Alors** une exception `\RuntimeException` doit être levée pour demander à l'utilisateur de fournir le FQCN exact.

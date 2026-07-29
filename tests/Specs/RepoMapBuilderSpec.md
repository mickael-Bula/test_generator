# Spécification BDD pour RepoMapBuilder

## Scénario 1 : Extraction de la cartographie de fichiers PHP valides
- **Étant donné** un répertoire source contenant un fichier PHP valide définissant une classe et des méthodes
- **Lorsque** `buildMap()` est appelée avec ce répertoire
- **Alors** le texte retourné doit contenir le nom FQCN de la classe suivi de la signature de ses méthodes
- **Et** chaque classe doit être séparée par une ligne vide.

## Scénario 2 : Ignorer les classes sans méthodes
- **Étant donné** un répertoire source contenant une classe PHP sans aucune méthode (ex: DTO vide ou interface vide)
- **Lorsque** `buildMap()` est appelée
- **Alors** cette classe ne doit pas apparaître dans le résultat généré.

## Scénario 3 : Ignorer les fichiers PHP malformés ou invalides
- **Étant donné** un répertoire source contenant un fichier PHP présentant une erreur de syntaxe (parse error)
- **Lorsque** `buildMap()` est appelée
- **Alors** l'exception de parsing doit être ignorée silencieusement et le reste du répertoire doit être traité.

## Scénario 4 : Traitement d'un répertoire sans fichier PHP
- **Étant donné** un répertoire source ne contenant aucun fichier `.php`
- **Lorsque** `buildMap()` est appelée
- **Alors** une chaîne de caractères vide (`""`) doit être retournée.

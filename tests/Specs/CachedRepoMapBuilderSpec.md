# Spécification BDD pour CachedRepoMapBuilder

## Scénario 1 : Utilisation du cache lorsque le hash des fichiers n'a pas changé
- **Étant donné** un répertoire source contenant des fichiers PHP
- **Et que** les éléments en cache pour le hash et la carte existent (hit)
- **Et que** le hash en cache correspond exactement au hash actuel du répertoire
- **Lorsque** `buildMap()` est appelée avec ce répertoire
- **Alors** la carte stockée en cache doit être retournée directement sans appeler `RepoMapBuilder::buildMap()`.

## Scénario 2 : Régénération et mise en cache lorsque le cache est vide (miss)
- **Étant donné** un répertoire source contenant des fichiers PHP
- **Et que** le cache est vide (miss sur le hash ou la carte)
- **Lorsque** `buildMap()` est appelée
- **Alors** `RepoMapBuilder::buildMap()` doit être exécutée
- **Et** les nouveaux éléments (hash et contenu de la carte) doivent être sauvegardés dans le cache
- **Et** le contenu régénéré doit être retourné.

## Scénario 3 : Invalidation du cache lorsque les fichiers du répertoire ont été modifiés
- **Étant donné** un répertoire source dont le hash des fichiers a changé par rapport au hash stocké en cache
- **Lorsque** `buildMap()` est appelée
- **Alors** `RepoMapBuilder::buildMap()` doit être réexécutée pour mettre à jour le cache.

## Scénario 4 : Gestion d'un répertoire source inexistant
- **Étant donné** un chemin de répertoire source qui n'existe pas
- **Et que** le cache est vide
- **Lorsque** `buildMap()` est appelée
- **Alors** la carte doit être régénérée normalement (le hash calculé étant vide `""`).

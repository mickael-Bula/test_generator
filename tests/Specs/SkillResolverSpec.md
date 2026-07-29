# Spécification BDD pour SkillResolver

## Configuration du Resolver
- **Étant donné** que le dossier des skills natifs (`$nativeSkillsDir`) et le dossier des skills personnalisés (`$customSkillsDir`) peuvent être injectés au constructeur
- **Alors** le resolver doit charger les fichiers `.md` depuis les dossiers configurés.

## Scénario 1 : Détection des commandes Symfony
- **Étant donné** une classe avec l'attribut `#[AsCommand]`, ou étendant `Command`
- **Lorsque** l'on résout les skills via `resolveForClass()`
- **Alors** le résultat doit obligatoirement contenir le contenu exact du fichier `symfony_command.md`.

## Scénario 2 : Détection des opérations sur le système de fichiers
- **Étant donné** un code source contenant des fonctions I/O (`file_put_contents`, `mkdir`, etc.) ou un import de `Finder`/`Filesystem`
- **Lorsque** l'on résout les skills via `resolveForClass()`
- **Alors** le résultat doit obligatoirement contenir le contenu exact du fichier `filesystem_test.md`.

## Scénario 3 : Détection de l'utilisation de PhpParser
- **Étant donné** un code source contenant `PhpParser\` ou `use PhpParser`
- **Lorsque** l'on résout les skills via `resolveForClass()`
- **Alors** le résultat doit obligatoirement contenir le contenu exact du fichier `php-parser-v5.md`.

## Scénario 4 : Concaténation de multiples skills natifs et déduplication
- **Étant donné** un code déclenchant plusieurs détections (ex: Command + PhpParser + Filesystem)
- **Lorsque** l'on résout les skills via `resolveForClass()`
- **Alors** les contenus des différents skills doivent être concaténés dans l'ordre de résolution avec exactement deux sauts de ligne (`\n\n`) entre chaque skill
- **Et** chaque fichier de skill ne doit apparaître qu'une seule fois, même s'il est déclenché par plusieurs règles.

## Scénario 5 : Ordre et ajout des skills personnalisés du projet hôte
- **Étant donné** un dossier `$customSkillsDir` valide contenant des fichiers `.md`
- **Lorsque** l'on résout les skills via `resolveForClass()`
- **Alors** les contenus des skills personnalisés doivent être ajoutés à la fin du résultat, séparés par deux sauts de ligne (`\n\n`).

## Scénario 6 : Isolation et résilience en cas de fichiers ou dossiers manquants
- **Étant donné** un skill natif référencé mais dont le fichier `.md` n'existe pas sur le disque, ou un dossier `$customSkillsDir` invalide/inexistant
- **Lorsque** l'on résout les skills via `resolveForClass()`
- **Alors** aucune exception ne doit être levée et la méthode doit ignorer le fichier manquant (retourner une chaîne vide si aucun skill valide n'est trouvé).

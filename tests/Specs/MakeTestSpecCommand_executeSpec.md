# Spécification de Test pour : MakeTestSpecCommand::execute()

## Scénario 1 : Échec lorsque le fichier de template n'existe pas
- **Étant donné** aucun fichier de template dans le dossier templates
- **Lorsque** la commande est exécutée avec une classe valide
- **Alors** le code de retour doit être Command::FAILURE (1)
- **Et** l'affichage de la console doit contenir le message d'erreur indiquant que le template n'existe pas.

## Scénario 2 : Création automatique du dossier des specs s'il n'existe pas
- **Étant donné** un template valide dans le dossier templates
- **Et** un dossier tests/Specs qui n'existe pas encore dans le répertoire du projet
- **Lorsque** la commande est exécutée avec une classe valide
- **Alors** le dossier tests/Specs doit être automatiquement créé
- **Et** le fichier de spec doit y être écrit avec succès.

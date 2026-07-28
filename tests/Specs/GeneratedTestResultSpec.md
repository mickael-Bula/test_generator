# Spécification BDD pour GeneratedTestResult

## Scénario 1 : Nettoyage des fins de ligne Windows
- **Étant donné** un code contenant des retour à la ligne Windows (`\r\n` ou `\r`)
- **Lorsque** `getCleanTestCode()` est appelée
- **Alors** les retours à la ligne doivent être convertis au format Unix (`\n`).

## Scénario 2 : Suppression des balises Markdown PHP
- **Étant donné** un code entouré de balises Markdown ```php et ```
- **Lorsque** `getCleanTestCode()` est appelée
- **Alors** les balises Markdown doivent être supprimées et le code nettoyé avec `trim()`.

## Scénario 3 : Correction des doubles antislashs PHP
- **Étant donné** un code contenant des namespaces avec des doubles antislashs (ex: `App\\Entity\\User`)
- **Lorsque** `getCleanTestCode()` est appelée
- **Alors** les doubles antislashs doivent être remplacés par un simple antislash.

## Scénario 4 : Lecture de la propriété testCode
- **Étant donné** une instance de `GeneratedTestResult` avec un code brut
- **Lorsque** l'on accède à la propriété publique `testCode`
- **Alors** la valeur exacte passée au constructeur doit être retournée.

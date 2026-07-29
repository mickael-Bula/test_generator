# Spécification de Test pour la classe : VatCalculator

## Prérequis métiers
- Taux de TVA appliqué par défaut = 20%
- L'utilisateur de test doit avoir le rôle ROLE_ADMIN

---

## Méthode : applyDiscountAndCalculateGross

### Scénario 1 : Application d'une remise fixe en valeur sur le montant HT
- **Étant donné** un montant HT de `100.0`, une remise fixe de `20.0` ($isPercentage = false) et un taux de TVA par défaut de `20.0`%
- **Lorsque** j'appelle `applyDiscountAndCalculateGross(100.0, 20.0, false)`
- **Alors** le montant HT après remise est de `80.0`, la TVA de `16.0` et le résultat TTC retourné doit être exactement `96.0`

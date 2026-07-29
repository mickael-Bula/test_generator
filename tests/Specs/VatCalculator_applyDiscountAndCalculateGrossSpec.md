# Spécification de Test pour : VatCalculator::applyDiscountAndCalculateGross()

## Prérequis métiers (Optionnel)
- Taux de TVA appliqué par défaut = 20%

---

## Scénario 1 : Application d'une remise fixe en valeur sur le montant HT
- **Étant donné** un montant HT de `100.0`, une remise fixe de `20.0` ($isPercentage = false) et un taux de TVA par défaut de `20.0`%
- **Lorsque** j'appelle `applyDiscountAndCalculateGross(100.0, 20.0, false)`
- **Alors** le montant HT après remise est de `80.0`, la TVA de `16.0` et le résultat TTC retourné doit être exactement `96.0`

## Scénario 2 : Application d'une remise en pourcentage
- **Étant donné** un montant HT de `200.0`, une remise en pourcentage de `15.0`% ($isPercentage = true) et un taux de TVA par défaut de `20.0`%
- **Lorsque** j'appelle `applyDiscountAndCalculateGross(200.0, 15.0, true)`
- **Alors** le montant HT après remise est de `170.0`, la TVA de `34.0` et le résultat TTC retourné doit être exactement `204.0`

## Scénario 3 : Surcharge du taux de TVA par défaut
- **Étant donné** un montant HT de `100.0`, une remise en pourcentage de `10.0`% ($isPercentage = true) et un taux de TVA spécifique transmis de `10.0`%
- **Lorsque** j'appelle `applyDiscountAndCalculateGross(100.0, 10.0, true, 10.0)`
- **Alors** le montant HT après remise est de `90.0`, la TVA calculée à 10% est de `9.0` et le résultat TTC retourné doit être exactement `99.0`

## Scénario 4 : Levée d'exception pour montant HT initial négatif
- **Étant donné** un montant HT négatif de `-50.0`
- **Lorsque** j'appelle `applyDiscountAndCalculateGross(-50.0, 10.0, false)`
- **Alors** une exception `\InvalidArgumentException` doit être levée avec le message exact : `"Le montant HT ne peut pas être négatif."`

## Scénario 5 : Levée d'exception pour remise négative
- **Étant donné** un montant HT de `100.0` et une valeur de remise négative de `-5.0`
- **Lorsque** j'appelle `applyDiscountAndCalculateGross(100.0, -5.0, false)`
- **Alors** une exception `\InvalidArgumentException` doit être levée avec le message exact : `"La remise ne peut pas être négative."`

## Scénario 6 : Levée d'exception pour remise en pourcentage supérieure à 100%
- **Étant donné** un montant HT de `100.0`, une remise en pourcentage de `150.0`% ($isPercentage = true)
- **Lorsque** j'appelle `applyDiscountAndCalculateGross(100.0, 150.0, true)`
- **Alors** une exception `\InvalidArgumentException` doit être levée avec le message exact : `"La remise en pourcentage ne peut pas dépasser 100%."`

## Scénario 7 : Levée d'exception pour remise fixe supérieure au montant HT (solde négatif)
- **Étant donné** un montant HT de `50.0` et une remise fixe de `80.0` ($isPercentage = false)
- **Lorsque** j'appelle `applyDiscountAndCalculateGross(50.0, 80.0, false)`
- **Alors** une exception `\InvalidArgumentException` doit être levée avec le message exact : `"Le montant HT après remise ne peut pas être négatif."`

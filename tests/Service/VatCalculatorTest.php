<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\VatCalculator;
use PHPUnit\Framework\TestCase;

class VatCalculatorTest extends TestCase
{
    private VatCalculator $vatCalculator;
    private float $defaultVatRate = 20.0; // Taux par défaut pour les tests

    protected function setUp(): void
    {
        parent::setUp();
        // On crée une instance de notre service en lui passant le taux par défaut
        $this->vatCalculator = new VatCalculator($this->defaultVatRate);
    }

    /**
     * Teste le calcul du montant de la TVA avec le taux par défaut.
     */
    public function testCalculateVatAmountWithDefaultRate(): void
    {
        $netAmount = 100.0;
        $expectedVatAmount = 20.0; // 100 * 20% = 20

        $actualVatAmount = $this->vatCalculator->calculateVatAmount($netAmount);

        $this->assertEquals($expectedVatAmount, $actualVatAmount);
    }

    /**
     * Teste le calcul du montant de la TVA avec un taux personnalisé.
     */
    public function testCalculateVatAmountWithCustomRate(): void
    {
        $netAmount = 100.0;
        $customVatRate = 10.0; // Taux personnalisé
        $expectedVatAmount = 10.0; // 100 * 10% = 10

        $actualVatAmount = $this->vatCalculator->calculateVatAmount($netAmount, $customVatRate);

        $this->assertEquals($expectedVatAmount, $actualVatAmount);
    }

    /**
     * Teste le calcul du montant de la TVA avec des décimales.
     */
    public function testCalculateVatAmountWithDecimals(): void
    {
        $netAmount = 99.99;
        $expectedVatAmount = 19.998; // 99.99 * 20% = 19.998

        $actualVatAmount = $this->vatCalculator->calculateVatAmount($netAmount);

        // On vérifie que le résultat est arrondi à 2 décimales comme le fait la méthode
        $this->assertEquals(round($expectedVatAmount, 2), $actualVatAmount);
    }

    /**
     * Teste la levée d'une exception si le taux de TVA est négatif.
     */
    public function testCalculateVatAmountThrowsExceptionForNegativeRate(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le taux de TVA ne peut pas être négatif.');

        $this->vatCalculator->calculateVatAmount(100.0, -5.0);
    }

    /**
     * Teste le calcul du montant TTC (Toutes Taxes Comprises).
     */
    public function testCalculateGrossAmount(): void
    {
        $netAmount = 100.0;
        $expectedGrossAmount = 120.0; // 100 + (100 * 20%)

        $actualGrossAmount = $this->vatCalculator->calculateGrossAmount($netAmount);

        $this->assertEquals($expectedGrossAmount, $actualGrossAmount);
    }

    /**
     * Teste le calcul du montant TTC avec un taux personnalisé.
     */
    public function testCalculateGrossAmountWithCustomRate(): void
    {
        $netAmount = 100.0;
        $customVatRate = 10.0;
        $expectedGrossAmount = 110.0; // 100 + (100 * 10%)

        $actualGrossAmount = $this->vatCalculator->calculateGrossAmount($netAmount, $customVatRate);

        $this->assertEquals($expectedGrossAmount, $actualGrossAmount);
    }

    /**
     * Teste le calcul du montant TTC avec des décimales et arrondi.
     */
    public function testCalculateGrossAmountWithDecimals(): void
    {
        $netAmount = 99.99;
        $expectedGrossAmount = 119.99; // 99.99 + (99.99 * 20% = 19.998) -> 99.99 + 19.998 = 119.988, arrondi à 119.99

        $actualGrossAmount = $this->vatCalculator->calculateGrossAmount($netAmount);

        $this->assertEquals($expectedGrossAmount, $actualGrossAmount);
    }

    /**
     * Teste le calcul du montant HT à partir d'un montant TTC (Gross).
     */
    public function testCalculateNetAmountFromGross(): void
    {
        $grossAmount = 120.0;
        $expectedNetAmount = 100.0; // 120 / (1 + 20%/100)

        $actualNetAmount = $this->vatCalculator->calculateNetAmountFromGross($grossAmount, $this->defaultVatRate); // Utilise le taux par défaut

        $this->assertEquals($expectedNetAmount, $actualNetAmount);
    }

    /**
     * Teste le calcul du montant HT à partir d'un montant TTC avec un taux personnalisé.
     */
    public function testCalculateNetAmountFromGrossWithCustomRate(): void
    {
        $grossAmount = 110.0;
        $customVatRate = 10.0;
        $expectedNetAmount = 100.0; // 110 / (1 + 10%/100)

        $actualNetAmount = $this->vatCalculator->calculateNetAmountFromGross($grossAmount, $customVatRate);

        $this->assertEquals($expectedNetAmount, $actualNetAmount);
    }

    /**
     * Teste le calcul du montant HT à partir d'un montant TTC avec des décimales.
     */
    public function testCalculateNetAmountFromGrossWithDecimals(): void
    {
        $grossAmount = 119.99;
        $expectedNetAmount = 99.991666666667; // 119.99 / (1 + 0.20) = 99.991666666667

        $actualNetAmount = $this->vatCalculator->calculateNetAmountFromGross($grossAmount, $this->defaultVatRate);

        // On vérifie que le résultat est arrondi à 2 décimales comme le fait la méthode
        $this->assertEquals(round($expectedNetAmount, 2), $actualNetAmount);
    }

    /**
     * Teste la levée d'une exception si le montant TTC est négatif pour le calcul HT.
     */
    public function testCalculateNetAmountFromGrossThrowsExceptionForNegativeGrossAmount(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le montant TTC ne peut pas être négatif.');

        $this->vatCalculator->calculateNetAmountFromGross(-100.0, $this->defaultVatRate);
    }

    /**
     * Teste le calcul du montant de la TVA récupérable (remboursement) avec un taux valide.
     */
    public function testCalculateRefundWithValidRateReturnsCorrectAmount(): void
    {
        $amountTtc = 120.0;
        $taxRate = 20.0;
        $expectedRefund = 20.0; // 120 - (120 / (1 + 20%/100)) = 120 - 100 = 20

        $actualRefund = $this->vatCalculator->calculateRefund($amountTtc, $taxRate);

        $this->assertEquals($expectedRefund, $actualRefund, sprintf('Expected refund of %f, but got %f', $expectedRefund, $actualRefund));
    }

    /**
     * Teste le calcul du montant de la TVA récupérable avec un autre taux valide et des décimales.
     */
    public function testCalculateRefundWithDifferentValidRateAndDecimalsReturnsCorrectAmount(): void
    {
        $amountTtc = 115.50;
        $taxRate = 10.0;
        // Calcul attendu : 115.50 - (115.50 / (1 + 10%/100)) = 115.50 - (115.50 / 1.10) = 115.50 - 105.00 = 10.50
        $expectedRefund = 10.50;

        $actualRefund = $this->vatCalculator->calculateRefund($amountTtc, $taxRate);

        $this->assertEquals($expectedRefund, $actualRefund, sprintf('Expected refund of %f, but got %f', $expectedRefund, $actualRefund));
    }

    /**
     * Teste la levée d'une exception si le montant TTC est négatif pour le calcul de remboursement.
     */
    public function testCalculateRefundThrowsExceptionForNegativeAmountTtcWhenCalculatingRefund(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le montant TTC ne peut pas être négatif.');

        $this->vatCalculator->calculateRefund(-100.0, 20.0);
    }

    /**
     * Teste la levée d'une exception si le taux de taxe est invalide (inférieur ou égal à 0).
     */
    public function testCalculateRefundThrowsExceptionForZeroTaxRateWhenCalculatingRefund(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le taux de taxe doit être compris entre 0 et 100% (exclus).');

        $this->vatCalculator->calculateRefund(100.0, 0.0);
    }

    /**
     * Teste la levée d'une exception si le taux de taxe est invalide (supérieur ou égal à 100).
     */
    public function testCalculateRefundThrowsExceptionForHundredTaxRateWhenCalculatingRefund(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le taux de taxe doit être compris entre 0 et 100% (exclus).');

        $this->vatCalculator->calculateRefund(100.0, 100.0);
    }

    /**
     * Teste la levée d'une exception si le taux de taxe est invalide (supérieur à 100).
     */
    public function testCalculateRefundThrowsExceptionForTaxRateAboveHundredWhenCalculatingRefund(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le taux de taxe doit être compris entre 0 et 100% (exclus).');

        $this->vatCalculator->calculateRefund(100.0, 150.0);
    }

    /**
     * Teste le calcul du montant remboursable lorsque le montant TTC est 0.
     */
    public function testCalculateRefundWithZeroAmountTtcReturnsZero(): void
    {
        $amountTtc = 0.0;
        $taxRate = 20.0;
        $expectedRefund = 0.0;

        $actualRefund = $this->vatCalculator->calculateRefund($amountTtc, $taxRate);

        $this->assertEquals($expectedRefund, $actualRefund);
    }

    /**
     * Teste le calcul du montant remboursable avec des valeurs très basses.
     */
    public function testCalculateRefundWithVerySmallValuesReturnsCorrectAmount(): void
    {
        $amountTtc = 0.01;
        $taxRate = 20.0;
        // Calcul attendu : 0.01 - (0.01 / (1 + 0.20)) = 0.01 - (0.01 / 1.20) = 0.01 - 0.00833333... = 0.00166666...
        $expectedRefund = 0.0016666666666667;

        $actualRefund = $this->vatCalculator->calculateRefund($amountTtc, $taxRate);

        // La méthode arrondit à 2 décimales, donc on attend 0.00.
        $this->assertEquals(round($expectedRefund, 2), $actualRefund);
    }

    /**
     * Teste le calcul du montant HT à partir de 0 TTC.
     */
    public function testCalculateNetAmountFromGrossWithZeroGrossAmount(): void
    {
        $grossAmount = 0.0;
        $expectedNetAmount = 0.0;

        $actualNetAmount = $this->vatCalculator->calculateNetAmountFromGross($grossAmount, $this->defaultVatRate);

        $this->assertEquals($expectedNetAmount, $actualNetAmount);
    }

    /**
     * Teste le calcul du montant TTC à partir de 0 HT.
     */
    public function testCalculateGrossAmountWithZeroNetAmount(): void
    {
        $netAmount = 0.0;
        $expectedGrossAmount = 0.0;

        $actualGrossAmount = $this->vatCalculator->calculateGrossAmount($netAmount);

        $this->assertEquals($expectedGrossAmount, $actualGrossAmount);
    }

    /**
     * Teste le calcul du montant de TVA à partir de 0 HT.
     */
    public function testCalculateVatAmountWithZeroNetAmount(): void
    {
        $netAmount = 0.0;
        $expectedVatAmount = 0.0;

        $actualVatAmount = $this->vatCalculator->calculateVatAmount($netAmount);

        $this->assertEquals($expectedVatAmount, $actualVatAmount);
    }

    /**
     * Teste le calcul du montant remboursable à partir d'un montant TTC nul.
     */
    public function testCalculateRefundWithZeroTtcAmount(): void
    {
        $amountTtc = 0.0;
        $taxRate = 20.0;
        $expectedRefund = 0.0;

        $actualRefund = $this->vatCalculator->calculateRefund($amountTtc, $taxRate);

        $this->assertEquals($expectedRefund, $actualRefund);
    }

    /**
     * Scénario 1 : Application d'une remise fixe en valeur sur le montant HT.
     */
    public function testApplyDiscountAndCalculateGrossWithFixedDiscount(): void
    {
        // ÉTANT DONNÉ un montant HT de 100.0, une remise fixe de 20.0 ($isPercentage = false) et un taux de TVA par défaut de 20.0%
        $netAmount = 100.0;
        $discount = 20.0;
        $isPercentage = false;
        $vatRate = $this->defaultVatRate;

        // QUAND j'appelle applyDiscountAndCalculateGross(100.0, 20.0, false)
        $result = $this->vatCalculator->applyDiscountAndCalculateGross($netAmount, $discount, $isPercentage, $vatRate);

        // ALORS le montant HT après remise est de 80.0, la TVA de 16.0 et le résultat TTC retourné doit être exactement 96.0
        $expectedNetAmountAfterDiscount = 80.0;
        $expectedVatAmount = 16.0;
        $expectedGrossAmount = 96.0;

        // Le calcul de la TVA est interne à la méthode calculateGrossAmount, qui utilise calculateVatAmount.
        // On ne peut donc pas directement vérifier la TVA intermédiaire sans recréer la logique.
        // On se concentre sur le résultat final TTC.
        $this->assertEquals($expectedGrossAmount, $result, 'Le montant TTC final est incorrect.');

        // Pour vérifier les montants intermédiaires HT et TVA, on peut utiliser les méthodes publiques.
        // Ce n'est pas idéal car cela duplique la logique, mais cela permet une vérification plus fine si nécessaire.
        // En cas de doute, privilégier la vérification du résultat final avec la méthode testée (applyDiscountAndCalculateGross).
        $actualNetAmountAfterDiscount = $netAmount - $discount;
        $this->assertEquals($expectedNetAmountAfterDiscount, $actualNetAmountAfterDiscount, 'Le montant HT après remise est incorrect.');

        $actualVatAmount = $this->vatCalculator->calculateVatAmount($actualNetAmountAfterDiscount, $vatRate);
        $this->assertEquals($expectedVatAmount, $actualVatAmount, 'Le montant de TVA calculé est incorrect.');
    }

    /**
     * Scénario 2 : Application d'une remise en pourcentage.
     */
    public function testApplyDiscountAndCalculateGrossWithPercentageDiscount(): void
    {
        // ÉTANT DONNÉ un montant HT de 200.0, une remise en pourcentage de 15.0% ($isPercentage = true) et un taux de TVA par défaut de 20.0%
        $netAmount = 200.0;
        $discount = 15.0;
        $isPercentage = true;
        $vatRate = $this->defaultVatRate;

        // QUAND j'appelle applyDiscountAndCalculateGross(200.0, 15.0, true)
        $result = $this->vatCalculator->applyDiscountAndCalculateGross($netAmount, $discount, $isPercentage, $vatRate);

        // ALORS le montant HT après remise est de 170.0, la TVA de 34.0 et le résultat TTC retourné doit être exactement 204.0
        $expectedNetAmountAfterDiscount = 170.0;
        $expectedVatAmount = 34.0;
        $expectedGrossAmount = 204.0;

        $this->assertEquals($expectedGrossAmount, $result, 'Le montant TTC final est incorrect.');

        // Vérifications intermédiaires (optionnelles, comme dans le scénario 1)
        $actualNetAmountAfterDiscount = $netAmount * (1 - ($discount / 100));
        $this->assertEquals($expectedNetAmountAfterDiscount, $actualNetAmountAfterDiscount, 'Le montant HT après remise est incorrect.');

        $actualVatAmount = $this->vatCalculator->calculateVatAmount($actualNetAmountAfterDiscount, $vatRate);
        $this->assertEquals($expectedVatAmount, $actualVatAmount, 'Le montant de TVA calculé est incorrect.');
    }

    /**
     * Scénario 3 : Surcharge du taux de TVA par défaut.
     */
    public function testApplyDiscountAndCalculateGrossWithCustomVatRate(): void
    {
        // ÉTANT DONNÉ un montant HT de 100.0, une remise en pourcentage de 10.0% ($isPercentage = true) et un taux de TVA spécifique transmis de 10.0%
        $netAmount = 100.0;
        $discount = 10.0;
        $isPercentage = true;
        $customVatRate = 10.0;

        // QUAND j'appelle applyDiscountAndCalculateGross(100.0, 10.0, true, 10.0)
        $result = $this->vatCalculator->applyDiscountAndCalculateGross($netAmount, $discount, $isPercentage, $customVatRate);

        // ALORS le montant HT après remise est de 90.0, la TVA calculée à 10% est de 9.0 et le résultat TTC retourné doit être exactement 99.0
        $expectedNetAmountAfterDiscount = 90.0;
        $expectedVatAmount = 9.0;
        $expectedGrossAmount = 99.0;

        $this->assertEquals($expectedGrossAmount, $result, 'Le montant TTC final est incorrect.');

        // Vérifications intermédiaires (optionnelles)
        $actualNetAmountAfterDiscount = $netAmount * (1 - ($discount / 100));
        $this->assertEquals($expectedNetAmountAfterDiscount, $actualNetAmountAfterDiscount, 'Le montant HT après remise est incorrect.');

        $actualVatAmount = $this->vatCalculator->calculateVatAmount($actualNetAmountAfterDiscount, $customVatRate);
        $this->assertEquals($expectedVatAmount, $actualVatAmount, 'Le montant de TVA calculé est incorrect.');
    }

    /**
     * Scénario 4 : Levée d'exception pour montant HT initial négatif.
     */
    public function testApplyDiscountAndCalculateGrossThrowsExceptionForNegativeNetAmount(): void
    {
        // ÉTANT DONNÉ un montant HT négatif de -50.0
        $netAmount = -50.0;
        $discount = 10.0;
        $isPercentage = false;
        $vatRate = $this->defaultVatRate;

        // ALORS une exception \InvalidArgumentException doit être levée avec le message exact : "Le montant HT ne peut pas être négatif."
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le montant HT ne peut pas être négatif.');

        // QUAND j'appelle applyDiscountAndCalculateGross(-50.0, 10.0, false)
        $this->vatCalculator->applyDiscountAndCalculateGross($netAmount, $discount, $isPercentage, $vatRate);
    }

    /**
     * Scénario 5 : Levée d'exception pour remise négative.
     */
    public function testApplyDiscountAndCalculateGrossThrowsExceptionForNegativeDiscount(): void
    {
        // ÉTANT DONNÉ un montant HT de 100.0 et une valeur de remise négative de -5.0
        $netAmount = 100.0;
        $discount = -5.0;
        $isPercentage = false;
        $vatRate = $this->defaultVatRate;

        // ALORS une exception \InvalidArgumentException doit être levée avec le message exact : "La remise ne peut pas être négative."
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La remise ne peut pas être négative.');

        // QUAND j'appelle applyDiscountAndCalculateGross(100.0, -5.0, false)
        $this->vatCalculator->applyDiscountAndCalculateGross($netAmount, $discount, $isPercentage, $vatRate);
    }

    /**
     * Scénario 6 : Levée d'exception pour remise en pourcentage supérieure à 100%.
     */
    public function testApplyDiscountAndCalculateGrossThrowsExceptionForPercentageDiscountAboveHundred(): void
    {
        // ÉTANT DONNÉ un montant HT de 100.0, une remise en pourcentage de 150.0% ($isPercentage = true)
        $netAmount = 100.0;
        $discount = 150.0;
        $isPercentage = true;
        $vatRate = $this->defaultVatRate;

        // ALORS une exception \InvalidArgumentException doit être levée avec le message exact : "La remise en pourcentage ne peut pas dépasser 100%."
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La remise en pourcentage ne peut pas dépasser 100%.');

        // QUAND j'appelle applyDiscountAndCalculateGross(100.0, 150.0, true)
        $this->vatCalculator->applyDiscountAndCalculateGross($netAmount, $discount, $isPercentage, $vatRate);
    }

    /**
     * Scénario 7 : Levée d'exception pour remise fixe supérieure au montant HT (solde négatif).
     */
    public function testApplyDiscountAndCalculateGrossThrowsExceptionForFixedDiscountExceedingNetAmount(): void
    {
        // ÉTANT DONNÉ un montant HT de 50.0 et une remise fixe de 80.0 ($isPercentage = false)
        $netAmount = 50.0;
        $discount = 80.0;
        $isPercentage = false;
        $vatRate = $this->defaultVatRate;

        // ALORS une exception \InvalidArgumentException doit être levée avec le message exact : "Le montant HT après remise ne peut pas être négatif."
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le montant HT après remise ne peut pas être négatif.');

        // QUAND j'appelle applyDiscountAndCalculateGross(50.0, 80.0, false)
        $this->vatCalculator->applyDiscountAndCalculateGross($netAmount, $discount, $isPercentage, $vatRate);
    }
}

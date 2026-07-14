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
}

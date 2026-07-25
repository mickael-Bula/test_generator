<?php

declare(strict_types=1);

namespace App\Service;

readonly class VatCalculator
{
    /**
     * Symfony injecte automatiquement le paramètre global 'app.default_vat_rate'
     * grâce au "bind" configuré dans le fichier services.yaml.
     */
    public function __construct(
        private float $defaultVatRate,
    ) {
    }

    /**
     * Calcule le montant de la TVA pour un montant Hors Taxes (HT).
     * Si aucun taux n'est fourni, on utilise le taux par défaut du projet.
     */
    public function calculateVatAmount(float $netAmount, ?float $vatRate = null): float
    {
        $rate = $vatRate ?? $this->defaultVatRate;

        if ($rate < 0) {
            throw new \InvalidArgumentException('Le taux de TVA ne peut pas être négatif.');
        }

        return round(($netAmount * $rate) / 100, 2);
    }

    /**
     * Calcule le montant Toutes Taxes Comprises (TTC).
     */
    public function calculateGrossAmount(float $netAmount, ?float $vatRate = null): float
    {
        $vatAmount = $this->calculateVatAmount($netAmount, $vatRate);

        // On arrondit le résultat final à 2 décimales pour nettoyer le float PHP
        return round($netAmount + $vatAmount, 2);
    }

    /**
     * Calcule le montant HT à partir d'un montant TTC (Gross).
     *
     * @throws \InvalidArgumentException Si le montant TTC est inférieur à 0
     */
    public function calculateNetAmountFromGross(float $grossAmount, float $vatRate = 20.0): float
    {
        if ($grossAmount < 0) {
            throw new \InvalidArgumentException('Le montant TTC ne peut pas être négatif.');
        }

        return round($grossAmount / (1 + ($vatRate / 100)), 2);
    }

    /**
     * Calcule le montant de la TVA récupérable (remboursement) à partir d'un montant TTC.
     *
     * @param float $amountTtc Le montant toutes taxes comprises
     * @param float $taxRate   Le taux de taxe en pourcentage (ex: 20.0 pour 20%)
     *
     * @throws \InvalidArgumentException Si le montant TTC est négatif ou si le taux est invalide
     */
    public function calculateRefund(float $amountTtc, float $taxRate): float
    {
        if ($amountTtc < 0) {
            throw new \InvalidArgumentException('Le montant TTC ne peut pas être négatif.');
        }

        if ($taxRate <= 0 || $taxRate >= 100) {
            throw new \InvalidArgumentException('Le taux de taxe doit être compris entre 0 et 100% (exclus).');
        }

        // Formule : TVA = TTC - (TTC / (1 + (Taux / 100)))
        $baseHt = $amountTtc / (1 + ($taxRate / 100));

        return round($amountTtc - $baseHt, 2);
    }

    /**
     * Applique une remise sur le montant HT puis calcule le montant TTC final.
     *
     * @param float      $netAmount    Montant HT initial
     * @param float      $discount     Valeur de la remise
     * @param bool       $isPercentage Si true, $discount est un % (ex: 10 pour 10%). Si false, c'est un montant fixe en HT.
     * @param float|null $vatRate      Taux de TVA (utilise le taux par défaut si null)
     *
     * @throws \InvalidArgumentException si le montant HT est négatif, la remise invalide, ou si le total devient négatif
     */
    public function applyDiscountAndCalculateGross(
        float $netAmount,
        float $discount,
        bool $isPercentage = false,
        ?float $vatRate = null,
    ): float {
        if ($netAmount < 0) {
            throw new \InvalidArgumentException('Le montant HT ne peut pas être négatif.');
        }

        if ($discount < 0) {
            throw new \InvalidArgumentException('La remise ne peut pas être négative.');
        }

        if ($isPercentage && $discount > 100) {
            throw new \InvalidArgumentException('La remise en pourcentage ne peut pas dépasser 100%.');
        }

        $discountedNet = $isPercentage
            ? $netAmount * (1 - ($discount / 100))
            : $netAmount - $discount;

        if ($discountedNet < 0) {
            throw new \InvalidArgumentException('Le montant HT après remise ne peut pas être négatif.');
        }

        return $this->calculateGrossAmount($discountedNet, $vatRate);
    }
}

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
}

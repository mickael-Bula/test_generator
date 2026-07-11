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
        private float $defaultVatRate
    ) {}

    /**
     * Calcule le montant de la TVA pour un montant Hors Taxes (HT).
     * Si aucun taux n'est fourni, on utilise le taux par défaut du projet.
     */
    public function calculateVatAmount(float $netAmount, ?float $vatRate = null): float
    {
        $rate = $vatRate ?? $this->defaultVatRate;

        if ($rate < 0) {
            throw new \InvalidArgumentException("Le taux de TVA ne peut pas être négatif.");
        }

        return round(($netAmount * $rate) / 100, 2);
    }

    /**
     * Calcule le montant Toutes Taxes Comprises (TTC).
     */
    public function calculateGrossAmount(float $netAmount, ?float $vatRate = null): float
    {
        $vatAmount = $this->calculateVatAmount($netAmount, $vatRate);

        return $netAmount + $vatAmount;
    }
}

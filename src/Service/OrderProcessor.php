<?php

declare(strict_types=1);

namespace App\Service;

readonly class OrderProcessor
{
    public function __construct(
        private PaymentGateway $paymentGateway,
    ) {
    }

    public function process(float $amount): bool
    {
        return $this->paymentGateway->charge($amount, 'EUR');
    }
}

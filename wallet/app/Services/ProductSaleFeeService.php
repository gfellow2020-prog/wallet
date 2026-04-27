<?php

namespace App\Services;

class ProductSaleFeeService
{
    public const CASHBACK_RATE = 0.02;

    public const ADMIN_FEE_RATE = 0.01;

    public const SELLER_RATE = 0.97;

    /**
     * @return array{gross: float, cashback: float, admin_fee: float, seller_net: float}
     */
    public function split(float $gross): array
    {
        $gross = round($gross, 2);

        return [
            'gross' => $gross,
            'cashback' => round($gross * self::CASHBACK_RATE, 2),
            'admin_fee' => round($gross * self::ADMIN_FEE_RATE, 2),
            'seller_net' => round($gross * self::SELLER_RATE, 2),
        ];
    }

    public function cashbackFor(float $gross): float
    {
        return $this->split($gross)['cashback'];
    }

    public function netAfterCashback(float $gross): float
    {
        return round($this->split($gross)['gross'] - $this->split($gross)['cashback'], 2);
    }
}

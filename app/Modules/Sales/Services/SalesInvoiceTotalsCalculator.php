<?php

namespace App\Modules\Sales\Services;

use App\Core\Utils\Math;

class SalesInvoiceTotalsCalculator
{
    /**
     * Calculate subtotal, discount total, and grand total for a set of items.
     * Returns [subtotal, discountTotal, grandTotal] as fixed-point strings.
     */
    public function calculate(array $items): array
    {
        $subtotal = '0.00';
        $discountTotal = '0.00';

        foreach ($items as $item) {
            $quantity = (string) ($item['quantity'] ?? 0);
            $unitPrice = (string) ($item['unit_price'] ?? 0);
            $discountPercent = (string) ($item['discount_percent'] ?? 0);

            $gross = Math::mul($quantity, $unitPrice);
            $discount = Math::round(Math::div(Math::mul($gross, $discountPercent), '100'), 2);

            $subtotal = Math::add($subtotal, $gross);
            $discountTotal = Math::add($discountTotal, $discount);
        }

        $grandTotal = Math::sub($subtotal, $discountTotal);

        return [
            Math::round($subtotal, 2),
            Math::round($discountTotal, 2),
            Math::round($grandTotal, 2),
        ];
    }
}

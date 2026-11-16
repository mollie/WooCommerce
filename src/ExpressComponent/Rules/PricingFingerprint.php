<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\ExpressComponent\Rules;

use Mollie\WooCommerce\ExpressComponent\Rules\Values\CartFacts;
/**
 * A session's amount is fixed at creation, so any change here needs a new session.
 */
final class PricingFingerprint
{
    public static function of(CartFacts $cart): string
    {
        $total = $cart->total();
        $shipping = $cart->shipping();
        $parts = [
            'cart' => $cart->cartHash(),
            'total' => $total === null ? null : $total->minorUnits(),
            'currency' => $total === null ? null : $total->currency(),
            'rates' => $shipping === null ? [] : $shipping->rateIds(),
            // Only a shipping cart's destination changes its price.
            'destination' => $cart->needsShipping() ? $cart->destination() : null,
        ];
        return hash('sha256', (string) json_encode($parts));
    }
}

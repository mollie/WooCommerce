<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\ExpressComponent\Rules;

use Mollie\WooCommerce\ExpressComponent\Rules\Values\CartFacts;

/**
 * A session's amount is fixed at creation, so any change here needs a new session.
 * The amount is the cart without shipping: the rate and the destination are the wallet's to choose and
 * Mollie adds the chosen option, so neither reprices the session.
 */
final class PricingFingerprint
{
    public static function of(CartFacts $cart): string
    {
        $total = $cart->total();

        $parts = [
            'cart' => $cart->cartHash(),
            'total' => $total === null ? null : $total->minorUnits(),
            'currency' => $total === null ? null : $total->currency(),
        ];
        return hash('sha256', (string) json_encode($parts));
    }
}

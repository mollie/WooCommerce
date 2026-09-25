<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Core\Express;

use Mollie\WooCommerce\Core\Types\CartFacts;
/**
 * What makes two session requests the same checkout: the cart contents, the total and currency,
 * the chosen shipping rates and the destination they were priced for. A session's amount is fixed
 * when it is created, so any change here needs a new session. The result is a hash only; the
 * destination cannot be read back from it.
 */
final class PricingFingerprint
{
    public static function of(CartFacts $cart): string
    {
        $total = $cart->total();
        $shipping = $cart->shipping();
        return hash('sha256', (string) json_encode(['cart' => $cart->cartHash(), 'total' => $total === null ? null : $total->minorUnits(), 'currency' => $total === null ? null : $total->currency(), 'rates' => $shipping === null ? [] : $shipping->rateIds(), 'destination' => $cart->destination()]));
    }
}

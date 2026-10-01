<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Core\Express;

use Mollie\WooCommerce\Core\Types\Admit;
use Mollie\WooCommerce\Core\Types\CartFacts;
use Mollie\WooCommerce\Core\Types\ExpressOrderFacts;
use Mollie\WooCommerce\Core\Types\RememberedSession;
use Mollie\WooCommerce\Core\Types\Refuse;

/**
 * What an order request at Mollie's submit event gets.
 */
final class StartOrderDecision
{
    public const CREATED_VIA = 'mollie_express';

    private const REFUSED = 409;

    public static function admit(
        ?RememberedSession $session,
        ?ExpressOrderFacts $existing,
        CartFacts $cart,
        int $now
    ): Admit|Refuse {

        if ($session === null) {
            return new Refuse('session_missing', self::REFUSED);
        }
        if ($now >= $session->expiresAt()) {
            return new Refuse('session_expired', self::REFUSED);
        }
        if ($cart->needsShipping() && !($cart->shippingDestinationComplete() && $cart->shippingRateChosen())) {
            return new Refuse('shipping_incomplete', self::REFUSED);
        }
        if ($session->fingerprint() !== PricingFingerprint::of($cart)) {
            return new Refuse('cart_changed', self::REFUSED);
        }
        if ($existing !== null && !$existing->needsPayment()) {
            // Paid, or cancelled: a second payment from this session must never be started.
            return new Refuse('order_not_payable', self::REFUSED);
        }

        return new Admit();
    }
}

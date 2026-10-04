<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\ExpressComponent\Rules;

use Mollie\WooCommerce\ExpressComponent\Rules\Values\CartFacts;
use Mollie\WooCommerce\ExpressComponent\Rules\Values\ExpressOrderFacts;
use Mollie\WooCommerce\ExpressComponent\Rules\Values\RememberedSession;
use Mollie\WooCommerce\Shared\Values\Admit;
use Mollie\WooCommerce\Shared\Values\Refuse;
final class StartOrderDecision
{
    public const CREATED_VIA = 'mollie_express';
    private const REFUSED = 409;
    public static function admit(?RememberedSession $session, ?ExpressOrderFacts $existing, CartFacts $cart, int $now): Admit|Refuse
    {
        if ($session === null) {
            return new Refuse('session_missing', self::REFUSED);
        }
        if ($now >= $session->expiresAt()) {
            return new Refuse('session_expired', self::REFUSED);
        }
        if ($cart->needsShipping() && !($cart->shippingDestinationComplete() && $cart->shippingRateChosen())) {
            return new Refuse('shipping_incomplete', self::REFUSED);
        }
        if ($session->fingerprint() !== \Mollie\WooCommerce\ExpressComponent\Rules\PricingFingerprint::of($cart)) {
            return new Refuse('cart_changed', self::REFUSED);
        }
        if ($existing !== null && !$existing->needsPayment()) {
            // Paid or cancelled: never start a second payment for this session.
            return new Refuse('order_not_payable', self::REFUSED);
        }
        return new Admit();
    }
}

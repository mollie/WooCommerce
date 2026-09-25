<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Core\Express;

use Mollie\WooCommerce\Core\Types\CartFacts;
use Mollie\WooCommerce\Core\Types\Effect;
use Mollie\WooCommerce\Core\Types\ExpressOrderFacts;
use Mollie\WooCommerce\Core\Types\Refuse;
/**
 * What an order request at Mollie's submit event gets.
 *
 * Ordered guards, first match wins: no session, an expired session, a cart that ships without a
 * complete destination and rate, a checkout priced differently from the session (which covers a
 * changed cart, address or rate); then the order that already carries the ref is reused, and
 * otherwise one is created.
 */
final class StartOrderDecision
{
    public const CREATE = 'create';
    public const REUSE = 'reuse';
    public const CREATED_VIA = 'mollie_express';
    public const NOTE = 'express.order.created';
    private const REFUSED = 409;
    public static function decide(ExpressOrderFacts $facts, CartFacts $cart, int $now): Refuse|string
    {
        if (!$facts->hasSession()) {
            return new Refuse('session_missing', self::REFUSED);
        }
        if ($facts->expiresAt() === null || $now >= $facts->expiresAt()) {
            return new Refuse('session_expired', self::REFUSED);
        }
        if ($cart->needsShipping() && !($cart->shippingDestinationComplete() && $cart->shippingRateChosen())) {
            return new Refuse('shipping_incomplete', self::REFUSED);
        }
        if ($facts->fingerprint() !== \Mollie\WooCommerce\Core\Express\PricingFingerprint::of($cart)) {
            return new Refuse('cart_changed', self::REFUSED);
        }
        return $facts->existingOrderId() !== null ? self::REUSE : self::CREATE;
    }
    /**
     * Everything written on a newly created express order. The payment method is provisional: the
     * first webhook corrects it to the wallet that paid.
     *
     * @return list<Effect>
     */
    public static function stamps(ExpressOrderFacts $facts, string $mode, string $gatewayId, string $wallet): array
    {
        return [Effect::setCreatedVia(self::CREATED_VIA), Effect::setMeta('_mollie_express_ref', (string) $facts->expressRef()), Effect::setMeta('_mollie_express_session_id', (string) $facts->sessionId()), Effect::setMeta('_mollie_express_expires_at', (string) $facts->expiresAt()), Effect::setMeta('_mollie_payment_mode', $mode), Effect::setStatus('pending'), Effect::setPaymentMethod($gatewayId), Effect::addNote(self::NOTE, ['wallet' => $wallet])];
    }
}

<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Core\Express;

use Mollie\WooCommerce\Core\Types\Admit;
use Mollie\WooCommerce\Core\Types\CartFacts;
use Mollie\WooCommerce\Core\Types\Effect;
use Mollie\WooCommerce\Core\Types\ExpressOrderFacts;
use Mollie\WooCommerce\Core\Types\RememberedSession;
use Mollie\WooCommerce\Core\Types\Refuse;

/**
 * What an order request at Mollie's submit event gets.
 */
final class StartOrderDecision
{
    public const CREATED_VIA = 'mollie_express';

    public const NOTE = 'express.order.created';

    private const REFUSED = 409;

    public static function decide(
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

    /**
     * Everything written on a newly created express order. The payment method is provisional: the
     * first webhook corrects it to the wallet that paid.
     *
     * @return list<Effect>
     */
    public static function stamps(RememberedSession $session, string $mode, string $gatewayId, string $wallet): array
    {
        return [
            Effect::setCreatedVia(self::CREATED_VIA),
            Effect::setMeta('_mollie_express_ref', $session->expressRef()),
            Effect::setMeta('_mollie_express_session_id', $session->sessionId()),
            Effect::setMeta('_mollie_express_expires_at', (string) $session->expiresAt()),
            Effect::setMeta('_mollie_payment_mode', $mode),
            Effect::setStatus('pending'),
            Effect::setPaymentMethod($gatewayId),
            Effect::addNote(self::NOTE, ['wallet' => $wallet]),
        ];
    }
}

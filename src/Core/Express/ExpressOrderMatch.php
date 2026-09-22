<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Core\Express;

use Mollie\WooCommerce\Core\Types\Admit;
use Mollie\WooCommerce\Core\Types\ExpressOrderFacts;
use Mollie\WooCommerce\Core\Types\PaymentSnapshot;
use Mollie\WooCommerce\Core\Types\Refuse;

/**
 * Whether a payment the plugin never created may be tied to the order carrying its express_ref
 * The payment was fetched from Mollie; the order is the one whose ref it names.
 *
 * Ordered guards, first match wins. A refusal is answered 200: the notification will never match,
 * so Mollie must stop retrying it.
 */
final class ExpressOrderMatch
{
    private const UNMATCHED = 200;

    public static function decide(PaymentSnapshot $payment, ?ExpressOrderFacts $order): Admit|Refuse
    {
        $ref = (string) $payment->expressRef();
        if ($ref === '') {
            return self::refuse('missing_ref');
        }
        if ($order === null || !hash_equals((string) $order->expressRef(), $ref)) {
            return self::refuse('unknown_ref');
        }
        if ($order->createdVia() !== StartOrderDecision::CREATED_VIA) {
            return self::refuse('not_express');
        }
        if (!self::sameAmount($payment, $order)) {
            return self::refuse('amount_mismatch');
        }

        $tracked = (string) $order->trackedPaymentId();
        if ($tracked === $payment->id()) {
            return new Admit();
        }
        if ($tracked !== '') {
            return self::refuse('other_payment');
        }
        if (!$order->needsPayment()) {
            return self::refuse('not_payable');
        }

        return new Admit();
    }

    private static function sameAmount(PaymentSnapshot $payment, ExpressOrderFacts $order): bool
    {
        $total = $order->total();

        return $total !== null
            && $total->currency() === $payment->amount()->currency()
            && $total->equals($payment->amount());
    }

    private static function refuse(string $reason): Refuse
    {
        return new Refuse($reason, self::UNMATCHED);
    }
}

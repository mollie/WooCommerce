<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\ExpressComponent\Rules;

use Mollie\WooCommerce\ExpressComponent\Rules\Values\ExpressOrderFacts;
use Mollie\WooCommerce\Shared\Values\Admit;
use Mollie\WooCommerce\Shared\Values\PaymentSnapshot;
use Mollie\WooCommerce\Shared\Values\Refuse;
final class ExpressOrderMatch
{
    // 200 so Mollie stops retrying a notification that will never match.
    private const UNMATCHED = 200;
    public static function admit(PaymentSnapshot $payment, ?ExpressOrderFacts $order): Admit|Refuse
    {
        $ref = (string) $payment->expressRef();
        if ($ref === '') {
            return self::refuse('missing_ref');
        }
        if ($order === null || !hash_equals($order->expressRef(), $ref)) {
            return self::refuse('unknown_ref');
        }
        if ($order->createdVia() !== \Mollie\WooCommerce\ExpressComponent\Rules\StartOrderDecision::CREATED_VIA) {
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
        return $total !== null && $total->isSameAs($payment->amount());
    }
    private static function refuse(string $reason): Refuse
    {
        return new Refuse($reason, self::UNMATCHED);
    }
}

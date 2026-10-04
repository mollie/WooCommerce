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
    public const PAID_AFTER_CANCEL = 'paid_after_cancel';
    private const CLEANUP = 'cleanup';
    private const MONEY_TAKEN = ['paid', 'authorized'];
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
        return self::tookMoney($payment) ? self::admitTakenMoney($order, $tracked) : self::admitUnfinishedAttempt($order, $tracked);
    }
    public static function tookMoney(PaymentSnapshot $payment): bool
    {
        return in_array($payment->status(), self::MONEY_TAKEN, \true);
    }
    private static function admitUnfinishedAttempt(ExpressOrderFacts $order, string $tracked): Admit|Refuse
    {
        if ($tracked !== '') {
            return self::refuse('other_payment');
        }
        if (!$order->needsPayment()) {
            return self::refuse('not_payable');
        }
        return new Admit();
    }
    private static function admitTakenMoney(ExpressOrderFacts $order, string $tracked): Admit|Refuse
    {
        if ($order->cancelled() && $order->cancelledBy() !== self::CLEANUP) {
            return self::refuse(self::PAID_AFTER_CANCEL);
        }
        if ($order->webhookNeedsPayment()) {
            return new Admit();
        }
        return self::refuse($tracked !== '' ? 'other_payment' : 'not_payable');
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

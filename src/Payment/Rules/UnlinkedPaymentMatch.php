<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Payment\Rules;

use Mollie\WooCommerce\Shared\Values\Admit;
use Mollie\WooCommerce\Shared\Values\Money;
use Mollie\WooCommerce\Shared\Values\Refuse;

/**
 * Whether a Mollie order no WooCommerce order is linked to yet may be handed to the order its
 * metadata names. Only a Mollie order qualifies: the plugin creates one per WooCommerce order, with
 * that order's number, through the Orders API, while express and other payments are tr_ payments
 * that must never pay an order they were not created for (REQ-G5). The data comes from Mollie,
 * which the plugin asked; a refusal is answered 200, as before.
 */
final class UnlinkedPaymentMatch
{
    public const NOT_A_MOLLIE_ORDER = 'not_a_mollie_order';
    public const NO_ORDER_ID = 'no_order_id';
    public const ORDER_NUMBER_MISMATCH = 'order_number_mismatch';
    public const ORDER_UNKNOWN = 'order_unknown';
    public const NOT_MOLLIE = 'not_mollie';
    public const NOT_PAYABLE = 'not_payable';
    public const AMOUNT_MISMATCH = 'amount_mismatch';

    public static function admit(
        bool $isMollieOrder,
        int $metadataOrderId,
        bool $orderExists,
        bool $orderNumberMatches,
        bool $isMollieGateway,
        bool $orderNeedsPayment,
        ?Money $paymentAmount,
        ?Money $orderTotal
    ): Admit|Refuse {

        if (!$isMollieOrder) {
            return self::refuse(self::NOT_A_MOLLIE_ORDER);
        }
        if ($metadataOrderId <= 0) {
            return self::refuse(self::NO_ORDER_ID);
        }
        if (!$orderExists) {
            return self::refuse(self::ORDER_UNKNOWN);
        }
        if (!$orderNumberMatches) {
            return self::refuse(self::ORDER_NUMBER_MISMATCH);
        }
        if (!$isMollieGateway) {
            return self::refuse(self::NOT_MOLLIE);
        }
        if (!$orderNeedsPayment) {
            return self::refuse(self::NOT_PAYABLE);
        }
        if ($paymentAmount === null || $orderTotal === null || !$orderTotal->isSameAs($paymentAmount)) {
            return self::refuse(self::AMOUNT_MISMATCH);
        }

        return new Admit();
    }

    private static function refuse(string $code): Refuse
    {
        return new Refuse($code, 200);
    }
}

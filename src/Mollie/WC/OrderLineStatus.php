<?php

use Mollie\Api\Types\OrderLineStatus as ApiOrderLineStatus;

/**
 * Class Mollie_WC_OrderLineStatus
 */
class Mollie_WC_OrderLineStatus extends ApiOrderLineStatus
{
    const CAN_BE_CANCELED = [
        self::STATUS_CREATED,
        self::STATUS_AUTHORIZED,
    ];

    const CAN_BE_REFUNDED = [
        self::STATUS_PAID,
        self::STATUS_SHIPPING,
        self::STATUS_COMPLETED,
    ];
}

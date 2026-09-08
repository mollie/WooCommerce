<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Gateway\Refund;

use Mollie\Api\Types\OrderLineStatus as ApiOrderLineStatus;
/**
 * Class OrderLineStatus
 */
class OrderLineStatus extends ApiOrderLineStatus
{
    /**
     * @var string[]
     */
    public const CAN_BE_CANCELED = [self::STATUS_CREATED, self::STATUS_AUTHORIZED];
    /**
     * @var string[]
     */
    public const CAN_BE_REFUNDED = [self::STATUS_PAID, self::STATUS_SHIPPING, self::STATUS_COMPLETED];
}

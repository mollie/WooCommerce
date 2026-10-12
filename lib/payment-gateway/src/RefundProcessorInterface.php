<?php

declare (strict_types=1);
namespace Mollie\Inpsyde\PaymentGateway;

use Exception;
use WC_Order;
interface RefundProcessorInterface
{
    /**
     * @throws Exception If failed to refund payment.
     */
    public function refundOrderPayment(WC_Order $order, float $amount, string $reason): void;
}

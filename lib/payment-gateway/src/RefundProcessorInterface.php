<?php

declare(strict_types=1);

namespace Inpsyde\PaymentGateway;

use Exception;
use WC_Order;

interface RefundProcessorInterface
{
    /**
     * @throws Exception If failed to refund payment.
     */
    public function refundOrderPayment(
        WC_Order $wcOrder,
        float $amount,
        string $reason
    ): void;
}

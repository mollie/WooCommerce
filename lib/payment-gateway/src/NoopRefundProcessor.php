<?php

declare(strict_types=1);

namespace Inpsyde\PaymentGateway;

use WC_Order;

class NoopRefundProcessor implements RefundProcessorInterface
{
    public function refundOrderPayment(WC_Order $wcOrder, float $amount, string $reason): void
    {
    }
}

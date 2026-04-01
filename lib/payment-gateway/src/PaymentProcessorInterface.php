<?php

declare (strict_types=1);
namespace Mollie\Inpsyde\PaymentGateway;

interface PaymentProcessorInterface
{
    /**
     * @return array<mixed>
     */
    public function processPayment(\WC_Order $order, PaymentGateway $gateway): array;
}

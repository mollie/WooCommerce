<?php

declare (strict_types=1);
namespace Mollie\Inpsyde\PaymentGateway;

interface PaymentProcessorInterface
{
    public function processPayment(\WC_Order $order, PaymentGateway $gateway): array;
}

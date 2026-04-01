<?php

declare(strict_types=1);

namespace Inpsyde\PaymentGateway;

class NoopPaymentProcessor implements PaymentProcessorInterface
{
    /**
     * @return array<mixed>
     */
    public function processPayment(\WC_Order $order, PaymentGateway $gateway): array
    {
        return [
            'result' => 'success',
            'redirect' => $gateway->get_return_url($order),
        ];
    }
}

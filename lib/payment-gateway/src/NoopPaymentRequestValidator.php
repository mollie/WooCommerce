<?php

declare(strict_types=1);

namespace Inpsyde\PaymentGateway;

class NoopPaymentRequestValidator implements PaymentRequestValidatorInterface
{
    public function assertIsValid(\WC_Order $order, PaymentGateway $gateway): void
    {
        // TODO: Implement assertIsValid() method.
    }
}

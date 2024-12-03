<?php

namespace Mollie\WooCommerce\PaymentMethods\PaymentFieldsStrategies;

use Inpsyde\PaymentGateway\PaymentFieldsRendererInterface;
use Mollie\WooCommerce\Gateway\MolliePaymentGateway;
use Mollie\WooCommerce\PaymentMethods\PaymentMethodI;

class PaymentFieldsRenderer implements PaymentFieldsRendererInterface
{
    private PaymentMethodI $paymentMethod;
    private MolliePaymentGateway $gateway;

    public function __construct($paymentMethod, $gateway)
    {
        $this->paymentMethod = $paymentMethod;
        $this->gateway = $gateway;
    }

    /**
     * @inheritDoc
     */
    public function renderFields(): string
    {
        return $this->paymentMethod->paymentFieldsStrategy($this->gateway);
    }
}

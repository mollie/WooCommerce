<?php

namespace Mollie\WooCommerce\PaymentMethods\PaymentFieldsStrategies;

use Inpsyde\PaymentGateway\PaymentFieldsRendererInterface;
use Inpsyde\PaymentGateway\PaymentGateway;
use Mollie\WooCommerce\Gateway\MolliePaymentGatewayHandler;
use Mollie\WooCommerce\PaymentMethods\PaymentMethodI;

class PaymentFieldsRenderer implements PaymentFieldsRendererInterface
{
    private PaymentMethodI $paymentMethod;
    private MolliePaymentGatewayHandler $deprecatedGatewayHelper;

    private string $gatewayDescription;

    public function __construct($paymentMethod, $deprecatedGatewayHelper, $gateway)
    {
        $this->paymentMethod = $paymentMethod;
        $this->deprecatedGatewayHelper = $deprecatedGatewayHelper;
        $this->gatewayDescription = $gateway;
    }

    /**
     * @inheritDoc
     */
    public function renderFields(): string
    {
        return $this->paymentMethod->paymentFieldsStrategy($this->deprecatedGatewayHelper, $this->gatewayDescription);
    }
}

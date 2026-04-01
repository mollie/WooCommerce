<?php

namespace Mollie\WooCommerce\PaymentMethods\PaymentFieldsStrategies;

use Inpsyde\PaymentGateway\PaymentFieldsRendererInterface;
use Mollie\WooCommerce\Gateway\MolliePaymentGatewayHandler;
use Mollie\WooCommerce\Shared\Data;

class AbstractPaymentFieldsRenderer implements PaymentFieldsRendererInterface
{
    protected MolliePaymentGatewayHandler $deprecatedHelperGateway;

    protected string $gatewayDescription;
    protected Data $dataHelper;

    /**
     * @param mixed $deprecatedHelperGateway
     * @param mixed $gateway
     * @param mixed $dataHelper
     */
    public function __construct($deprecatedHelperGateway, $gateway, $dataHelper)
    {

        $this->deprecatedHelperGateway = $deprecatedHelperGateway;
        $this->gatewayDescription = $gateway;
        $this->dataHelper = $dataHelper;
    }

    /**
     * @inheritDoc
     */
    public function renderFields(): string
    {
        return '';
    }

    /**
     * @param mixed $gateway
     * @param mixed $dataHelper
     * @return mixed
     */
    public function getFieldMarkup($gateway, $dataHelper)
    {
        return '';
    }
}

<?php

namespace Mollie\WooCommerce\PaymentMethods\PaymentFieldsStrategies;

use Mollie\Inpsyde\PaymentGateway\PaymentFieldsRendererInterface;
use Mollie\Inpsyde\PaymentGateway\PaymentGateway;
use Mollie\WooCommerce\Gateway\MolliePaymentGatewayHandler;
use Mollie\WooCommerce\PaymentMethods\PaymentMethodI;
use Mollie\WooCommerce\Shared\Data;
class AbstractPaymentFieldsRenderer implements PaymentFieldsRendererInterface
{
    protected MolliePaymentGatewayHandler $deprecatedHelperGateway;
    protected string $gatewayDescription;
    /**
     * @var mixed
     */
    protected Data $dataHelper;
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
}

<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\PaymentMethods\PaymentFieldsStrategies;

class DefaultFieldsStrategy implements PaymentFieldsStrategyI
{
    public function execute($deprecatedHelperGateway, $gatewayDescription, $dataHelper): string
    {
        return $gatewayDescription;
    }

    public function getFieldMarkup($gateway, $dataHelper)
    {
        return "";
    }
}

<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\PaymentMethods\PaymentFieldsStrategies;

interface PaymentFieldsStrategyI
{
    public function execute($deprecatedHelperDeprecatedHelperGateway, $gatewayDescription, $dataHelper): string;
}

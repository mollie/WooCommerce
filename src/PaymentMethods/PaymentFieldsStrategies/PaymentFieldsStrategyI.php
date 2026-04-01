<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\PaymentMethods\PaymentFieldsStrategies;

interface PaymentFieldsStrategyI
{
    /**
     * @param mixed $deprecatedHelperDeprecatedHelperGateway
     * @param mixed $gatewayDescription
     * @param mixed $dataHelper
     */
    public function execute($deprecatedHelperDeprecatedHelperGateway, $gatewayDescription, $dataHelper): string;
}

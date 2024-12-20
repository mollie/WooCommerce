<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\PaymentMethods\PaymentFieldsStrategies;

class DefaultFieldsStrategy implements PaymentFieldsStrategyI
{
    public function execute($gateway, $dataHelper): string
    {
        return '';
    }

    public function getFieldMarkup($gateway, $dataHelper)
    {
        return "";
    }
}

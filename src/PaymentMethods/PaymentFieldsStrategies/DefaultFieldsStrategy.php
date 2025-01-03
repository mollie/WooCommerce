<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\PaymentMethods\PaymentFieldsStrategies;

use Inpsyde\PaymentGateway\PaymentFieldsRendererInterface;

class DefaultFieldsStrategy extends AbstractPaymentFieldsRenderer implements PaymentFieldsRendererInterface
{
    public function renderFields(): string
    {
        return $this->gatewayDescription;
    }

    public function getFieldMarkup($gateway, $dataHelper)
    {
        return "";
    }
}

<?php

namespace Mollie\WooCommerce\PaymentMethods\PaymentFieldsStrategies;

use Inpsyde\PaymentGateway\PaymentFieldsRendererInterface;

class NoopPaymentFieldsRenderer implements PaymentFieldsRendererInterface
{
    /**
     * @inheritDoc
     */
    public function renderFields(): string
    {
        return '';
    }
}

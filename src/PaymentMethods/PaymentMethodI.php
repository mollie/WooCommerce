<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\PaymentMethods;

interface PaymentMethodI
{
    public function getProperty(string $propertyName);
    public function hasProperty(string $propertyName);
    public function hasPaymentFields();
}

<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\PaymentMethods;

use Psr\Container\ContainerInterface;

interface PaymentMethodI
{
    public function getProperty(string $propertyName);
    public function hasProperty(string $propertyName): bool;
}

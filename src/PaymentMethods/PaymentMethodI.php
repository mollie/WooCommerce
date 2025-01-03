<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\PaymentMethods;

interface PaymentMethodI
{
    public function getProperty(string $propertyName);
    public function hasProperty(string $propertyName): bool;
    public function title(): string;
    public function hasPaymentFields(): bool;
    public function getProcessedDescriptionForBlock(): string;
    public function hasSurcharge(): bool;
}

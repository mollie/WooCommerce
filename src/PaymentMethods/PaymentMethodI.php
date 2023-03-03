<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\PaymentMethods;

use Mollie\WooCommerce\Payment\PaymentFieldsService;

interface PaymentMethodI
{
    public function getProperty(string $propertyName);
    public function hasProperty(string $propertyName): bool;
    public function hasPaymentFields(): bool;
    public function getProcessedDescriptionForBlock(): string;
    public function paymentFieldsService(): PaymentFieldsService;
    public function hasSurcharge(): bool;
}

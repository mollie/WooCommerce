<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\PaymentMethods;

trait CommonPaymentMethodTrait
{
    public function getAllSettings(): array
    {
        return $this->settings;
    }

    public function getProperty(string $propertyName)
    {
        $properties = $this->getMergedProperties();
        return $properties[$propertyName] ?? false;
    }

    public function hasProperty(string $propertyName): bool
    {
        $properties = $this->getMergedProperties();
        return isset($properties[$propertyName]);
    }

    private function getMergedProperties(): array
    {
        return $this->settings !== null && is_array($this->settings) ? array_merge($this->config, $this->settings) : $this->config;
    }
}

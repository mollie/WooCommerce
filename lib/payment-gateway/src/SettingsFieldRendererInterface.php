<?php

declare (strict_types=1);
namespace Mollie\Inpsyde\PaymentGateway;

interface SettingsFieldRendererInterface
{
    /**
     * @param array<mixed> $fieldConfig
     */
    public function render(string $fieldId, array $fieldConfig, PaymentGateway $gateway): string;
}

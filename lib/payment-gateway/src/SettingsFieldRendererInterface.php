<?php

declare (strict_types=1);
namespace Mollie\Inpsyde\PaymentGateway;

interface SettingsFieldRendererInterface
{
    public function render(string $fieldId, array $fieldConfig, PaymentGateway $gateway): string;
}

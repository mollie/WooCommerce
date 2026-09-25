<?php

declare (strict_types=1);
namespace Mollie\Inpsyde\PaymentGateway\Method;

use Mollie\Inpsyde\PaymentGateway\SettingsFieldRendererInterface;
use Mollie\Inpsyde\PaymentGateway\SettingsFieldSanitizerInterface;
use Mollie\Psr\Container\ContainerInterface;
interface CustomSettingsFieldsDefinition
{
    /**
     * @return array<callable(ContainerInterface):SettingsFieldRendererInterface>
     */
    public function renderers(): array;
    /**
     * @return array<callable(ContainerInterface):SettingsFieldSanitizerInterface>
     */
    public function sanitizers(): array;
}

<?php

declare(strict_types=1);

namespace Inpsyde\PaymentGateway\Method;

use Inpsyde\PaymentGateway\SettingsFieldRendererInterface;
use Inpsyde\PaymentGateway\SettingsFieldSanitizerInterface;
use Psr\Container\ContainerInterface;

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

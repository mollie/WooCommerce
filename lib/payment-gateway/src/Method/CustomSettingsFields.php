<?php

declare(strict_types=1);

namespace Inpsyde\PaymentGateway\Method;

use Inpsyde\PaymentGateway\SettingsFieldRendererInterface;
use Inpsyde\PaymentGateway\SettingsFieldSanitizerInterface;
use Psr\Container\ContainerInterface;

class CustomSettingsFields implements CustomSettingsFieldsDefinition
{
    /**
     * @var array<callable(ContainerInterface):SettingsFieldRendererInterface>
     */
    private array $renderers;

    /**
     * @var array<callable(ContainerInterface):SettingsFieldSanitizerInterface>
     */
    private array $sanitizers;

    /**
     * @param array<string,callable(ContainerInterface):SettingsFieldRendererInterface> $renderers
     * @param array<string,callable(ContainerInterface):SettingsFieldSanitizerInterface> $sanitizers
     */
    public function __construct(array $renderers, array $sanitizers)
    {
        $this->renderers = $renderers;
        $this->sanitizers = $sanitizers;
    }

    public function renderers(): array
    {
        return $this->renderers;
    }

    public function sanitizers(): array
    {
        return $this->sanitizers;
    }
}

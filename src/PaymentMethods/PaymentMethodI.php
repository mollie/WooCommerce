<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\PaymentMethods;

use Psr\Container\ContainerInterface;

interface PaymentMethodI
{
    public function getProperty(string $propertyName);
    public function hasProperty(string $propertyName): bool;

    public function blocksData(ContainerInterface $container): array;

    public function shouldDisplayIcon(): bool;

    public function id(): string;

    public function initializeTranslations(): void;

    public function updateSettingsWithDefaults(ContainerInterface $container): array;

    public function getInitialOrderStatus(): string;

    public function filtersOnBuild(): void;

    public function debugGiftcardDetails($payment, \WC_Order $order): void;
}

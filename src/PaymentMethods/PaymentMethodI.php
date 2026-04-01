<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\PaymentMethods;

use Psr\Container\ContainerInterface;

interface PaymentMethodI
{
    /**
     * @return mixed
     */
    public function getProperty(string $propertyName);
    public function hasProperty(string $propertyName): bool;

    /**
     * @return array<mixed>
     */
    public function blocksData(ContainerInterface $container): array;

    public function shouldDisplayIcon(): bool;

    public function id(): string;

    public function initializeTranslations(): void;

    /**
     * @return array<mixed>
     */
    public function updateSettingsWithDefaults(ContainerInterface $container): array;

    public function getInitialOrderStatus(): string;

    public function filtersOnBuild(): void;

    /**
     * @param mixed $payment
     */
    public function debugGiftcardDetails($payment, \WC_Order $order): void;
}

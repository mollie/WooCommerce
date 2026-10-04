<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\ExpressComponent\Rules\Values;

final class ExpressSettings
{
    /**
     * @param list<string> $supportedSurfaces
     * @param list<string> $allowedModes
     * @param array<string, array{gatewayId: string, mollieMethod: string, paidAs: string, checkoutSetting: string, addressFrom: string}> $wallets
     */
    public function __construct(
        private array $supportedSurfaces,
        private array $allowedModes,
        private array $wallets
    ) {
    }

    /**
     * @return list<string>
     */
    public function supportedSurfaces(): array
    {
        return $this->supportedSurfaces;
    }

    /**
     * @return list<string>
     */
    public function allowedModes(): array
    {
        return $this->allowedModes;
    }

    /**
     * @return array<string, array{gatewayId: string, mollieMethod: string, paidAs: string, checkoutSetting: string, addressFrom: string}>
     */
    public function wallets(): array
    {
        return $this->wallets;
    }
}

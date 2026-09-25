<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Core\Types;

/**
 * The config rows the express decisions read: which surfaces exist, in which modes the feature may
 * run and which wallets it knows. There is no switch of its own: what the merchant turned on lives
 * in each wallet's payment method settings and arrives as ShopFacts.
 */
final class ExpressSettings
{
    /**
     * @param list<string> $supportedSurfaces Surfaces the feature can render on.
     * @param list<string> $allowedModes Shop modes the feature may run in.
     * @param array<string, array{gatewayId: string, mollieMethod: string, needsHttps: bool, checkoutSetting: string, addressFrom: string}> $wallets
     */
    public function __construct(private array $supportedSurfaces, private array $allowedModes, private array $wallets)
    {
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
     * @return array<string, array{gatewayId: string, mollieMethod: string, needsHttps: bool, checkoutSetting: string, addressFrom: string}>
     */
    public function wallets(): array
    {
        return $this->wallets;
    }
}

<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\ExpressComponent\Rules\Values;

final class ShopFacts
{
    /**
     * @param 'live'|'test' $mode
     * @param list<string> $registeredGatewayIds
     * @param list<string> $enabledGatewayIds
     * @param list<string> $activeMollieMethods Active on the merchant's Mollie profile.
     * @param list<string> $expressCheckoutGatewayIds Express button on the checkout turned on.
     */
    public function __construct(private string $mode, private bool $isHttps, private array $registeredGatewayIds, private array $enabledGatewayIds, private array $activeMollieMethods, private array $expressCheckoutGatewayIds)
    {
    }
    public function mode(): string
    {
        return $this->mode;
    }
    public function isHttps(): bool
    {
        return $this->isHttps;
    }
    /**
     * @return list<string>
     */
    public function registeredGatewayIds(): array
    {
        return $this->registeredGatewayIds;
    }
    /**
     * @return list<string>
     */
    public function enabledGatewayIds(): array
    {
        return $this->enabledGatewayIds;
    }
    /**
     * @return list<string>
     */
    public function activeMollieMethods(): array
    {
        return $this->activeMollieMethods;
    }
    /**
     * @return list<string>
     */
    public function expressCheckoutGatewayIds(): array
    {
        return $this->expressCheckoutGatewayIds;
    }
}

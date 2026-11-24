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
     * @param list<string> $surchargedGatewayIds The merchant set a surcharge on the payment method.
     * @param list<string> $expressCartGatewayIds Express button on the cart turned on.
     */
    public function __construct(private string $mode, private bool $isHttps, private array $registeredGatewayIds, private array $enabledGatewayIds, private array $activeMollieMethods, private array $expressCheckoutGatewayIds, private array $surchargedGatewayIds = [], private array $expressCartGatewayIds = [])
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
    /**
     * Gateways whose express button is turned on for the surface; none for a surface without a switch.
     *
     * @return list<string>
     */
    public function expressGatewayIds(string $surface): array
    {
        if ($surface === 'checkout') {
            return $this->expressCheckoutGatewayIds;
        }
        if ($surface === 'cart') {
            return $this->expressCartGatewayIds;
        }
        return [];
    }
    /**
     * @return list<string>
     */
    public function surchargedGatewayIds(): array
    {
        return $this->surchargedGatewayIds;
    }
}

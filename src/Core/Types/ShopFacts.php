<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Core\Types;

/**
 * What is true of the shop right now, as plain values: its mode, its scheme and which payment
 * methods exist, are enabled and are active at Mollie, and which have their express button on the
 * checkout turned on.
 */
final class ShopFacts
{
    /**
     * @param 'live'|'test' $mode
     * @param list<string> $registeredGatewayIds Gateway ids of the payment methods the plugin has.
     * @param list<string> $enabledGatewayIds Gateway ids the merchant enabled.
     * @param list<string> $activeMollieMethods Mollie method ids active on the merchant's profile.
     * @param list<string> $expressCheckoutGatewayIds Gateway ids whose own "show the express button on the
     *        checkout" setting is on.
     */
    public function __construct(
        private string $mode,
        private bool $isHttps,
        private array $registeredGatewayIds,
        private array $enabledGatewayIds,
        private array $activeMollieMethods,
        private array $expressCheckoutGatewayIds
    ) {
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

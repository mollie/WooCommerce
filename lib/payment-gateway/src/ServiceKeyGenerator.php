<?php

declare (strict_types=1);
namespace Mollie\Inpsyde\PaymentGateway;

/**
 * ServiceKeyGenerator is a utility class for generating unique service keys
 * based on an identifier and base prefix. It provides methods to create specific item keys
 * as well as fallback keys without the item component.
 */
class ServiceKeyGenerator
{
    private string $id;
    private string $base;
    private string $fallbackBase;
    public function __construct(string $id, string $base = 'payment_gateway', string $fallbackBase = 'payment_gateways')
    {
        $this->id = $id;
        $this->base = $base;
        $this->fallbackBase = $fallbackBase;
    }
    /**
     * Creates a unique service key by appending an item identifier to the base and id components.
     *
     * @param string $item The specific item or subcomponent of the key.
     *
     * @return string A generated key in the format `base.id.item`.
     */
    public function createKey(string $item): string
    {
        return $this->base . '.' . $this->id . '.' . $item;
    }
    /**
     * Creates a generic service key without including an item identifier,
     * useful as a fallback option.
     *
     * @return string A generated fallback key in the format `base.id`.
     *
     */
    public function createFallbackKey(string $item): string
    {
        return $this->fallbackBase . '.' . $item;
    }
}

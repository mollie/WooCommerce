<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\ExpressComponent\Rules\Values;

/**
 * What the store answers when Mollie asks for the shipping options of an address:
 * the options, or the Mollie error type that says why there are none.
 */
final class ShippingOptionsVerdict
{
    /**
     * @param list<array{reference: string, description: string, amount: array{currency: string, value: string}}> $options
     */
    private function __construct(private array $options, private ?string $refusalType)
    {
    }

    /**
     * @param list<array{reference: string, description: string, amount: array{currency: string, value: string}}> $options
     */
    public static function offer(array $options): self
    {
        return new self($options, null);
    }

    public static function refuse(string $type): self
    {
        return new self([], $type);
    }

    /**
     * @return list<array{reference: string, description: string, amount: array{currency: string, value: string}}>
     */
    public function options(): array
    {
        return $this->options;
    }

    /** One of Mollie's unsupported-* types, or null when options are offered. */
    public function refusalType(): ?string
    {
        return $this->refusalType;
    }
}

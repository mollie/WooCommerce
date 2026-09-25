<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Core\Types;

/**
 * An address as Mollie holds it on a payment, in Mollie's field names (givenName, streetAndNumber,
 * region, …). Personal data: it goes to the order through effects and is never a log field.
 */
final class MollieAddress
{
    /**
     * @param array<string, string> $fields
     */
    private function __construct(private array $fields)
    {
    }
    /**
     * Keeps the scalar, non-empty fields only.
     *
     * @param array<string, mixed> $fields
     */
    public static function fromArray(array $fields): self
    {
        $kept = [];
        foreach ($fields as $name => $value) {
            if (is_scalar($value) && trim((string) $value) !== '') {
                $kept[(string) $name] = (string) $value;
            }
        }
        return new self($kept);
    }
    /**
     * @return array<string, string>
     */
    public function fields(): array
    {
        return $this->fields;
    }
}

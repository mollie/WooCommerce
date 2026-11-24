<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\ExpressComponent\Rules;

use Mollie\WooCommerce\ExpressComponent\Rules\Values\ShippingOptionsVerdict;
use Mollie\WooCommerce\Shared\Values\Money;
/**
 * Turns the rates the store priced for an address into the options Mollie shows the shopper.
 * Mollie accepts 1 to 10 options and never an empty list: no price means a refusal.
 */
final class ShippingOptions
{
    public const MAX_OPTIONS = 10;
    // The store cannot tell an unsupported country from an unsupported postal code, so it never says which.
    public const UNSUPPORTED_ADDRESS = 'unsupported-address';
    /**
     * @param list<array{id: string, label: string, amount: Money}> $rates In WooCommerce's order, VAT included.
     */
    public static function decide(array $rates, bool $lookupFailed): ShippingOptionsVerdict
    {
        // A failed lookup refuses even when it left a rate behind: that price was not computed.
        if ($lookupFailed || $rates === []) {
            return ShippingOptionsVerdict::refuse(self::UNSUPPORTED_ADDRESS);
        }
        $options = [];
        foreach (array_slice($rates, 0, self::MAX_OPTIONS) as $rate) {
            $options[] = ['reference' => $rate['id'], 'description' => $rate['label'], 'amount' => ['currency' => $rate['amount']->currency(), 'value' => $rate['amount']->toDecimal()]];
        }
        return ShippingOptionsVerdict::offer($options);
    }
}

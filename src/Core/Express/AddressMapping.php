<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Core\Express;

/**
 * An address a wallet collected, from Mollie's field names to WooCommerce's.
 */
final class AddressMapping
{
    private const FIELDS = ['first_name' => 'givenName', 'last_name' => 'familyName', 'company' => 'organizationName', 'email' => 'email', 'phone' => 'phone', 'address_1' => 'streetAndNumber', 'address_2' => 'streetAdditional', 'postcode' => 'postalCode', 'city' => 'city', 'state' => 'region', 'country' => 'country'];
    /**
     * Mollie's region is WooCommerce's state, as given.
     *
     * @param array<string, mixed> $mollieFields Mollie field names, as on the payment.
     * @return array<string, string> WooCommerce field names without the billing_/shipping_ prefix.
     */
    public static function toWooCommerce(array $mollieFields): array
    {
        $wooCommerce = [];
        foreach (self::FIELDS as $wooField => $mollieField) {
            $value = $mollieFields[$mollieField] ?? null;
            if (!is_scalar($value)) {
                continue;
            }
            $value = trim((string) $value);
            if ($value !== '') {
                $wooCommerce[$wooField] = $value;
            }
        }
        return $wooCommerce;
    }
}

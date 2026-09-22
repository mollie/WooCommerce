<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Core\Express;

/**
 * Addresses between WooCommerce's field names and Mollie's.
 *
 * toMollie() shapes what the store holds for checkout.on('submit') resolve(): WooCommerce's state
 * is Mollie's region, as held, and what the store does not hold is left out so the wallet can still
 * collect it.
 */
final class AddressMapping
{
    private const TO_MOLLIE = [
        'first_name' => 'givenName',
        'last_name' => 'familyName',
        'company' => 'organizationName',
        'email' => 'email',
        'phone' => 'phone',
        'address_1' => 'streetAndNumber',
        'address_2' => 'streetAdditional',
        'postcode' => 'postalCode',
        'city' => 'city',
        'state' => 'region',
        'country' => 'country',
    ];

    /**
     * @param array<string, string> $wooFields WooCommerce field names without the billing_/shipping_ prefix.
     * @return array<string, string>
     */
    public static function toMollie(array $wooFields): array
    {
        $mollie = [];
        foreach (self::TO_MOLLIE as $wooField => $mollieField) {
            $value = trim((string) ($wooFields[$wooField] ?? ''));
            if ($value !== '') {
                $mollie[$mollieField] = $value;
            }
        }

        return $mollie;
    }
}

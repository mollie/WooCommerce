<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\ExpressComponent\WooCommerce;

use Mollie\WooCommerce\Shared\SharedDataDictionary;
/**
 * Whether a wallet's cart and checkout switches are on when the merchant never saved them.
 * The only reader of the defaults option. Options only: no form fields, no translation.
 */
final class ExpressDefaults
{
    /** Its cart and checkout switches came after existing installs made their choices, so none chose them. */
    private const ON_FOR_EXISTING_INSTALLS = 'mollie_wc_gateway_googlepay';
    public static function onByDefault(string $gatewayId): bool
    {
        if (get_option(SharedDataDictionary::EXPRESS_DEFAULT_ON_PARAM_NAME) === 'yes') {
            return \true;
        }
        return $gatewayId === self::ON_FOR_EXISTING_INSTALLS;
    }
}

<?php

use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Resources\CurrentProfile;
use Mollie\WooCommerce\Components\ComponentsStyles;
use Mollie\WooCommerce\Plugin;
use Mollie\WooCommerce\SDK\Api;
use Mollie\WooCommerce\Settings\SettingsComponents;

/**
 * Check if the current page context is for checkout
 *
 * @return bool
 */
function mollieWooCommerceIsCheckoutContext()
{
    global $wp_query;
    if (!isset($wp_query)) {
        return false;
    }
    return is_checkout() || is_checkout_pay_page();
}

/**
 * ComponentsStyles Factory
 *
 * @return array
 */
function mollieWooCommerceComponentsStylesForAvailableGateways()
{
    $pluginPath = untrailingslashit(M4W_PLUGIN_DIR) . '/';

    $mollieComponentsStyles = new ComponentsStyles(
        new SettingsComponents($pluginPath),
        WC()->payment_gateways()
    );

    return $mollieComponentsStyles->forAvailableGateways();
}
/**
 * Retrieve the cardToken value for Mollie Components
 *
 * @return string
 */
function mollieWooCommerceCardToken()
{
    //phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
    return wc_clean(wp_unslash($_POST["cardtoken"] ?? $_POST["cardToken"] ?? ''));
}

/**
 * Check if certain gateway setting is enabled.
 *
 * @param $gatewaySettingsName string
 * @param $settingToCheck string
 * @param bool $default
 * @return bool
 */
function mollieWooCommerceIsGatewayEnabled($gatewaySettingsName, $settingToCheck, $default = false)
{

    $gatewaySettings = get_option($gatewaySettingsName);
    return mollieWooCommerceStringToBoolOption(
        checkIndexExistOrDefault($gatewaySettings, $settingToCheck, $default)
    );
}

/**
 * Check if the Apple Pay gateway is enabled and then if the button is enabled too.
 *
 * @param $page
 *
 * @return bool
 */
function mollieWooCommerceisApplePayDirectEnabled($page)
{
    $pageToCheck = 'mollie_apple_pay_button_enabled_' . $page;
    return mollieWooCommerceIsGatewayEnabled('mollie_wc_gateway_applepay_settings', $pageToCheck);
}
/**
 * Check if the PayPal gateway is enabled and then if the button is enabled too.
 *
 * @param $page string setting to check between cart or product
 *
 * @return bool
 */
function mollieWooCommerceIsPayPalButtonEnabled($page)
{
    $payPalGatewayEnabled = mollieWooCommerceIsGatewayEnabled('mollie_wc_gateway_paypal_settings', 'enabled');

    if (!$payPalGatewayEnabled) {
        return false;
    }
    $settingToCheck = 'mollie_paypal_button_enabled_' . $page;
    return mollieWooCommerceIsGatewayEnabled('mollie_wc_gateway_paypal_settings', $settingToCheck);
}

/**
 * Check if the product needs shipping
 *
 * @param $product
 *
 * @return bool
 */
function mollieWooCommerceCheckIfNeedShipping($product)
{
    if (
        !wc_shipping_enabled()
        || 0 === wc_get_shipping_method_count(
            true
        )
    ) {
        return false;
    }
    //variations might be virtual
    if ($product->is_type('variable')) {
        $variations = $product->get_available_variations();
        foreach ($variations as $variation) {
            if ($variation["is_virtual"]) {
                return false;
            }
        }
        return true;
    }

    return $product->needs_shipping();
}

function checkIndexExistOrDefault($array, $key, $default)
{
    return isset($array[$key]) ? $array[$key] : $default;
}
/**
 * Check if the issuers dropdown for this gateway is enabled.
 *
 * @param string $gatewaySettingsName mollie_wc_gateway_xxxx_settings
 * @return bool
 */
function mollieWooCommerceIsDropdownEnabled($gatewaySettingsName)
{
    $gatewaySettings = get_option($gatewaySettingsName);
    $optionValue = checkIndexExistOrDefault($gatewaySettings, 'issuers_dropdown_shown', 'yes');
    return $optionValue == 'yes';
}


/**
 * Check if is a Mollie gateway
 * @param $gateway string
 *
 * @return bool
*/
function mollieWooCommerceIsMollieGateway($gateway)
{
    if (
        (is_string($gateway) && strpos($gateway, 'mollie_wc_gateway_') !== false)
        || (is_object($gateway) && strpos($gateway->id, 'mollie_wc_gateway_') !== false)
    ) {
        return true;
    }
    return false;
}

/**
 * Format the value that is sent to Mollie's API
 * with the required number of decimals
 * depending on the currency used
 *
 * @param $value
 * @param $currency
 * @return string
 */
function mollieWooCommerceFormatCurrencyValue($value, $currency)
{
    $currenciesWithNoDecimals = ["JPY", "ISK"];
    if (in_array($currency, $currenciesWithNoDecimals)) {
        return number_format($value, 0, '.', '');
    }

    return number_format($value, 2, '.', '');
}

function isMollieBirthValid($billing_birthdate)
{
    $today = new DateTime();
    $birthdate = DateTime::createFromFormat('Y-m-d', $billing_birthdate);
    if ($birthdate >= $today) {
        return false;
    }
    return true;
}

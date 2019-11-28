<?php

use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Resources\CurrentProfile;

/**
 * Check if the current page context is for checkout
 *
 * @return bool
 */
function isCheckoutContext()
{
    return is_checkout() || is_checkout_pay_page();
}

/**
 * Retrieve all of the available gateways registered in WooCommerce
 *
 * @return array
 */
function availablePaymentGateways()
{
    return WC()->payment_gateways()->get_available_payment_gateways();
}

/**
 * Is Mollie Test Mode enabled?
 *
 * @return bool
 */
// TODO change all of the other occurencies
function isTestModeEnabled()
{
    $settingsHelper = Mollie_WC_Plugin::getSettingsHelper();
    $isTestModeEnabled = $settingsHelper->isTestModeEnabled();

    return $isTestModeEnabled;
}

/**
 * Retrieve the instance of the Credit Card Gateway
 *
 * @param $gatewayKey
 * @param array $availableGateways
 * @return mixed
 * @throws OutOfBoundsException
 */
function gatewayFromAvailableGateways($gatewayKey, array $availableGateways)
{
    if (!isset($availableGateways[$gatewayKey])) {
        throw new OutOfBoundsException('Gateway Credit Card is not registered');
    }

    return $availableGateways[$gatewayKey];
}

/**
 * Retrieve the mollie components settings from the given gateway
 *
 * @param WC_Payment_Gateway $gateway
 * @return array
 */
function componentsSettings(WC_Payment_Gateway $gateway)
{
    $gatewayComponentsSettings = get_option($gateway->get_option_key(), null) ?: [];

    return $gatewayComponentsSettings;
}

/**
 * Retrieve the default mollie components settings values from the given gateway
 *
 * @param WC_Payment_Gateway $gateway
 * @return array
 */
function defaultComponentSettings(WC_Payment_Gateway $gateway)
{
    $settings = $gateway->settings;
    $defaultComponentSettings = [];

    $componentKeys = array_merge(
        Mollie_WC_Components_Styles::STYLES_OPTIONS_KEYS_MAP,
        Mollie_WC_Components_Styles::INVALID_STYLES_OPTIONS_KEYS_MAP
    );

    foreach ($settings as $key => $value) {
        if (in_array($key, $componentKeys, true)) {
            $defaultComponentSettings[$key] = $value;
        }
    }

    return $defaultComponentSettings;
}

/**
 * @return CurrentProfile
 * @throws ApiException
 */
function merchantProfile()
{
    $settingsHelper = Mollie_WC_Plugin::getSettingsHelper();
    $isTestMode = $settingsHelper->isTestModeEnabled();

    $apiHelper = Mollie_WC_Plugin::getApiHelper();
    $profile = $apiHelper->getApiClient($isTestMode)->profiles->getCurrent();

    return $profile;
}

/**
 * Retrieve the cardToken value for Mollie Components
 *
 * @return string
 */
function cardToken()
{
    return $cardToken = filter_input(INPUT_POST, 'cardToken', FILTER_SANITIZE_STRING) ?: '';
}

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

function availableGatewaysWithMollieComponentsEnabled()
{
    // TODO May be we want to cache them for the current request?

    $gatewaysWithMollieComponentsEnabled = [];
    $availablePaymentGateways = availablePaymentGateways();

    /** @var WC_Payment_Gateway $gateway */
    foreach ($availablePaymentGateways as $gateway) {
        $isGatewayEnabled = wc_string_to_bool($gateway->enabled);
        // TODO The mollie_components_enabled should be a constant somewhere
        if ($isGatewayEnabled && isMollieComponentsEnabledForGateway($gateway)) {
            $gatewaysWithMollieComponentsEnabled[] = $gateway;
        }
    }

    return $gatewaysWithMollieComponentsEnabled;
}

function gatewayNames(array $gateways)
{
    $gatewayNames = [];

    /** @var WC_Payment_Gateway $gateway */
    foreach ($gateways as $gateway) {
        $gatewayNames[] = str_replace('mollie_wc_gateway_', '', $gateway->id);
    }

    return $gatewayNames;
}

function isMollieComponentsEnabledForGateway(WC_Payment_Gateway $gateway)
{
    if (!isset($gateway->settings['mollie_components_enabled'])) {
        return false;
    }

    return wc_string_to_bool($gateway->settings['mollie_components_enabled']);
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
 * Retrieve the merchant profile ID
 *
 * @return int|string
 * @throws ApiException
 */
function merchantProfileId()
{
    $merchantProfile = merchantProfile();

    return isset($merchantProfile->id) ? $merchantProfile->id : 0;
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

/**
 * Retrieve the mollie components styles for all of the available Gateways
 *
 * Gateways are enabled along with mollie components
 *
 * @return array
 */
function mollieComponentsStylesForAvailableGateways()
{
    $mollieComponentsSettings = new Mollie_WC_Settings_Components();
    $gatewaysWithMollieComponentsEnabled = availableGatewaysWithMollieComponentsEnabled();

    if (!$gatewaysWithMollieComponentsEnabled) {
        return [];
    }

    return mollieComponentsStylesPerGateway(
        $gatewaysWithMollieComponentsEnabled,
        $mollieComponentsSettings
    );
}

/**
 * Retrieve the mollie components styles associated to the given gateways
 *
 * @param array $gateways
 * @param Mollie_WC_Settings_Components $mollieComponentsSettings
 * @return array
 */
function mollieComponentsStylesPerGateway(
    array $gateways,
    Mollie_WC_Settings_Components $mollieComponentsSettings
) {

    $gatewayNames = gatewayNames($gateways);
    $mollieComponentsStylesGateways = array_combine(
        $gatewayNames,
        array_fill(0, count($gatewayNames), ['styles' => $mollieComponentsSettings->styles()])
    );

    return $mollieComponentsStylesGateways ?: [];
}

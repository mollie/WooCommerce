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
 * Mollie_WC_Components_Styles Factory
 *
 * @return array
 */
function mollieComponentsStylesForAvailableGateways()
{
    $mollieComponentsStyles = new Mollie_WC_Components_Styles(
        new Mollie_WC_Settings_Components(),
        WC()->payment_gateways()
    );

    return $mollieComponentsStyles->forAvailableGateways();
}

/**
 * Is Mollie Test Mode enabled?
 *
 * @return bool
 */
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
    static $profile = null;

    if (null === $profile) {
        $isTestMode = isTestModeEnabled();

        $apiHelper = Mollie_WC_Plugin::getApiHelper();
        $profile = $apiHelper->getApiClient($isTestMode)->profiles->getCurrent();
    }

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
 * Isolates static debug calls.
 *
 * @param  string $message
 * @param bool  $set_debug_header Set X-Mollie-Debug header (default false)
 */
function debug($message, $set_debug_header = false)
{
    Mollie_WC_Plugin::debug($message, $set_debug_header);
}

/**
 * Isolates static addNotice calls.
 *
 * @param  string $message
 * @param string $type    One of notice, error or success (default notice)
 */
function notice($message, $type = 'notice')
{
    Mollie_WC_Plugin::addNotice($message, $type);
}



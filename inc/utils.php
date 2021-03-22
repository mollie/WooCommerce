<?php

use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Resources\CurrentProfile;

/**
 * Check if the current page context is for checkout
 *
 * @return bool
 */
function mollieWooCommerceIsCheckoutContext()
{
    return is_checkout() || is_checkout_pay_page();
}

/**
 * Mollie_WC_Components_Styles Factory
 *
 * @return array
 */
function mollieWooCommerceComponentsStylesForAvailableGateways()
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
function mollieWooCommerceIsTestModeEnabled()
{
    $settingsHelper = Mollie_WC_Plugin::getSettingsHelper();
    $isTestModeEnabled = $settingsHelper->isTestModeEnabled();

    return $isTestModeEnabled;
}

/**
 * If we are calling this the api key has been updated, we need a new api object
 * to retrieve a new profile id
 *
 * @return CurrentProfile
 * @throws ApiException
 */
function mollieWooCommerceMerchantProfile()
{
    $isTestMode = mollieWooCommerceIsTestModeEnabled();

    $apiHelper = new Mollie_WC_Helper_Api(
        Mollie_WC_Plugin::getSettingsHelper()
    );

    return $apiHelper->getApiClient(
        $isTestMode,
        true
    )->profiles->getCurrent();
}

/**
 * Retrieve the merchant profile ID
 *
 * @return int|string
 * @throws ApiException
 */
function mollieWooCommerceMerchantProfileId()
{
    static $merchantProfileId = null;
    $merchantProfileIdOptionKey = Mollie_WC_Plugin::PLUGIN_ID . '_profile_merchant_id';

    if ($merchantProfileId === null) {
        $merchantProfileId = get_option($merchantProfileIdOptionKey, '');

        /*
         * Try to retrieve the merchant profile ID from an Api Request if not stored already,
         * then store it into the database
         */
        if (!$merchantProfileId) {
            try {
                $merchantProfile = mollieWooCommerceMerchantProfile();
                $merchantProfileId = isset($merchantProfile->id) ? $merchantProfile->id : '';
            } catch (ApiException $exception) {
                $merchantProfileId = '';
            }

            if ($merchantProfileId) {
                update_option($merchantProfileIdOptionKey, $merchantProfileId);
            }
        }
    }

    return $merchantProfileId;
}

/**
 * Retrieve the cardToken value for Mollie Components
 *
 * @return string
 */
function mollieWooCommerceCardToken()
{
    return $cardToken = filter_input(INPUT_POST, 'cardToken', FILTER_SANITIZE_STRING) ?: '';
}

/**
 * Retrieve the available Payment Methods Data
 *
 * @return array|bool|mixed|\Mollie\Api\Resources\BaseCollection|\Mollie\Api\Resources\MethodCollection
 */
function mollieWooCommerceAvailablePaymentMethods()
{
    $testMode = mollieWooCommerceIsTestModeEnabled();
    $dataHelper = Mollie_WC_Plugin::getDataHelper();
    $methods = $dataHelper->getApiPaymentMethods($testMode, $use_cache = true);

    return $methods;
}

/**
 * Isolates static debug calls.
 *
 * @param  string $message
 * @param bool  $set_debug_header Set X-Mollie-Debug header (default false)
 */
function mollieWooCommerceDebug($message, $set_debug_header = false)
{
    Mollie_WC_Plugin::debug($message, $set_debug_header);
}

/**
 * Isolates static addNotice calls.
 *
 * @param  string $message
 * @param string $type    One of notice, error or success (default notice)
 */
function mollieWooCommerceNotice($message, $type = 'notice')
{
    Mollie_WC_Plugin::addNotice($message, $type);
}
/**
 * Isolates static getDataHelper calls.
 *
 * @return Mollie_WC_Helper_Data
 */
function mollieWooCommerceGetDataHelper()
{
    return Mollie_WC_Plugin::getDataHelper();
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
    $pageToCheck = 'mollie_apple_pay_button_enabled_'.$page;
    return mollieWooCommerceIsGatewayEnabled('mollie_wc_gateway_applepay_settings', $pageToCheck);
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
 * Check if the Voucher gateway is enabled.
 *
 * @return bool
 */
function mollieWooCommerceIsVoucherEnabled(){
    $voucherSettings = get_option('mollie_wc_gateway_mealvoucher_settings');
    return $voucherSettings? ($voucherSettings['enabled'] == 'yes'): false;
}



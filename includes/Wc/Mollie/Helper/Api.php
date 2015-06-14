<?php
class WC_Mollie_Helper_Api
{
    /**
     * @var Mollie_API_Client
     */
    protected static $api_client;

    /**
     * @var WC_Mollie_Helper_Settings
     */
    protected $settings_helper;

    /**
     * @param WC_Mollie_Helper_Settings $settings_helper
     */
    public function __construct (WC_Mollie_Helper_Settings $settings_helper)
    {
        $this->settings_helper = $settings_helper;
    }

    /**
     * @return Mollie_API_Client
     * @throws Mollie_API_Exception
     * @throws WC_Mollie_Exception_InvalidApiKey
     */
    public function getApiClient ()
    {
        $api_key = trim($this->settings_helper->getApiKey());

        if (empty($api_key))
        {
            throw new WC_Mollie_Exception_InvalidApiKey(__('No API key provided.', 'woocommerce-mollie-payments'));
        }
        elseif (!preg_match('/^(live|test)_\w+$/', $api_key))
        {
            throw new WC_Mollie_Exception_InvalidApiKey(__('Invalid API key. The API key must start with \'test_\' or \'live_\' and can\'t further contain any special characters.', 'woocommerce-mollie-payments'));
        }

        if (empty(self::$api_client))
        {
            $client = new Mollie_API_Client();
            $client->setApiKey($api_key);
            $client->setApiEndpoint(apply_filters(WC_Mollie::PLUGIN_ID . '_api_endpoint', Mollie_API_Client::API_ENDPOINT));
            $client->addVersionString('WordPress/'   . (isset($wp_version) ? $wp_version : 'Unknown'));
            $client->addVersionString('WooCommerce/' . get_option('woocommerce_version', 'Unknown'));
            $client->addVersionString('MollieWoo/'   . WC_Mollie::PLUGIN_VERSION);

            self::$api_client = $client;
        }

        return self::$api_client;
    }
}

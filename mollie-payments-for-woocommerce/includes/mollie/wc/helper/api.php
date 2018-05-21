<?php
use Mollie\Api\MollieApiClient;
class Mollie_WC_Helper_Api
{
    /**
     * @var \Mollie\Api\MollieApiClient
     */
    protected static $api_client;

    /**
     * @var Mollie_WC_Helper_Settings
     */
    protected $settings_helper;

    /**
     * @param Mollie_WC_Helper_Settings $settings_helper
     */
    public function __construct (Mollie_WC_Helper_Settings $settings_helper)
    {
        $this->settings_helper = $settings_helper;
    }

    /**
     * @param bool $test_mode
     * @return \Mollie\Api\MollieApiClient
     * @throws Mollie_WC_Exception_InvalidApiKey
     */
    public function getApiClient ($test_mode = false)
    {
        global $wp_version;

        $api_key = $this->settings_helper->getApiKey($test_mode);

        if(has_filter('mollie_api_key_filter')) {
            $api_key = apply_filters('mollie_api_key_filter', $api_key);
        }

        if (empty($api_key))
        {
            throw new Mollie_WC_Exception_InvalidApiKey(__('No API key provided.', 'mollie-payments-for-woocommerce'));
        }
        elseif (!preg_match('/^(live|test)_\w+$/', $api_key))
        {
            throw new Mollie_WC_Exception_InvalidApiKey(__('Invalid API key. The API key must start with \'live_\' or \'test_\' and can\'t further contain any special characters.', 'mollie-payments-for-woocommerce'));
        }

        if (empty(self::$api_client))
        {
		        $client = new MollieApiClient();
		        $client->setApiKey( $api_key );
		        $client->setApiEndpoint( self::getApiEndpoint() );
		        $client->addVersionString( 'WordPress/' . ( isset( $wp_version ) ? $wp_version : 'Unknown' ) );
		        $client->addVersionString( 'WooCommerce/' . get_option( 'woocommerce_version', 'Unknown' ) );
		        $client->addVersionString( 'WooCommerceSubscriptions/' . get_option( 'woocommerce_subscriptions_active_version', 'Unknown' ) );
		        $client->addVersionString( 'MollieWoo/' . Mollie_WC_Plugin::PLUGIN_VERSION );


            self::$api_client = $client;
        }

        return self::$api_client;
    }

    /**
     * Get API endpoint. Override using filter.
     * @return string
     * @throws \Mollie_WC_Exception_InvalidApiKey
     */
    public static function getApiEndpoint ()
    {
        return apply_filters(Mollie_WC_Plugin::PLUGIN_ID . '_api_endpoint', \Mollie\Api\MollieApiClient::API_ENDPOINT);
    }

}

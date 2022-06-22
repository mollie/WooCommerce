<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\SDK;

use Mollie\Api\MollieApiClient;

class Api
{
    /**
     * @var \Mollie\Api\MollieApiClient
     */
    protected static $api_client;
    /**
     * @var string
     */
    protected $pluginVersion;
    /**
     * @var string
     */
    protected $pluginId;

    public function __construct(string $pluginVersion, string $pluginId)
    {
        $this->pluginVersion = $pluginVersion;
        $this->pluginId = $pluginId;
    }

    /**
     * @param bool $test_mode
     * @param bool $needToUpdateApiKey If the apiKey was updated discard the old instance, and create a new one with the new key.
     *
     * @return \Mollie\Api\MollieApiClient
     * @throws \Mollie\Api\Exceptions\ApiException
     */
    public function getApiClient($apiKey, $needToUpdateApiKey = false)
    {

        global $wp_version;

        if (has_filter('mollie_api_key_filter')) {
            /**
             * Overwrite the Mollie API key.
             *
             * @since 2.6.0
             *
             * @param string $apiKey saved in db api key.
             */
            $apiKey = apply_filters('mollie_api_key_filter', $apiKey);
        }

        if (empty($apiKey)) {
            throw new \Mollie\Api\Exceptions\ApiException(__('No API key provided. Please set your Mollie API keys below.', 'mollie-payments-for-woocommerce'));
        } elseif (! preg_match('#^(live|test)_\w{30,}$#', $apiKey)) {
            throw new \Mollie\Api\Exceptions\ApiException(sprintf(__("Invalid API key(s). Get them on the %1\$sDevelopers page in the Mollie dashboard%2\$s. The API key(s) must start with 'live_' or 'test_', be at least 30 characters and must not contain any special characters.", 'mollie-payments-for-woocommerce'), '<a href="https://www.mollie.com/dashboard/developers/api-keys" target="_blank">', '</a>'));
        }

        if (empty(self::$api_client) || $needToUpdateApiKey) {
            $client = new MollieApiClient(null, new WordPressHttpAdapterPicker());
            $client->setApiKey($apiKey);
            $client->setApiEndpoint($this->getApiEndpoint());
            $client->addVersionString('WooCommerce/' . get_option('woocommerce_version', 'Unknown'));
            $client->addVersionString('WooCommerceSubscriptions/' . get_option('woocommerce_subscriptions_active_version', 'Unknown'));
            $client->addVersionString('MollieWoo/' . $this->pluginVersion);

            self::$api_client = $client;
        }

        return self::$api_client;
    }

    /**
     * Get API endpoint. Override using filter.
     * @return string
     */
    public function getApiEndpoint()
    {
        /**
         * Overwrite the Mollie endpoint.
         *
         * @since 2.0.0
         *
         * @param string "https://api.mollie.com" endpoint.
         */
        return apply_filters($this->pluginId . '_api_endpoint', \Mollie\Api\MollieApiClient::API_ENDPOINT);
    }
}

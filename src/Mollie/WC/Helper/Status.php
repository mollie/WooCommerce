<?php

use Mollie\Api\CompatibilityChecker;
use Mollie\Api\Exceptions\IncompatiblePlatform;
use Mollie\Api\MollieApiClient;


class Mollie_WC_Helper_Status
{
    /**
     * Minimal required WooCommerce version
     *
     * @var string
     */
    const MIN_WOOCOMMERCE_VERSION = '2.2.0';

    /**
     * @var string[]
     */
    protected $errors = array();

    /**
     * @var CompatibilityChecker
     */
    protected $compatibilityChecker;

    public function __construct(Mollie\Api\CompatibilityChecker $compatibilityChecker)
    {
        $this->compatibilityChecker = $compatibilityChecker;
    }

    /**
     * @return bool
     */
    public function hasErrors ()
    {
        return !empty($this->errors);
    }

    /**
     * @return string[]
     */
    public function getErrors ()
    {
        return $this->errors;
    }

    /**
     * Check if this plugin is compatible
     *
     * @return bool
     */
    public function isCompatible()
    {
        static $isCompatible = null;

        if ($isCompatible !== null) {
            return $isCompatible;
        }

        // Default
        $isCompatible = true;

        if (!$this->hasCompatibleWooCommerceVersion()) {
            $this->errors[] = sprintf(
            /* translators: Placeholder 1: Plugin name, placeholder 2: required WooCommerce version, placeholder 3: used WooCommerce version */
                __(
                    'The %s plugin requires at least WooCommerce version %s, you are using version %s. Please update your WooCommerce plugin.',
                    'mollie-payments-for-woocommerce'
                ),
                Mollie_WC_Plugin::PLUGIN_TITLE,
                self::MIN_WOOCOMMERCE_VERSION,
                $this->getWooCommerceVersion()
            );

            return $isCompatible = false;
        }

        if (!$this->isApiClientInstalled()) {
            $this->errors[] = __(
                'Mollie API client not installed. Please make sure the plugin is installed correctly.',
                'mollie-payments-for-woocommerce'
            );

            return $isCompatible = false;
        }

        if (function_exists('extension_loaded') && !extension_loaded('json')) {
            $this->errors[] = __(
                'Mollie Payments for WooCommerce requires the JSON extension for PHP. Enable it in your server or ask your webhoster to enable it for you.',
                'mollie-payments-for-woocommerce'
            );

            return $isCompatible = false;
        }

        try {
            $this->compatibilityChecker->checkCompatibility();
        } catch (IncompatiblePlatform $exception) {
            switch ($exception->getCode()) {
                case IncompatiblePlatform::INCOMPATIBLE_PHP_VERSION:
                    $error = sprintf(
                    /* translators: Placeholder 1: Required PHP version, placeholder 2: current PHP version */
                        __(
                            'Mollie Payments for WooCommerce requires PHP %s or higher, you have PHP %s. Please upgrade and view %sthis FAQ%s',
                            'mollie-payments-for-woocommerce'
                        ),
                        CompatibilityChecker::MIN_PHP_VERSION,
                        PHP_VERSION,
                        '<a href="https://github.com/mollie/WooCommerce/wiki/PHP-&-Mollie-API-v2" target="_blank">',
                        '</a>'
                    );
                    break;

                case IncompatiblePlatform::INCOMPATIBLE_JSON_EXTENSION:
                    $error = __(
                        'Mollie Payments for WooCommerce requires the PHP extension JSON to be enabled. Please enable the \'json\' extension in your PHP configuration.',
                        'mollie-payments-for-woocommerce'
                    );
                    break;

                case IncompatiblePlatform::INCOMPATIBLE_CURL_EXTENSION:
                    $error = __(
                        'Mollie Payments for WooCommerce requires the PHP extension cURL to be enabled. Please enable the \'curl\' extension in your PHP configuration.',
                        'mollie-payments-for-woocommerce'
                    );
                    break;

                case IncompatiblePlatform::INCOMPATIBLE_CURL_FUNCTION:
                    $error =
                        __(
                            'Mollie Payments for WooCommerce requires PHP cURL functions to be available. Please make sure all of these functions are available.',
                            'mollie-payments-for-woocommerce'
                        );
                    break;

                default:
                    $error = $exception->getMessage();
                    break;
            }

            $this->errors[] = $error;

            return $isCompatible = false;
        }

        return $isCompatible;
    }

    /**
     * @return string
     */
    public function getWooCommerceVersion ()
    {
        return WooCommerce::instance()->version;
    }

    /**
     * @return bool
     */
    public function hasCompatibleWooCommerceVersion ()
    {
        return (bool) version_compare($this->getWooCommerceVersion(), self::MIN_WOOCOMMERCE_VERSION, ">=");
    }

    /**
     * @return bool
     */
    protected function isApiClientInstalled ()
    {
        return class_exists(MollieApiClient::class);
    }

    /**
     * @throws \Mollie\Api\Exceptions\ApiException
     */
    public function getMollieApiStatus ()
    {
        try
        {
            // Is test mode enabled?
            $test_mode  = Mollie_WC_Plugin::getSettingsHelper()->isTestModeEnabled();

            $api_helper = Mollie_WC_Plugin::getApiHelper();
            $api_client = $api_helper->getApiClient($test_mode);

            // Try to load Mollie issuers
            $api_client->methods->all();
        }
        catch ( \Mollie\Api\Exceptions\ApiException $e )
        {

	        if ( $e->getMessage() == 'Error executing API call (401: Unauthorized Request): Missing authentication, or failed to authenticate. Documentation: https://docs.mollie.com/guides/authentication') {
		        throw new \Mollie\Api\Exceptions\ApiException(
			        'incorrect API key or other authentication issue. Please check your API keys!'
		        );
	        }

            throw new \Mollie\Api\Exceptions\ApiException(
                $e->getMessage()
            );
        }
    }
}

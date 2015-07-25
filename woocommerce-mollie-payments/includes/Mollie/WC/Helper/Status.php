<?php
class Mollie_WC_Helper_Status
{
    /**
     * Minimal required WooCommerce version
     *
     * @todo: test plugin with older WooCommerce versions
     * @var string
     */
    const MIN_WOOCOMMERCE_VERSION = '2.1.0';

    /**
     * @var string[]
     */
    protected $errors = array();

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
    public function isCompatible ()
    {
        static $is_compatible = null;

        if ($is_compatible !== null)
        {
            return $is_compatible;
        }

        // Default
        $is_compatible = true;

        if (!$this->hasCompatibleWooCommerceVersion())
        {
            $this->errors[] = sprintf(
                /* translators: Placeholder 1: required WooCommerce version, placeholder 2: used WooCommerce version */
                __('The WooCommerce Mollie Payments plugin requires at least WooCommerce version %s, you are using version %s. Please update your WooCommerce plugin.', 'woocommerce-mollie-payments'),
                self::MIN_WOOCOMMERCE_VERSION,
                $this->getWooCommerceVersion()
            );

            return $is_compatible = false;
        }

        if (!$this->isApiClientInstalled())
        {
            $this->errors[] = __('Mollie API client not installed. Please make sure the plugin is installed correctly.', 'woocommerce-mollie-payments');

            return $is_compatible = false;
        }

        try
        {
            $checker = $this->getApiClientCompatibilityChecker();

            $checker->checkCompatibility();
        }
        catch (Mollie_API_Exception_IncompatiblePlatform $e)
        {
            switch ($e->getCode())
            {
                case Mollie_API_Exception_IncompatiblePlatform::INCOMPATIBLE_PHP_VERSION:
                    $error = sprintf(
                        /* translators: Placeholder 1: Required PHP version, placeholder 2: current PHP version */
                        __('The client requires PHP version >= %s, you have %s.', 'woocommerce-mollie-payments'),
                        Mollie_API_CompatibilityChecker::$MIN_PHP_VERSION,
                        PHP_VERSION
                    );
                    break;

                case Mollie_API_Exception_IncompatiblePlatform::INCOMPATIBLE_JSON_EXTENSION:
                    $error = __('The Mollie API client requires the PHP extension JSON to be enabled. Please enable the \'json\' extension in your PHP configuration.', 'woocommerce-mollie-payments');
                    break;

                case Mollie_API_Exception_IncompatiblePlatform::INCOMPATIBLE_CURL_EXTENSION:
                    $error = __('The Mollie API client requires the PHP extension cURL to be enabled. Please enable the \'curl\' extension in your PHP configuration.', 'woocommerce-mollie-payments');
                    break;

                case Mollie_API_Exception_IncompatiblePlatform::INCOMPATIBLE_CURL_FUNCTION:
                    $error = sprintf(
                        /* translators: Placeholder 1: The required cURL function names */
                        __('The Mollie API client requires the following PHP cURL functions to be available: %s. Please make sure all of these functions are available.', 'woocommerce-mollie-payments'),
                        implode(', ', Mollie_API_CompatibilityChecker::$REQUIRED_CURL_FUNCTIONS)
                    );
                    break;

                default:
                    $error = $e->getMessage();
                    break;
            }

            $this->errors[] = $error;

            return $is_compatible = false;
        }

        return $is_compatible;
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
        $includes_dir = dirname(dirname(dirname(dirname(__FILE__))));

        return file_exists($includes_dir . '/mollie-api-php');
    }

    /**
     * @throws Mollie_WC_Exception_CouldNotConnectToMollie
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
            $api_client->issuers->all();
        }
        catch (Mollie_API_Exception $e)
        {
            throw new Mollie_WC_Exception_CouldNotConnectToMollie(
                $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * @return Mollie_API_CompatibilityChecker
     */
    protected function getApiClientCompatibilityChecker ()
    {
        return new Mollie_API_CompatibilityChecker();
    }
}

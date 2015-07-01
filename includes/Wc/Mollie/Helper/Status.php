<?php
class WC_Mollie_Helper_Status
{
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
     * - Check if Mollie API client is found
     * -
     * @return bool
     */
    public function isCompatible ()
    {
        static $is_compatible = null;

        if ($is_compatible !== null)
        {
            return $is_compatible;
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

        return $is_compatible = true;
    }

    /**
     * @throws WC_Mollie_Exception_IncompatiblePlatform
     * @return void
     */
    public function checkCompatibility ()
    {
        // Make sure autoloader is registered
        require_once dirname(dirname(__FILE__)) . '/Autoload.php';

        WC_Mollie_Autoload::register();

        if (!$this->isApiClientInstalled())
        {
            throw new WC_Mollie_Exception_IncompatiblePlatform(
                "Mollie API client not found. Plugin not installed correctly.",
                WC_Mollie_Exception_IncompatiblePlatform::API_CLIENT_NOT_INSTALLED
            );
        }

        try
        {
            $checker = $this->getApiClientCompatibilityChecker();

            $checker->checkCompatibility();
        }
        catch (Mollie_API_Exception_IncompatiblePlatform $e)
        {
            throw new WC_Mollie_Exception_IncompatiblePlatform(
                $e->getMessage(),
                WC_Mollie_Exception_IncompatiblePlatform::API_CLIENT_NOT_COMPATIBLE,
                $e
            );
        }
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
     * @throws WC_Mollie_Exception_CouldNotConnectToMollie
     */
    public function getMollieApiStatus ()
    {
        try
        {
            // Is test mode enabled?
            $test_mode  = WC_Mollie::getSettingsHelper()->isTestModeEnabled();

            $api_helper = WC_Mollie::getApiHelper();
            $api_client = $api_helper->getApiClient($test_mode);

            // Try to load Mollie issuers
            $api_client->issuers->all();
        }
        catch (Mollie_API_Exception $e)
        {
            throw new WC_Mollie_Exception_CouldNotConnectToMollie(
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

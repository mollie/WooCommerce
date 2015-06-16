<?php
class WC_Mollie_Helper_CompatibilityChecker
{
    /**
     * @throws WC_Mollie_Exception_IncompatiblePlatform
     * @return void
     */
    public function checkCompatibility ()
    {
        // Make sure autoloader is registered
        require_once dirname(dirname(__FILE__)) . '/Autoload.php';
        WC_Mollie_Autoload::register();

        if (!class_exists('Mollie_API_Client'))
        {
            throw new WC_Mollie_Exception_IncompatiblePlatform(
                "Mollie API client not found. Please install plugin again.",
                WC_Mollie_Exception_IncompatiblePlatform::API_CLIENT_NOT_INSTALLED
            );
        }

        try
        {
            $api_helper = WC_Mollie::getApiHelper();
            $api_client = $api_helper->getApiClient();

            // Try to load Mollie issuers
            $issuers = $api_client->issuers->all();
        }
        catch (Mollie_API_Exception_IncompatiblePlatform $e)
        {
            throw new WC_Mollie_Exception_IncompatiblePlatform(
                $e->getMessage(),
                WC_Mollie_Exception_IncompatiblePlatform::PHP_NOT_COMPATIBLE,
                $e
            );
        }
        catch (Mollie_API_Exception $e)
        {
            throw new WC_Mollie_Exception_IncompatiblePlatform(
                $e->getMessage(),
                WC_Mollie_Exception_IncompatiblePlatform::COULD_NOT_CONNECT_TO_MOLLIE,
                $e
            );
        }
    }
}

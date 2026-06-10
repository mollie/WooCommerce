<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Shared;

use Mollie\Api\CompatibilityChecker;
use Mollie\Api\Exceptions\IncompatiblePlatform;
use Mollie\Api\MollieApiClient;
use WooCommerce;
class Status
{
    /**
     * Minimal required WooCommerce version
     *
     * @var string
     */
    public const MIN_WOOCOMMERCE_VERSION = '3.0';
    /**
     * @var string[]
     */
    protected $errors = [];
    /**
     * @var CompatibilityChecker
     */
    protected $compatibilityChecker;
    protected $pluginTitle;
    public function __construct(CompatibilityChecker $compatibilityChecker, string $pluginTitle)
    {
        $this->compatibilityChecker = $compatibilityChecker;
        $this->pluginTitle = $pluginTitle;
    }
    /**
     * @return bool
     */
    public function hasErrors()
    {
        return !empty($this->errors);
    }
    /**
     * @return string[]
     */
    public function getErrors()
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
        $isCompatible = \true;
        if (!$this->hasCompatibleWooCommerceVersion()) {
            $this->errors[] = sprintf(
                /* translators: Placeholder 1: Plugin title. Placeholder 2: Min WooCommerce version. Placeholder 3: WooCommerce version used. */
                __('The %1$s plugin requires at least WooCommerce version %2$s, you are using version %3$s. Please update your WooCommerce plugin.', 'mollie-payments-for-woocommerce'),
                $this->pluginTitle,
                self::MIN_WOOCOMMERCE_VERSION,
                $this->getWooCommerceVersion()
            );
            return $isCompatible = \false;
        }
        if (!$this->isApiClientInstalled()) {
            $this->errors[] = __('Mollie API client not installed. Please make sure the plugin is installed correctly.', 'mollie-payments-for-woocommerce');
            return $isCompatible = \false;
        }
        if (function_exists('extension_loaded') && !extension_loaded('json')) {
            $this->errors[] = __('Mollie Payments for WooCommerce requires the JSON extension for PHP. Enable it in your server or ask your webhoster to enable it for you.', 'mollie-payments-for-woocommerce');
            return $isCompatible = \false;
        }
        try {
            $this->compatibilityChecker->checkCompatibility();
        } catch (IncompatiblePlatform $incompatiblePlatform) {
            switch ($incompatiblePlatform->getCode()) {
                case IncompatiblePlatform::INCOMPATIBLE_PHP_VERSION:
                    $error = sprintf(
                        /* translators: Placeholder 1: Min PHP version. Placeholder 2: PHP version used. Placeholder 3: Opening link tag. placeholder 4: Closing link tag. */
                        __('Mollie Payments for WooCommerce require PHP %1$s or higher, you have PHP %2$s. Please upgrade and view %3$sthis FAQ%4$s', 'mollie-payments-for-woocommerce'),
                        CompatibilityChecker::MIN_PHP_VERSION,
                        \PHP_VERSION,
                        '<a href="https://github.com/mollie/WooCommerce/wiki/PHP-&-Mollie-API-v2" target="_blank">',
                        '</a>'
                    );
                    break;
                case IncompatiblePlatform::INCOMPATIBLE_JSON_EXTENSION:
                    $error = __("Mollie Payments for WooCommerce requires the PHP extension JSON to be enabled. Please enable the 'json' extension in your PHP configuration.", 'mollie-payments-for-woocommerce');
                    break;
                case IncompatiblePlatform::INCOMPATIBLE_CURL_EXTENSION:
                    $error = __("Mollie Payments for WooCommerce requires the PHP extension cURL to be enabled. Please enable the 'curl' extension in your PHP configuration.", 'mollie-payments-for-woocommerce');
                    break;
                case IncompatiblePlatform::INCOMPATIBLE_CURL_FUNCTION:
                    $error = __('Mollie Payments for WooCommerce require PHP cURL functions to be available. Please make sure all of these functions are available.', 'mollie-payments-for-woocommerce');
                    break;
                default:
                    $error = $incompatiblePlatform->getMessage();
                    break;
            }
            $this->errors[] = $error;
            return $isCompatible = \false;
        }
        return $isCompatible;
    }
    /**
     * @return string
     */
    public function getWooCommerceVersion()
    {
        return WooCommerce::instance()->version;
    }
    /**
     * @return bool
     */
    public function hasCompatibleWooCommerceVersion()
    {
        return version_compare($this->getWooCommerceVersion(), self::MIN_WOOCOMMERCE_VERSION, ">=");
    }
    /**
     * @return bool
     */
    protected function isApiClientInstalled()
    {
        return class_exists(MollieApiClient::class);
    }
    /**
     * @throws \Mollie\Api\Exceptions\ApiException
     */
    public function getMollieApiStatus($apiClient)
    {
        try {
            // Try to load Mollie issuers
            $apiClient->methods->all();
        } catch (\Mollie\Api\Exceptions\ApiException $apiException) {
            if ($apiException->getMessage() === 'Error executing API call (401: Unauthorized Request): Missing authentication, or failed to authenticate. Documentation: https://docs.mollie.com/guides/authentication') {
                throw new \Mollie\Api\Exceptions\ApiException(esc_html__('incorrect API key or other authentication issue. Please check your API keys!', 'mollie-payments-for-woocommerce'));
            }
            $message = $apiException->getMessage();
            throw new \Mollie\Api\Exceptions\ApiException(esc_html($message));
        }
    }
}

<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Settings;

use DateInterval;
use DateTime;
use Mollie\Api\Exceptions\ApiException;
use Mollie\WooCommerce\Gateway\Surcharge;
use Mollie\WooCommerce\Notice\AdminNotice;
use Mollie\WooCommerce\Payment\PaymentProcessor;
use Mollie\WooCommerce\Settings\General\MollieGeneralSettings;
use Mollie\WooCommerce\Shared\SharedDataDictionary;
use Mollie\WooCommerce\Shared\Status;
class Settings
{
    /**
     * Stage that produced a connection failure, reported as 'error_kind'.
     */
    public const ERROR_KIND_INCOMPATIBLE = 'incompatible';
    public const ERROR_KIND_API_KEY = 'api_key';
    public const ERROR_KIND_API = 'api';
    protected $pluginId;
    protected $pluginVersion;
    protected $pluginUrl;
    protected $cleanDb;
    protected $globalSettingsUrl;
    protected $statusHelper;
    protected $apiHelper;
    /**
     * Settings constructor.
     */
    public function __construct($pluginId, $statusHelper, $pluginVersion, $pluginUrl, $apiHelper, $cleanDb)
    {
        $this->pluginId = $pluginId;
        $this->pluginVersion = $pluginVersion;
        $this->pluginUrl = $pluginUrl;
        $this->statusHelper = $statusHelper;
        $this->apiHelper = $apiHelper;
        $this->globalSettingsUrl = admin_url('admin.php?page=wc-settings&tab=mollie_settings#' . $pluginId);
        $this->cleanDb = $cleanDb;
    }
    public function cleanDb()
    {
        return $this->cleanDb;
    }
    public function getGlobalSettingsUrl()
    {
        return $this->globalSettingsUrl;
    }
    public function generalFormFields($defaultTitle, $defaultDescription, $paymentConfirmation): array
    {
        $generalSettings = new MollieGeneralSettings();
        return $generalSettings->gatewayFormFields($defaultTitle, $defaultDescription, $paymentConfirmation, $this->isOrderApiSetting());
    }
    public function processSettings(string $gatewayId)
    {
        $nonce = filter_input(\INPUT_POST, '_wpnonce', \FILTER_SANITIZE_SPECIAL_CHARS);
        $isNonceValid = wp_verify_nonce($nonce, 'woocommerce-settings');
        if (!$isNonceValid) {
            return;
        }
        if (isset($_POST['save'])) {
            $this->processAdminOptionCustomLogo($gatewayId);
            $this->processAdminOptionSurcharge($gatewayId);
            //only credit cards have a selector
            if ($gatewayId === 'mollie_wc_gateway_creditcard') {
                $this->processAdminOptionCreditcardSelector();
            }
        }
    }
    public function processAdminOptionCustomLogo(string $gatewayId)
    {
        $nonce = filter_input(\INPUT_POST, '_wpnonce', \FILTER_SANITIZE_SPECIAL_CHARS);
        $isNonceValid = $nonce && wp_verify_nonce($nonce, 'woocommerce-settings');
        if (!$isNonceValid) {
            return;
        }
        $gatewaySettings = get_option(sprintf('%s_settings', $gatewayId), []);
        $removeLogoOptionName = 'woocommerce_' . $gatewayId . '_remove_logo';
        if (!empty($_POST[$removeLogoOptionName])) {
            unset($gatewaySettings['iconFileUrl'], $gatewaySettings['iconFilePath']);
            update_option(sprintf('%s_settings', $gatewayId), $gatewaySettings);
            return;
        }
        $fileOptionName = 'woocommerce_' . $gatewayId . '_upload_logo';
        if (!empty($_FILES[$fileOptionName]['size']) && !empty($_FILES[$fileOptionName]['name']) && is_string($_FILES[$fileOptionName]['name']) && !empty($_FILES[$fileOptionName]['tmp_name']) && is_string($_FILES[$fileOptionName]['tmp_name'])) {
            $name = filter_var(wp_unslash($_FILES[$fileOptionName]['name']), \FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $tempName = filter_var(wp_unslash($_FILES[$fileOptionName]['tmp_name']), \FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $size = filter_var(wp_unslash($_FILES[$fileOptionName]['size']), \FILTER_SANITIZE_NUMBER_INT);
            if (!$this->validateUploadedFile($name, $tempName, (int) $size)) {
                return;
            }
            $this->processUploadedFile($name, $tempName, $gatewayId);
        }
    }
    public function processAdminOptionSurcharge(string $gatewayId)
    {
        $nonce = filter_input(\INPUT_POST, '_wpnonce', \FILTER_SANITIZE_SPECIAL_CHARS);
        $isNonceValid = wp_verify_nonce($nonce, 'woocommerce-settings');
        if (!$isNonceValid) {
            return;
        }
        $paymentSurcharge = $gatewayId . '_payment_surcharge';
        if (isset($_POST[$paymentSurcharge]) && $_POST[$paymentSurcharge] !== Surcharge::NO_FEE) {
            $surchargeFields = ['_fixed_fee', '_percentage', '_surcharge_limit'];
            foreach ($surchargeFields as $field) {
                $optionName = $gatewayId . $field;
                $validatedValue = isset($_POST[$optionName]) && $_POST[$optionName] > 0 && $_POST[$optionName] < 999;
                if (!$validatedValue) {
                    unset($_POST[$optionName]);
                }
            }
        }
    }
    /**
     * @return bool
     */
    public function isTestModeEnabled()
    {
        $testModeEnabled = get_option($this->getSettingId('test_mode_enabled'));
        return is_string($testModeEnabled) ? trim($testModeEnabled) === 'yes' : \false;
    }
    /**
     * Check if the advanced setting to switch API has value 'Order"
     * @return bool
     */
    public function isOrderApiSetting()
    {
        $orderApiSetting = get_option($this->getSettingId('api_switch'), PaymentProcessor::PAYMENT_METHOD_TYPE_PAYMENT);
        return !$orderApiSetting || is_string($orderApiSetting) && trim($orderApiSetting) === PaymentProcessor::PAYMENT_METHOD_TYPE_ORDER;
    }
    /**
     * @param bool|null $overrideTestMode Pass null to use the current test mode setting.
     *
     * @return false|string
     */
    public function getApiKey(?bool $overrideTestMode = null)
    {
        $isTestModeEnabled = $overrideTestMode === null ? $this->isTestModeEnabled() : $overrideTestMode;
        $settingId = $isTestModeEnabled ? 'test_api_key' : 'live_api_key';
        $apiKeyId = $this->getSettingId($settingId);
        $apiKey = get_option($apiKeyId);
        //TODO add api key filter
        //phpcs:ignore WordPress.Security.NonceVerification
        if (!$apiKey && is_admin() && isset($_POST[$apiKeyId])) {
            $apiKey = filter_input(\INPUT_POST, $apiKeyId, \FILTER_SANITIZE_SPECIAL_CHARS);
        }
        return is_string($apiKey) ? trim($apiKey) : \false;
    }
    /**
     * Order status for cancelled payments
     *
     * @return string|null
     */
    public function getOrderStatusCancelledPayments()
    {
        $defaultCanceledPaymentSetting = 'pending';
        $orderStatusCanceledPaymentsSetting = get_option($this->getSettingId('order_status_cancelled_payments')) ?: $defaultCanceledPaymentSetting;
        return trim($orderStatusCanceledPaymentsSetting);
    }
    /**
     * Deletes the selector transient when the Admin option changes
     *
     */
    protected function processAdminOptionCreditcardSelector()
    {
        delete_transient('svg_creditcards_string');
    }
    /**
     * Retrieve the Payment Locale Setting from Database
     *
     * @return string
     */
    protected function getPaymentLocaleSetting()
    {
        $option = (string) get_option($this->getSettingId(SharedDataDictionary::SETTING_NAME_PAYMENT_LOCALE), SharedDataDictionary::SETTING_LOCALE_WP_LANGUAGE);
        $option = $option ?: SharedDataDictionary::SETTING_LOCALE_WP_LANGUAGE;
        return trim($option);
    }
    /**
     * Retrieve the Payment Locale
     *
     * @return string
     */
    public function getPaymentLocale()
    {
        $setting = $this->getPaymentLocaleSetting();
        if ($setting === SharedDataDictionary::SETTING_LOCALE_DETECT_BY_BROWSER) {
            return $this->browserLanguage();
        }
        $languageCode = $setting === SharedDataDictionary::SETTING_LOCALE_WP_LANGUAGE ? $this->getCurrentLocale() : $setting;
        // TODO Missing Post condition, $languageCode has to be check for a valid
        //      language code.
        return $languageCode ?: SharedDataDictionary::SETTING_LOCALE_DEFAULT_LANGUAGE;
    }
    /**
     * Store customer details at Mollie
     */
    public function shouldStoreCustomer(): bool
    {
        return get_option($this->getSettingId('customer_details'), 'yes') === 'yes';
    }
    /**
     * @return bool
     */
    public function isDebugEnabled()
    {
        return get_option($this->getSettingId('debug'), 'yes') === 'yes';
    }
    /**
     * @return string
     */
    public function getLogsUrl(): string
    {
        return admin_url('admin.php?page=wc-status&tab=logs');
    }
    /**
     * Update the profileId option on update keys or on changing live/test mode
     *
     * @param $optionValue
     * @param $optionName
     *
     * @return mixed
     */
    public function updateMerchantIdOnApiKeyChanges($optionValue, $optionName)
    {
        $optionId = isset($optionName['id']) ? $optionName['id'] : '';
        $allowedOptionsId = [$this->getSettingId('live_api_key'), $this->getSettingId('test_api_key')];
        if (!in_array($optionId, $allowedOptionsId, \true)) {
            return $optionValue;
        }
        $merchantProfileIdOptionKey = $this->pluginId . '_profile_merchant_id';
        try {
            $merchantProfile = $this->mollieWooCommerceMerchantProfile();
            $merchantProfileId = property_exists($merchantProfile, 'id') && $merchantProfile->id !== null ? $merchantProfile->id : '';
        } catch (ApiException $apiException) {
            $merchantProfileId = '';
        }
        update_option($merchantProfileIdOptionKey, $merchantProfileId);
        return $optionValue;
    }
    /**
     * Called after the api keys are updated so we can update the profile Id
     *
     * @param $oldValue
     * @param $value
     * @param $optionName
     */
    public function updateMerchantIdAfterApiKeyChanges($oldValue, $value, $optionName)
    {
        $option = ['id' => $optionName];
        $this->updateMerchantIdOnApiKeyChanges($value, $option);
    }
    public function getConnectionStatus(): bool
    {
        return $this->getConnectionStatusWithError()['connected'];
    }
    /**
     * Attempt connection and return error details on failure.
     *
     * 'error_kind' says which stage failed, because 'error_code' alone cannot: the API key
     * checks and the compatibility checks never produce an HTTP status, so they would be
     * indistinguishable from a request that never reached Mollie.
     *
     * @return array{connected: bool, error_kind?: string, error_code?: int, error_message?: string}
     */
    public function getConnectionStatusWithError(): array
    {
        $status = $this->statusHelper;
        if (!$status->isCompatible()) {
            return ['connected' => \false, 'error_kind' => self::ERROR_KIND_INCOMPATIBLE, 'error_code' => 0, 'error_message' => implode('<br/>', $status->getErrors())];
        }
        try {
            $apiKey = $this->getApiKey();
            $apiClient = $this->apiHelper->getApiClient($apiKey);
        } catch (ApiException $e) {
            // No key saved, or a key that fails the format check: never a connectivity problem.
            return ['connected' => \false, 'error_kind' => self::ERROR_KIND_API_KEY, 'error_code' => (int) $e->getCode(), 'error_message' => Status::plainApiErrorMessage($e)];
        }
        try {
            $status->getMollieApiStatus($apiClient);
            return ['connected' => \true];
        } catch (ApiException $e) {
            return ['connected' => \false, 'error_kind' => self::ERROR_KIND_API, 'error_code' => (int) $e->getCode(), 'error_message' => Status::plainApiErrorMessage($e)];
        }
    }
    /**
     * Get plugin status
     *
     * - Check compatibility
     * - Check Mollie API connectivity
     *
     * @return string
     */
    public function getPluginStatus()
    {
        $status = $this->statusHelper;
        if (!$status->isCompatible()) {
            // Just stop here!
            return '' . '<div class="notice notice-error">' . '<p><strong>' . __('Error', 'mollie-payments-for-woocommerce') . ':</strong> ' . implode('<br/>', $status->getErrors()) . '</p></div>';
        }
        try {
            $apiKey = $this->getApiKey();
            $apiClient = $this->apiHelper->getApiClient($apiKey);
        } catch (ApiException $e) {
            // Plugin-authored message; it carries an intentional link to the Mollie dashboard.
            return '' . '<div id="message" class="error fade notice">' . '<p style="font-weight:bold;"><span style="color:red;">' . esc_html__('Error', 'mollie-payments-for-woocommerce') . ':</span> ' . Status::plainApiErrorMessage($e) . '</p>' . '</div>';
        }
        try {
            $status->getMollieApiStatus($apiClient);
            $api_status = '' . '<p>' . __('Mollie status:', 'mollie-payments-for-woocommerce') . ' <span style="color:green; font-weight:bold;">' . __('Connected', 'mollie-payments-for-woocommerce') . '</span>' . '</p>';
            $api_status_type = 'updated';
        } catch (ApiException $e) {
            $api_status = '' . '<p style="font-weight:bold;"><span style="color:red;">Communicating with Mollie failed:</span> ' . esc_html(Status::plainApiErrorMessage($e)) . '</p>' . '<p>Please view the FAQ item <a href="https://github.com/mollie/WooCommerce/wiki/Common-issues#communicating-with-mollie-failed" target="_blank">Communicating with Mollie failed</a> if this does not fix your problem.';
            $api_status_type = 'error';
        }
        return '' . '<div id="message" class="' . $api_status_type . ' fade notice">' . $api_status . '</div>';
    }
    public function getPaymentConfirmationCheckTime()
    {
        $time = strtotime(SharedDataDictionary::DEFAULT_TIME_PAYMENT_CONFIRMATION_CHECK);
        $date = new DateTime();
        if ($date->getTimestamp() > $time) {
            $date->setTimestamp($time);
            $date->add(new DateInterval('P1D'));
        } else {
            $date->setTimestamp($time);
        }
        return $date->getTimestamp();
    }
    public function getPluginId()
    {
        return $this->pluginId;
    }
    /**
     * @param string $setting
     *
     * @return string
     */
    public function getSettingId($setting)
    {
        $setting_id = $this->pluginId . '_' . trim($setting);
        $setting_id_length = strlen($setting_id);
        $max_option_name_length = 191;
        if ($setting_id_length > $max_option_name_length) {
            trigger_error(sprintf('Setting id %s (%s) to long for database column wp_options.option_name which is varchar(%s).', esc_html($setting_id), esc_html((string) $setting_id_length), esc_html((string) $max_option_name_length)), \E_USER_WARNING);
        }
        return $setting_id;
    }
    /**
     * Get current locale by WordPress
     *
     * Default to self::SETTING_LOCALE_DEFAULT_LANGUAGE
     *
     * @return string
     */
    protected function getCurrentLocale()
    {
        $locale = apply_filters(SharedDataDictionary::FILTER_WPML_CURRENT_LOCALE, get_locale());
        // Convert known exceptions
        $locale = $locale === 'nl_NL_formal' ? 'nl_NL' : $locale;
        $locale = $locale === 'de_DE_formal' ? 'de_DE' : $locale;
        $locale = $locale === 'no_NO' ? 'nb_NO' : $locale;
        return $this->extractValidLanguageCode([$locale]);
    }
    /**
     * Retrieve the browser language
     *
     * @return string
     */
    protected function browserLanguage()
    {
        if (empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            return SharedDataDictionary::SETTING_LOCALE_DEFAULT_LANGUAGE;
        }
        $httpAcceptedLanguages = explode(',', filter_var(wp_unslash($_SERVER['HTTP_ACCEPT_LANGUAGE']), \FILTER_SANITIZE_FULL_SPECIAL_CHARS));
        foreach ($httpAcceptedLanguages as $index => $languageCode) {
            $languageCode = explode(';', $languageCode)[0];
            if (strpos($languageCode, '-') !== \false) {
                $languageCode = str_replace('-', '_', $languageCode);
            }
            $httpAcceptedLanguages[$index] = $languageCode;
        }
        $httpAcceptedLanguages = array_filter($httpAcceptedLanguages);
        if ($httpAcceptedLanguages === []) {
            return SharedDataDictionary::SETTING_LOCALE_DEFAULT_LANGUAGE;
        }
        return $this->extractValidLanguageCode($httpAcceptedLanguages);
    }
    /**
     * Extract a valid code Language from the given arguments
     *
     * The language Code could contains valid language codes that are not supported such as
     * country codes.
     *
     * Since the Browser can send both country and region codes we need to map the country code
     * to a region code on the fly.
     *
     * The method does that, it try to retrieve the language code if it's exists within the
     * allowed language codes dictionary, if not it will try to retrieve the first one that
     * contains the country code.
     *
     * @return string
     */
    protected function extractValidLanguageCode(array $languageCodes)
    {
        // TODO Need Assertion to ensure $languageCodes is not empty and contains only strings
        /**
         * Filter Allowed Language Codes
         *
         * @param array $allowedLanguageCodes
         */
        $allowedLanguageCodes = apply_filters(SharedDataDictionary::FILTER_ALLOWED_LANGUAGE_CODE_SETTING, SharedDataDictionary::ALLOWED_LANGUAGE_CODES);
        if (empty($allowedLanguageCodes)) {
            // TODO Need validation for Language Code
            return (string) $languageCodes[0];
        }
        foreach ($languageCodes as $languageCode) {
            if (in_array($languageCode, $allowedLanguageCodes, \true)) {
                return $languageCode;
            }
        }
        foreach ($languageCodes as $languageCode) {
            foreach ($allowedLanguageCodes as $currentAllowedLanguageCode) {
                $countryCode = substr($currentAllowedLanguageCode, 0, 2);
                if ($countryCode === $languageCode) {
                    return $currentAllowedLanguageCode;
                }
            }
        }
        return SharedDataDictionary::SETTING_LOCALE_DEFAULT_LANGUAGE;
    }
    /**
     * Init all the gateways and add to the db for the first time
     * @param $gateway
     */
    protected function updateGatewaySettings($gateway)
    {
        $gateway->settings['enabled'] = $gateway->is_available() ? 'yes' : 'no';
        update_option($gateway->id . "_settings", $gateway->settings);
    }
    /**
     * If we are calling this the api key has been updated, we need a new api object
     * to retrieve a new profile id
     *
     * @return \Mollie\Api\Resources\CurrentProfile
     * @throws ApiException
     */
    protected function mollieWooCommerceMerchantProfile()
    {
        $apiKey = $this->getApiKey();
        return $this->apiHelper->getApiClient($apiKey, \true)->profiles->getCurrent();
    }
    /**
     * Retrieve the merchant profile ID
     *
     * @return int|string
     * @throws ApiException
     */
    public function mollieWooCommerceMerchantProfileId()
    {
        static $merchantProfileId = null;
        $merchantProfileIdOptionKey = $this->pluginId . '_profile_merchant_id';
        if ($merchantProfileId === null) {
            $merchantProfileId = get_option($merchantProfileIdOptionKey, '');
            /*
             * Try to retrieve the merchant profile ID from an Api Request if not stored already,
             * then store it into the database
             */
            if (!$merchantProfileId) {
                try {
                    $merchantProfile = $this->mollieWooCommerceMerchantProfile();
                    $merchantProfileId = $merchantProfile->id;
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
    protected function validateUploadedFile(string $fileName, string $fileTempName, int $fileSize): bool
    {
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'svg', 'JPG', 'JPEG', 'PNG', 'GIF', 'SVG'];
        $fileExtension = pathinfo($fileName, \PATHINFO_EXTENSION);
        $extensionNotAllowed = !in_array($fileExtension, $allowedExtensions);
        if (!class_exists('finfo')) {
            return \false;
        }
        $finfo = finfo_open(\FILEINFO_MIME_TYPE);
        if (!$finfo) {
            return \false;
        }
        $fileMimeType = finfo_file($finfo, $fileTempName);
        finfo_close($finfo);
        $isSvg = strtolower($fileExtension) === 'svg';
        $fileIsNotAnImage = !$isSvg && (!$fileMimeType || strpos($fileMimeType, 'image') === \false);
        $invalidFileSize = $fileSize > 500000 || $fileSize === 0;
        $notice = new AdminNotice();
        if ($extensionNotAllowed || $fileIsNotAnImage) {
            $message = sprintf(
                /* translators: Placeholder 1: opening tag Placeholder 2: closing tag */
                esc_html__('%1$sMollie Payments for WooCommerce%2$s Unable to upload the file. Only jpg, jpeg, png, gif and svg files are allowed.', 'mollie-payments-for-woocommerce'),
                '<strong>',
                '</strong>'
            );
            $notice->addNotice('notice-error is-dismissible', $message);
            return \false;
        }
        if ($invalidFileSize) {
            $message = sprintf(
                /* translators: Placeholder 1: opening tag Placeholder 2: closing tag */
                esc_html__('%1$sMollie Payments for WooCommerce%2$s Unable to upload the file. Size must be under 500kb.', 'mollie-payments-for-woocommerce'),
                '<strong>',
                '</strong>'
            );
            $notice->addNotice('notice-error is-dismissible', $message);
            return \false;
        }
        return \true;
    }
    protected function processUploadedFile(string $name, string $tempName, string $gatewayId)
    {
        $fileName = preg_replace('#\s+#', '_', $name);
        if (!function_exists('wp_handle_upload')) {
            require_once \ABSPATH . 'wp-admin/includes/file.php';
        }
        $upload_overrides = ['test_form' => \false];
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing -- nonce already verified in processAdminOptionCustomLogo() before this method is called
        $file = isset($_FILES['woocommerce_' . $gatewayId . '_upload_logo']) ? wp_unslash($_FILES['woocommerce_' . $gatewayId . '_upload_logo']) : [];
        if (!empty($file)) {
            $file = ['name' => $file['name'], 'type' => $file['type'], 'tmp_name' => $file['tmp_name'], 'error' => $file['error'], 'size' => $file['size']];
            $isSvg = strtolower(pathinfo($name, \PATHINFO_EXTENSION)) === 'svg';
            // WordPress excludes SVG from allowed MIME types by default; temporarily allow it
            // so wp_handle_upload does not reject the file before we can sanitize it.
            $svgMimeFilter = static function (array $mimes): array {
                $mimes['svg'] = 'image/svg+xml';
                return $mimes;
            };
            // finfo may return text/plain or text/xml for SVGs; override the filetype check
            // so wp_handle_upload accepts the file regardless of what finfo detects.
            $svgTypeFilter = static function (array $data, $file, string $filename): array {
                if (strtolower(pathinfo($filename, \PATHINFO_EXTENSION)) === 'svg') {
                    $data['ext'] = 'svg';
                    $data['type'] = 'image/svg+xml';
                }
                return $data;
            };
            if ($isSvg) {
                add_filter('upload_mimes', $svgMimeFilter);
                add_filter('wp_check_filetype_and_ext', $svgTypeFilter, 10, 3);
            }
            $movefile = wp_handle_upload($file, $upload_overrides);
            if ($isSvg) {
                remove_filter('upload_mimes', $svgMimeFilter);
                remove_filter('wp_check_filetype_and_ext', $svgTypeFilter);
            }
            if ($movefile && !isset($movefile['error'])) {
                if ($isSvg) {
                    $svgContent = file_get_contents($movefile['file']);
                    $sanitizer = new \Mollie\enshrined\svgSanitize\Sanitizer();
                    $sanitized = $svgContent !== \false ? $sanitizer->sanitize($svgContent) : \false;
                    if (!$sanitized) {
                        $this->addFileUploadErrorNotice();
                        wp_delete_file($movefile['file']);
                        return;
                    }
                    file_put_contents($movefile['file'], $sanitized);
                }
                $gatewaySettings = get_option(sprintf('%s_settings', $gatewayId), []);
                $gatewaySettings["iconFileUrl"] = $movefile['url'];
                $gatewaySettings["iconFilePath"] = $movefile['file'];
                update_option(sprintf('%s_settings', $gatewayId), $gatewaySettings);
            } else {
                $this->addFileUploadErrorNotice();
            }
        }
    }
    private function addFileUploadErrorNotice(): void
    {
        $notice = new AdminNotice();
        $notice->addNotice('notice-error is-dismissible', sprintf(
            /* translators: Placeholder 1: opening tag Placeholder 2: closing tag */
            esc_html__('%1$sMollie Payments for WooCommerce%2$s Unable to save the file.', 'mollie-payments-for-woocommerce'),
            '<strong>',
            '</strong>'
        ));
    }
}

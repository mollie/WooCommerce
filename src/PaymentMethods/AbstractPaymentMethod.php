<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\PaymentMethods;

use Mollie\WooCommerce\Gateway\MolliePaymentGateway;
use Mollie\WooCommerce\Gateway\Surcharge;
use Mollie\WooCommerce\Payment\PaymentFieldsService;
use Mollie\WooCommerce\Settings\Settings;
use Mollie\WooCommerce\Shared\SharedDataDictionary;

abstract class AbstractPaymentMethod implements PaymentMethodI
{
    /**
     * @var string
     */
    protected $id;
    /**
     * @var string[]
     */
    protected $config = [];
    /**
     * @var array
     */
    protected $settings = [];
    /**
     * @var IconFactory
     */
    protected $iconFactory;
    /**
     * @var Settings
     */
    protected $settingsHelper;
    /**
     * @var PaymentFieldsService
     */
    protected $paymentFieldsService;
    /**
     * @var Surcharge
     */
    protected $surcharge;
    /**
     * @var array
     */
    private $apiPaymentMethod;

    public function __construct(
        IconFactory $iconFactory,
        Settings $settingsHelper,
        PaymentFieldsService $paymentFieldsService,
        Surcharge $surcharge,
        array $apiPaymentMethod
    ) {

        $this->id = $this->getIdFromConfig();
        $this->iconFactory = $iconFactory;
        $this->settingsHelper = $settingsHelper;
        $this->paymentFieldsService = $paymentFieldsService;
        $this->surcharge = $surcharge;
        $this->config = $this->getConfig();
        $this->settings = $this->getSettings();
        $this->apiPaymentMethod = $apiPaymentMethod;
    }

    public function title(): string
    {
        $useApiTitle = apply_filters('mollie_wc_gateway_use_api_title', $this->isUseApiTitleChecked(), $this->id);
        $title = $this->getProperty('title');
        //new installations should use the api title
        if ($useApiTitle || $title === false) {
            return $this->getApiTitle();
        }
         return $title;
    }

    /**
     * Payment method id accessor
     * @return string
     */
    public function getIdFromConfig(): string
    {
        return $this->getConfig()['id'];
    }

    public function getUploadedImage()
    {
        $settings = $this->getSettings();

        return $settings["iconFileUrl"] ?? null;
    }

    public function isCreditCardSelectorEnabled()
    {
        $settings = $this->getSettings();
        return isset($settings[PaymentMethodsIconUrl::MOLLIE_CREDITCARD_ICONS_ENABLER]) ? $settings[PaymentMethodsIconUrl::MOLLIE_CREDITCARD_ICONS_ENABLER] === "yes" :  null;
    }

    /**
     * Access the payment method surcharge applied
     * @return Surcharge
     */
    public function surcharge()
    {
        return $this->surcharge;
    }

    /**
     * Check if the payment method has surcharge applied
     * @return bool
     */
    public function hasSurcharge(): bool
    {
        return $this->getProperty('payment_surcharge')
            && $this->getProperty('payment_surcharge') !== Surcharge::NO_FEE;
    }

    /**
     * Check if payment method should show payment fields, like issuers or components
     * @return bool
     */
    public function hasPaymentFields(): bool
    {
        return $this->getProperty('paymentFields');
    }

    /**
     * Payment method custom icon url
     * @return string
     */
    public function getIconUrl(): string
    {
        if ($uploadedImageUrl = $this->getUploadedImage()) {
            return $this->iconFactory->getExternalIconHtml($uploadedImageUrl);
        }

        $useAPIImage = apply_filters('mollie_wc_gateway_use_api_icon', $this->isUseApiTitleChecked(), $this->id);

        if (isset($this->apiPaymentMethod["image"]) && property_exists($this->apiPaymentMethod["image"], "svg") && !$this->isCreditCardSelectorEnabled() && $useAPIImage) {
            return $this->iconFactory->getExternalIconHtml($this->apiPaymentMethod["image"]->svg);
        }
        return $this->iconFactory->getIconUrl(
            $this->getIdFromConfig()
        );
    }

    /**
     * Check if payment method should show any icon
     * @return bool
     */
    public function shouldDisplayIcon(): bool
    {
        $defaultIconSetting = true;
        return $this->hasProperty('display_logo') ? $this->getProperty('display_logo') === 'yes' : $defaultIconSetting;
    }

    /**
     * Settings that apply to all payment methods
     * @return array
     */
    public function getSharedFormFields()
    {
        $defaultTitle = $this->getApiTitle();
        return $this->settingsHelper->generalFormFields(
            $defaultTitle,
            $this->config['defaultDescription'],
            $this->config['confirmationDelayed']
        );
    }

    /**
     * Settings specific to every payment method
     * @return mixed
     */
    public function getAllFormFields()
    {
        return $this->getFormFields($this->getSharedFormFields());
    }

    /**
     * Sets the gateway's payment fields strategy based on payment method
     * @param $gateway
     * @return void
     */
    public function paymentFieldsStrategy($gateway)
    {
        $this->paymentFieldsService->setStrategy($this);
        $this->paymentFieldsService->executeStrategy($gateway);
    }

    /**
     * @return PaymentFieldsService
     */
    public function paymentFieldsService(): PaymentFieldsService
    {
        return $this->paymentFieldsService;
    }

    /**
     * Access the payment method processed description, surcharge included
     * @return mixed|string
     */
    public function getProcessedDescription()
    {
        $description = $this->getProperty('description') === false ? $this->getProperty(
            'defaultDescription'
        ) : $this->getProperty('description');
        return $this->surcharge->buildDescriptionWithSurcharge($description, $this);
    }

    /**
     * Access the payment method description for the checkout blocks
     * @return string
     */
    public function getProcessedDescriptionForBlock(): string
    {
        return $this->surcharge->buildDescriptionWithSurchargeForBlock($this);
    }

    /**
     * Retrieve the user's payment method settings or the default values
     * if there are no settings saved for this payment method it will save the defaults
     * @return array
     */
    public function getSettings(): array
    {
        $optionName = 'mollie_wc_gateway_' . $this->id . '_settings';
        $settings = get_option($optionName, false);
        if (!$settings) {
            $settings = $this->defaultSettings();
            update_option($optionName, $settings, true);
        }
        return $settings;
    }

    /**
     * Order status for cancelled payments setting
     *
     * @return string|null
     */
    public function getOrderStatusCancelledPayments()
    {
        return $this->settingsHelper->getOrderStatusCancelledPayments();
    }

    /**
     * Order status after transaction
     * @return string
     */
    public function getInitialOrderStatus(): string
    {
        if ($this->getProperty('confirmationDelayed')) {
            return $this->getProperty('initial_order_status')
                ?: SharedDataDictionary::STATUS_ON_HOLD;
        }

        return SharedDataDictionary::STATUS_PENDING;
    }

    /**
     * Retrieve the payment method's property from config or settings
     * @param string $propertyName
     * @return false|mixed
     */
    public function getProperty(string $propertyName)
    {
        $properties = $this->getMergedProperties();
        return $properties[$propertyName] ?? false;
    }

    /**
     * Check if a certain property exists for this payment method
     * @param string $propertyName
     * @return bool
     */
    public function hasProperty(string $propertyName): bool
    {
        $properties = $this->getMergedProperties();
        return isset($properties[$propertyName]);
    }

    /**
     * Merge settings with config properties
     * @return array
     */
    public function getMergedProperties(): array
    {
        return array_merge($this->config, $this->getSettings());
    }

    /**
     * Default values for the initial settings saved
     *
     * @return array
     */
    public function defaultSettings(): array
    {
        $fields = $this->getAllFormFields();
        //remove setting title fields
        $fields = array_filter($fields, static function ($key) {
                return !is_numeric($key);
        }, ARRAY_FILTER_USE_KEY);
        //we don't save the default description or title, in case the language changes
        unset($fields['description']);
        unset($fields['title']);
        return array_combine(array_keys($fields), array_column($fields, 'default')) ?: [];
    }

    /**
     * Update the payment method's settings
     * @param string $optionName
     * @param string $newValue
     * @return void
     */
    public function updateMethodOption(string $optionName, string $newValue)
    {
        $settingName = 'mollie_wc_gateway_' . $this->id . '_settings';
        $settings = get_option($settingName, false);
        $settings[$optionName] = $newValue;
        update_option($settingName, $settings, true);
    }

    private function getApiTitle()
    {
        $apiTitle = $this->apiPaymentMethod['description'] ?? null;
        return $apiTitle ?: $this->config['defaultTitle'];
    }

    private function isUseApiTitleChecked(): bool
    {
        return $this->getProperty(SharedDataDictionary::USE_API_TITLE_AND_IMAGE) === 'yes';
    }

    protected function titleIsDefault(): bool
    {
        $savedTitle = $this->getProperty('title');
        if (!$savedTitle) {
            return false;
        }

        return $savedTitle === $this->config['defaultTitle'];
    }
}

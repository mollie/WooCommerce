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
        $titleIsDefault = $this->titleIsDefault();
        $useApiTitle = $this->getProperty('use_api_title');
        $title = $this->getProperty('title');
        //new installations or installations that saved the default one should use the api title
        if ($useApiTitle !== 'no' || !$title || $titleIsDefault) {
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

    private function getApiTitle()
    {
        $apiTitle = $this->apiPaymentMethod['description'];
        return $apiTitle ?: $this->config['defaultTitle'];
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

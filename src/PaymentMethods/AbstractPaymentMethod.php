<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\PaymentMethods;


use Mollie\WooCommerce\Gateway\MolliePaymentGateway;
use Mollie\WooCommerce\Gateway\Surcharge;
use Mollie\WooCommerce\Payment\PaymentFieldsService;
use Mollie\WooCommerce\Settings\Settings;

abstract class AbstractPaymentMethod implements PaymentMethodI
{
    /**
     * @var string[]
     */
    public $config = [];
    /**
     * @var array[]
     */
    public $settings = [];
    protected $iconFactory;
    protected $settingsHelper;
    /**
     * @var PaymentFieldsService
     */
    public $paymentFieldsService;
    protected $surcharge;

    public function __construct(
        IconFactory $iconFactory,
        Settings $settingsHelper,
        PaymentFieldsService $paymentFieldsService,
        Surcharge $surcharge
    ) {
        $this->config = $this->getConfig();
        $this->settings = $this->getSettings();
        $this->iconFactory = $iconFactory;
        $this->settingsHelper = $settingsHelper;
        $this->paymentFieldsService = $paymentFieldsService;
        $this->surcharge = $surcharge;
    }

    public function surcharge(){
        return $this->surcharge;
    }

    public function hasSurcharge(){
        return $this->getProperty('payment_surcharge')
            && $this->getProperty('payment_surcharge') !== Surcharge::NO_FEE;
    }

    public function getIconUrl(): string
    {
        return $this->iconFactory->getIconUrl(
            $this->getProperty('id')
        );
    }

    public function shouldDisplayIcon(): bool
    {
        $defaultIconSetting = true;
        return $this->hasProperty('display_logo')? $this->getProperty('display_logo') === 'yes': $defaultIconSetting;
    }

    public function getSharedFormFields(){
        return $this->settingsHelper->generalFormFields(
            $this->getProperty('defaultTitle'),
            $this->getProperty('defaultDescription'),
            $this->getProperty('confirmationDelayed')
        );
    }

    public function getAllFormFields(){
        return $this->getFormFields($this->getSharedFormFields());
    }

    public function paymentFieldsStrategy($gateway){
        $this->paymentFieldsService->setStrategy($this);
        $this->paymentFieldsService->executeStrategy($gateway);
    }

    public function getProcessedDescription(){
        return $this->surcharge->buildDescriptionWithSurcharge($this);
    }

    public function getProcessedDescriptionForBlock(){
        return $this->surcharge->buildDescriptionWithSurchargeForBlock($this);
    }

    public function getSettings()
    {
        $paymentMethodId = $this->getProperty('id');
        $optionName = 'mollie_wc_gateway_' . $paymentMethodId . '_settings';
        return get_option($optionName, false);
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
     * @return string
     */
    public function getInitialOrderStatus(): string
    {
        if ($this->getProperty('confirmationDelayed')) {
            return $this->getProperty('initial_order_status')
                ?: MolliePaymentGateway::STATUS_ON_HOLD;
        }

        return MolliePaymentGateway::STATUS_PENDING;
    }

    public function getAllSettings(): array
    {
        return $this->settings;
    }

    public function getProperty(string $propertyName)
    {
        $properties = $this->getMergedProperties();
        return $properties[$propertyName] ?? false;
    }

    public function hasProperty(string $propertyName): bool
    {
        $properties = $this->getMergedProperties();
        return isset($properties[$propertyName]);
    }

    public function getMergedProperties(): array
    {
        return $this->settings !== null && is_array($this->settings) ? array_merge($this->config, $this->settings) : $this->config;
    }
}

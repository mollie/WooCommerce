<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\PaymentMethods;


use Mollie\WooCommerce\Gateway\MolliePaymentGateway;
use Mollie\WooCommerce\Gateway\SurchargeService;
use Mollie\WooCommerce\Payment\PaymentFieldsService;
use Mollie\WooCommerce\Settings\Settings;

abstract class AbstractPaymentMethod implements PaymentMethodI
{
    /**
     * @var string[]
     */
    protected $config = [];
    /**
     * @var array[]
     */
    protected $settings = [];
    protected $iconFactory;
    protected $settingsHelper;
    /**
     * @var PaymentFieldsService
     */
    public $paymentFieldsService;
    protected $surchargeService;

    public function __construct(
        PaymentMethodSettingsHandlerI $paymentMethodSettingsHandler,
        IconFactory $iconFactory,
        Settings $settingsHelper,
        PaymentFieldsService $paymentFieldsService,
        SurchargeService $surchargeService
    ) {
        $this->config = $this->getConfig();
        $this->settings = $paymentMethodSettingsHandler->getSettings($this);
        $this->iconFactory = $iconFactory;
        $this->settingsHelper = $settingsHelper;
        $this->paymentFieldsService = $paymentFieldsService;
        $this->surchargeService = $surchargeService;
    }

    public function getIconUrl(): string
    {
        return $this->iconFactory->getIconUrl(
            $this->getProperty('id')
        );
    }

    public function shouldDisplayIcon(): bool
    {
        return $this->hasProperty('display_logo')
            && $this->getProperty('display_logo') == 'yes';
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
        $this->surchargeService->buildDescriptionWithSurcharge($this);
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
            return $this->getProperty('initial_order_status');
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

    private function getMergedProperties(): array
    {
        return $this->settings !== null && is_array($this->settings) ? array_merge($this->config, $this->settings) : $this->config;
    }
}

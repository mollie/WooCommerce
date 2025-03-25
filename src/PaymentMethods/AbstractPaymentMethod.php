<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\PaymentMethods;

use Inpsyde\PaymentGateway\Icon;
use Inpsyde\PaymentGateway\IconProviderInterface;
use Inpsyde\PaymentGateway\Method\CustomSettingsFields;
use Inpsyde\PaymentGateway\Method\CustomSettingsFieldsDefinition;
use Inpsyde\PaymentGateway\Method\DefaultPaymentMethodDefinitionTrait;
use Inpsyde\PaymentGateway\Method\PaymentMethodDefinition;
use Inpsyde\PaymentGateway\PaymentFieldsRendererInterface;
use Inpsyde\PaymentGateway\PaymentProcessorInterface;
use Inpsyde\PaymentGateway\PaymentRequestValidatorInterface;
use Inpsyde\PaymentGateway\RefundProcessorInterface;
use Inpsyde\PaymentGateway\StaticIconProvider;
use Mollie\WooCommerce\Gateway\Surcharge;
use Mollie\WooCommerce\Payment\PaymentProcessor;
use Mollie\WooCommerce\Settings\General\MultiCountrySettingsField;
use Mollie\WooCommerce\Shared\SharedDataDictionary;
use Psr\Container\ContainerInterface;
use Mollie\WooCommerce\PaymentMethods\Icon\GatewayIconsRenderer;
use Mollie\WooCommerce\PaymentMethods\PaymentFieldsStrategies\NoopPaymentFieldsRenderer;
use Mollie\WooCommerce\PaymentMethods\PaymentFieldsStrategies\DefaultFieldsStrategy;

abstract class AbstractPaymentMethod implements PaymentMethodI, PaymentMethodDefinition
{
    use DefaultPaymentMethodDefinitionTrait;

    /**
     * @var string[]
     */
    protected $config = [];
    /**
     * @var array
     */
    protected $settings = [];

    protected $surcharge;

    /**
     * @var bool
     */
    protected bool $translationsInitialized = false;

    public function __construct()
    {
        $this->config = $this->getConfig();
        $this->settings = $this->getSettings();
        $this->surcharge = new Surcharge();
    }

    public function title(ContainerInterface $container): string
    {
        $useApiTitle = apply_filters('mollie_wc_gateway_use_api_title', $this->isUseApiTitleChecked(), $this->getIdFromConfig());
        $title = $this->getProperty('title');
        //new installations should use the api title
        if ($useApiTitle || $title === false) {
            return $this->getApiTitle($container);
        }
         return $title;
    }

    /**
     * Payment method id accessor
     * @return string
     */
    public function getIdFromConfig(): string
    {
        $config = $this->getConfig();
        return $config['id'];
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
     * Check if payment method should show any icon
     * @return bool
     */
    public function shouldDisplayIcon(): bool
    {
        $defaultIconSetting = true;
        return $this->hasProperty('display_logo') ? $this->getProperty('display_logo') === 'yes' : $defaultIconSetting;
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
        $optionName = 'mollie_wc_gateway_' . $this->getIdFromConfig() . '_settings';
        $settings = get_option($optionName, false);
        if (!$settings) {
            $settings = [];
        }
        return $settings;
    }

    /**
     * Update the payment method's settings with defaults if not exist
     * @return array
     */
    public function updateSettingsWithDefaults(ContainerInterface $container): array
    {
        $optionName = 'mollie_wc_gateway_' . $this->getIdFromConfig() . '_settings';
        $settings = get_option($optionName, false);
        if (!$settings) {
            $settings = $this->defaultSettings($container);
            update_option($optionName, $settings, true);
        }
        return $settings;
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
    public function defaultSettings(ContainerInterface $container): array
    {
        $defaultTitle = $this->getApiTitle($container);
        $settingsHelper = $container->get('settings.settings_helper');
        $generalFormFields = $settingsHelper->generalFormFields(
            $defaultTitle,
            $this->config['defaultDescription'],
            $this->config['confirmationDelayed']
        );
        $fields = $this->getFormFields($generalFormFields);
        //remove setting title fields
        $fields = array_filter($fields, static function ($field) {
                return isset($field['type']) && $field['type'] !== 'title';
        });
        //we don't save the default description or title, in case the language changes
        unset($fields['description']);
        unset($fields['title']);
        return array_combine(array_keys($fields), array_column($fields, 'default')) ?: [];
    }

    private function getApiTitle(ContainerInterface $container): string
    {
        $apiMethod = $container->get('gateway.getPaymentMethodsAfterFeatureFlag')[$this->getIdFromConfig()];
        $apiTitle = $apiMethod['description'] ?? null;
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

    public function id(): string
    {
        return 'mollie_wc_gateway_' . $this->getIdFromConfig();
    }

    public function paymentProcessor(ContainerInterface $container): PaymentProcessorInterface
    {
        return $container->get(PaymentProcessor::class);
    }

    public function paymentRequestValidator(ContainerInterface $container): PaymentRequestValidatorInterface
    {
        return $container->get('payment_gateways.noop_payment_request_validator');
    }

    public function methodTitle(ContainerInterface $container): string
    {
        return 'Mollie - ' . $this->title($container);
    }

    public function description(ContainerInterface $container): string
    {
        $description = $this->getProcessedDescription();
        return empty($description) ? '' : $description;
    }

    public function methodDescription(ContainerInterface $container): string
    {
        return $this->getProperty('settingsDescription');
    }

    /**
     * @inheritDoc
     */
    public function availabilityCallback(ContainerInterface $container): callable
    {
        $gatewayInstances = $container->get('__deprecated.gateway_helpers');
        $gatewayId = $this->id();
        return static function ($gateway) use ($gatewayInstances, $gatewayId) {
            return $gatewayInstances[$gatewayId]->is_available($gateway);
        };
    }

    public function supports(ContainerInterface $container): array
    {
        $supports = $this->getProperty('supports');
        $isSepa = $this->getProperty('SEPA') === true;
        $isSubscription = $this->getProperty('Subscription') === true;
        $subscriptionHooks = $container->get('gateway.subscriptionsSupports');
        if ($isSepa || $isSubscription) {
            $supports = array_merge($supports, $subscriptionHooks);
        }
        return $supports;
    }

    public function refundProcessor(ContainerInterface $container): RefundProcessorInterface
    {
        $supports = $this->getProperty('supports');
        $supportsRefunds = $supports && in_array('refunds', $supports, true);
        if ($supportsRefunds) {
            return $container->get('payment_gateway.getRefundProcessor')($this->getIdFromConfig());
        }
        return $container->get('payment_gateways.noop_refund_processor');
    }

    public function paymentMethodIconProvider(ContainerInterface $container): IconProviderInterface
    {
        $iconFactory = $container->get(IconFactory::class);
        $url = $iconFactory->getIconUrl($this->getIdFromConfig());
        if ($uploadedImageUrl = $this->getUploadedImage()) {
            $url = $iconFactory->getExternalIconHtml($uploadedImageUrl);
        }

        $useAPIImage = apply_filters('mollie_wc_gateway_use_api_icon', $this->isUseApiTitleChecked(), $this->getIdFromConfig());

        if (isset($this->apiPaymentMethod["image"]) && property_exists($this->apiPaymentMethod["image"], "svg") && !$this->isCreditCardSelectorEnabled() && $useAPIImage) {
            $url = $iconFactory->getExternalIconHtml($this->apiPaymentMethod["image"]->svg);
        }

        $alt = $this->getIdFromConfig() . ' icon';
        $icon = new Icon(
            $this->getIdFromConfig(),
            $url,
            $alt
        );
        return new StaticIconProvider($icon);
    }

    public function gatewayIconsRenderer(ContainerInterface $container): \Inpsyde\PaymentGateway\GatewayIconsRendererInterface
    {
        return new GatewayIconsRenderer($this, $this->paymentMethodIconProvider($container));
    }

    public function paymentFieldsRenderer(ContainerInterface $container): PaymentFieldsRendererInterface
    {
        $oldGatewayInstances = $container->get('__deprecated.gateway_helpers');
        //not all payment methods have a gateway
        if (!isset($oldGatewayInstances[$this->id()])) {
            return new NoopPaymentFieldsRenderer();
        }
        $gatewayDescription = $container->get('payment_gateway.' . $this->id() . '.description');
        $dataHelper = $container->get('settings.data_helper');
        $deprecatedGatewayHelper = $oldGatewayInstances[$this->id()];
        if (!$this->getProperty('paymentFields')) {
            return new DefaultFieldsStrategy($deprecatedGatewayHelper, $gatewayDescription, $dataHelper);
        } else {
            $className = 'Mollie\\WooCommerce\\PaymentMethods\\PaymentFieldsStrategies\\' . ucfirst($this->getIdFromConfig()) . 'FieldsStrategy';
            return class_exists($className) ? new $className($deprecatedGatewayHelper, $gatewayDescription, $dataHelper) : new DefaultFieldsStrategy($deprecatedGatewayHelper, $gatewayDescription, $dataHelper);
        }
    }

    public function hasFields(ContainerInterface $container): bool
    {
        $hasFields = $this->hasPaymentFields();
        if ($hasFields) {
            return true;
        }

        /* Override show issuers dropdown? */
        $dropdownEnabled = $this->getProperty('issuers_dropdown_shown') === 'yes';
        if ($dropdownEnabled) {
            return true;
        }
        return false;
    }

    public function formFields(ContainerInterface $container): array
    {
        $defaultTitle = $this->getApiTitle($container);
        $settingsHelper = $container->get('settings.settings_helper');
        $generalFormFields = $settingsHelper->generalFormFields(
            $defaultTitle,
            $this->config['defaultDescription'],
            $this->config['confirmationDelayed']
        );

        return $this->getFormFields($generalFormFields);
    }

    public function optionKey(ContainerInterface $container): string
    {
        return $this->id() . '_settings';
    }

    public function registerBlocks(ContainerInterface $container): bool
    {
        //we handle it outside for the moment
        return false;
    }

    public function orderButtonText(ContainerInterface $container): string
    {
        return '';
    }

    public function customSettings(): CustomSettingsFieldsDefinition
    {
        return new CustomSettingsFields([
            'multi_select_countries' => function () {
                return new MultiCountrySettingsField($this);
            },
        ], []);
    }

    public function icon(ContainerInterface $container): string
    {
        return '';
    }
}

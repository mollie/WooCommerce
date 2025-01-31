<?php

namespace Mollie\WooCommerce\Assets;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use Mollie\WooCommerce\PaymentMethods\PaymentFieldsStrategies\DefaultFieldsStrategy;
use Mollie\WooCommerce\PaymentMethods\PaymentMethodI;
use Mollie\WooCommerce\Shared\Data;
use Psr\Container\ContainerInterface;

final class MollieCheckoutBlocksSupport extends AbstractPaymentMethodType
{
    /** @var string $name */
    protected $name = "mollie";
    /** @var string $scriptHandle */
    private static $scriptHandle = "mollie_block_index";
    /** @var Data */
    protected $dataService;
    /** @var array */
    protected $gatewayInstances;
    /** @var string $registerScriptUrl */
    protected $registerScriptUrl;
    /** @var string $registerScriptVersion */
    protected $registerScriptVersion;
    private ContainerInterface $container;

    public function __construct(
        Data $dataService,
        array $gatewayInstances,
        string $registerScriptUrl,
        string $registerScriptVersion,
        ContainerInterface $container
    ) {

        $this->dataService = $dataService;
        $this->gatewayInstances = $gatewayInstances;
        $this->registerScriptUrl = $registerScriptUrl;
        $this->registerScriptVersion = $registerScriptVersion;
        $this->container = $container;
    }

    public function initialize()
    {
        //
    }

    public static function getScriptHandle()
    {

        return self::$scriptHandle;
    }

    public function get_payment_method_script_handles(): array
    {
        wp_register_script(
            self::$scriptHandle,
            $this->registerScriptUrl,
            ['wc-blocks-registry', 'underscore', 'jquery'],
            $this->registerScriptVersion,
            true
        );

        self::localizeWCBlocksData($this->dataService, $this->gatewayInstances, $this->container);

        return [self::$scriptHandle];
    }

    public static function localizeWCBlocksData($dataService, $gatewayInstances, $container)
    {
        wp_enqueue_style('mollie-applepaydirect');
        wp_localize_script(
            self::$scriptHandle,
            'mollieBlockData',
            [
                'gatewayData' => self::gatewayDataForWCBlocks($dataService, $gatewayInstances, $container),
                'mollieApplePayBlockDataCart' => $dataService->mollieApplePayBlockDataCart(),
            ]
        );
    }

    public static function gatewayDataForWCBlocks(Data $dataService, array $deprecatedGatewayHelpers, ContainerInterface $container): array
    {
        $filters = $dataService->wooCommerceFiltersForCheckout();
        $availableGateways = WC()->payment_gateways()->get_available_payment_gateways();
        $availablePaymentMethods = [];

        /**
         * @var $gateway
         * psalm-suppress  UnusedForeachValue
         */
        foreach ($availableGateways as $key => $gateway) {
            if (strpos($key, 'mollie_wc_gateway_') === false) {
                unset($availableGateways[$key]);
            }
        }
        if (
            isset($filters['amount']['currency'])
            && isset($filters['locale'])
            && isset($filters['billingCountry'])
        ) {
            $filterKey = "{$filters['amount']['currency']}-{$filters['billingCountry']}";
            foreach ($availableGateways as $key => $gateway) {
                $gatewayId = $gateway->id;
                $methodId = substr($gatewayId, strrpos($gatewayId, '_') + 1);
                $availablePaymentMethods[$filterKey][$key] = $methodId;
            }
        }

        $dataToScript = [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'filters' => [
                'currency' => isset($filters['amount']['currency']) ? $filters['amount']['currency'] : false,
                'cartTotal' => isset($filters['amount']['value']) ? $filters['amount']['value'] : false,
                'paymentLocale' => isset($filters['locale']) ? $filters['locale'] : false,
                'billingCountry' => isset($filters['billingCountry']) ? $filters['billingCountry'] : false,
            ],
        ];
        $gatewayData = [];
        $isSepaEnabled = isset($deprecatedGatewayHelpers['mollie_wc_gateway_directdebit']) && $deprecatedGatewayHelpers['mollie_wc_gateway_directdebit']->enabled === 'yes';
        /** @var PaymentGateway $gateway */
        foreach ($availableGateways as $gatewayKey => $gateway) {
            $deprecatedGateway = $deprecatedGatewayHelpers[$gatewayKey];
            $method = $deprecatedGateway->paymentMethod();
            $gatewayId = is_string($method->getProperty('id')) ? $method->getProperty('id') : "";

            if ($gateway->enabled !== 'yes' || ($gatewayId === 'directdebit' && !is_admin())) {
                continue;
            }
            $content = $method->getProcessedDescriptionForBlock();
            $issuers = false;
            if ($method->getProperty('paymentFields') === true) {
                $className = 'Mollie\\WooCommerce\\PaymentMethods\\PaymentFieldsStrategies\\' . ucfirst($method->getProperty('id')) . 'FieldsStrategy';
                $paymentFieldsStrategy = class_exists($className) ? new $className(
                    $deprecatedGateway,
                    $gateway->get_description(),
                    $dataService
                ) : new DefaultFieldsStrategy($deprecatedGateway, $gateway->get_description(), $dataService);
                $issuers = $paymentFieldsStrategy->getFieldMarkup($deprecatedGateway, $dataService);
            }
            if ($gatewayId === 'creditcard') {
                $content .= $issuers;
                $issuers = false;
            }
            $title = $method->title($container);
            $labelMarkup = "<span style='margin-right: 1em'>{$title}</span>{$gateway->get_icon()}";
            $hasSurcharge = $method->hasSurcharge();
            $countryCodes = [
                'BE' => '+32xxxxxxxxx',
                'NL' => '+316xxxxxxxx',
                'DE' => '+49xxxxxxxxx',
                'AT' => '+43xxxxxxxxx',
            ];
            $country = WC()->customer ? WC()->customer->get_billing_country() : '';
            $hideCompanyFieldFilter = apply_filters('mollie_wc_hide_company_field', false);
            $phonePlaceholder = in_array($country, array_keys($countryCodes)) ? $countryCodes[$country] : $countryCodes['NL'];
            $gatewayData[] = [
                'name' => $gatewayKey,
                'label' => $labelMarkup,
                'content' => $content,
                'issuers' => $issuers,
                'hasSurcharge' => $hasSurcharge,
                'title' => $title,
                'contentFallback' => __(
                    'Please choose a billing country to see the available payment methods',
                    'mollie-payments-for-woocommerce'
                ),
                'edit' => $content,
                'paymentMethodId' => $gatewayKey,
                'allowedCountries' => is_array(
                    $method->getProperty('allowed_countries')
                ) ? $method->getProperty('allowed_countries') : [],
                'ariaLabel' => $method->getProperty('defaultDescription'),
                'supports' => self::gatewaySupportsFeatures($method, $isSepaEnabled),
                'errorMessage' => $method->getProperty('errorMessage'),
                'companyPlaceholder' => $method->getProperty('companyPlaceholder'),
                'phoneLabel' => $method->getProperty('phoneLabel'),
                'phonePlaceholder' => $phonePlaceholder,
                'birthdatePlaceholder' => $method->getProperty('birthdatePlaceholder'),
                'isExpressEnabled' => $gatewayId === 'applepay' && $method->getProperty('mollie_apple_pay_button_enabled_express_checkout') === 'yes',
                'hideCompanyField' => $hideCompanyFieldFilter,
            ];
        }
        $dataToScript['gatewayData'] = $gatewayData;
        $dataToScript['availableGateways'] = $availablePaymentMethods;

        return $dataToScript;
    }

    public static function gatewaySupportsFeatures(PaymentMethodI $paymentMethod, bool $isSepaEnabled): array
    {
        $supports = (array)$paymentMethod->getProperty('supports');
        $isSepaPaymentMethod = (bool)$paymentMethod->getProperty('SEPA');
        if ($isSepaEnabled && $isSepaPaymentMethod) {
            $supports[] = 'subscriptions';
        }

        return $supports;
    }
}

<?php

namespace Mollie\WooCommerce\Assets;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use Mollie\Inpsyde\PaymentGateway\Icon;
use Mollie\WooCommerce\Components\ComponentDataService;
use Mollie\WooCommerce\PaymentMethods\PaymentFieldsStrategies\DefaultFieldsStrategy;
use Mollie\WooCommerce\Shared\Data;
use Mollie\Psr\Container\ContainerInterface;
final class MollieCheckoutBlocksSupport
{
    /** @var string $scriptHandle */
    private static $scriptHandle = "mollie_block_index";
    /** @var Data */
    protected $dataService;
    /** @var array */
    protected $gatewayInstances;
    public function __construct(Data $dataService, array $gatewayInstances)
    {
        $this->dataService = $dataService;
        $this->gatewayInstances = $gatewayInstances;
    }
    public static function getScriptHandle(): string
    {
        return self::$scriptHandle;
    }
    public static function localizeWCBlocksData($dataService, $gatewayInstances, $container)
    {
        wp_enqueue_style('mollie-applepaydirect');
        wp_localize_script(self::$scriptHandle, 'mollieBlockData', ['gatewayData' => self::gatewayDataForWCBlocks($dataService, $gatewayInstances, $container), 'mollieApplePayBlockDataCart' => $dataService->mollieApplePayBlockDataCart()]);
    }
    public static function gatewayDataForWCBlocks(Data $dataService, array $deprecatedGatewayHelpers, ContainerInterface $container): array
    {
        $paymentGateways = WC()->payment_gateways()->payment_gateways();
        $gatewayData = [];
        /** @var ComponentDataService */
        $componentDataService = $container->get('components.data_service');
        $componentData = $componentDataService->getComponentData();
        $isOrderPayPage = is_checkout_pay_page();
        $isMultiStepsCheckout = get_option('woocommerce_gzdp_checkout_enable') === 'yes';
        /** @var PaymentGateway $gateway */
        foreach ($paymentGateways as $gatewayKey => $gateway) {
            if (substr($gateway->id, 0, 18) !== 'mollie_wc_gateway_') {
                continue;
            }
            $deprecatedGateway = $deprecatedGatewayHelpers[$gatewayKey];
            $method = $deprecatedGateway->paymentMethod();
            $gatewayId = is_string($method->getProperty('id')) ? $method->getProperty('id') : "";
            if ($gateway->enabled !== 'yes' || $gatewayId === 'directdebit' && !is_admin()) {
                continue;
            }
            $content = $method->getProcessedDescriptionForBlock();
            $issuers = \false;
            if ($method->getProperty('paymentFields') === \true) {
                $className = 'Mollie\WooCommerce\PaymentMethods\PaymentFieldsStrategies\\' . ucfirst($method->getProperty('id')) . 'FieldsStrategy';
                $paymentFieldsStrategy = class_exists($className) ? new $className($deprecatedGateway, $gateway->get_description(), $dataService) : new DefaultFieldsStrategy($deprecatedGateway, $gateway->get_description(), $dataService);
                $issuers = $paymentFieldsStrategy->getFieldMarkup($deprecatedGateway, $dataService);
            }
            $title = $method->title($container);
            $iconProvider = $method->paymentMethodIconProvider($container);
            $icons = $iconProvider->provideIcons();
            $iconsArray = array_map(function (Icon $icon) {
                return ['id' => $icon->id(), 'src' => $icon->src(), 'alt' => $icon->alt()];
            }, $icons);
            $labelContent = ['title' => $title, 'iconsArray' => $iconsArray];
            $componentsDescription = '';
            if (!$method->shouldDisplayIcon()) {
                $labelContent['iconsArray'] = [];
            }
            if ($gatewayId === 'creditcard') {
                $issuers = \false;
                $lockIcon = file_get_contents($dataService->pluginPath() . '/' . 'public/images/lock-icon.svg');
                $mollieLogo = file_get_contents($dataService->pluginPath() . '/' . 'public/images/mollie-logo.svg');
                $descriptionTranslated = __('Secure payments provided by', 'mollie-payments-for-woocommerce');
                $componentsDescription = "{$lockIcon} {$descriptionTranslated} {$mollieLogo}";
            }
            $hasSurcharge = $method->hasSurcharge();
            $countryCodes = ['BE' => '+32xxxxxxxxx', 'NL' => '+316xxxxxxxx', 'DE' => '+49xxxxxxxxx', 'AT' => '+43xxxxxxxxx', 'ES' => '+34xxxxxxxxx', 'NO' => '+47xxxxxxxxx', 'DK' => '+45xxxxxxxxx', 'FI' => '+358xxxxxxxx'];
            $country = WC()->customer ? WC()->customer->get_billing_country() : '';
            $hideCompanyFieldFilter = apply_filters('mollie_wc_hide_company_field', \false);
            $phonePlaceholder = in_array($country, array_keys($countryCodes)) ? $countryCodes[$country] : $countryCodes['NL'];
            $shouldLoadComponents = $componentDataService->isComponentsEnabled($method);
            $gatewayData[] = ['name' => $gatewayKey, 'label' => $labelContent, 'content' => $content, 'issuers' => $issuers, 'hasSurcharge' => $hasSurcharge, 'title' => $title, 'contentFallback' => __('Please choose a billing country to see the available payment methods', 'mollie-payments-for-woocommerce'), 'edit' => $content, 'paymentMethodId' => $gatewayKey, 'allowedCountries' => is_array($method->getProperty('allowed_countries')) ? $method->getProperty('allowed_countries') : [], 'ariaLabel' => $method->getProperty('defaultDescription'), 'supports' => $gateway->supports, 'errorMessage' => $method->getProperty('errorMessage'), 'companyPlaceholder' => $method->getProperty('companyPlaceholder'), 'phoneLabel' => $method->getProperty('phoneLabel'), 'phonePlaceholder' => $phonePlaceholder, 'birthdatePlaceholder' => $method->getProperty('birthdatePlaceholder'), 'isExpressEnabled' => $gatewayId === 'applepay' && $method->getProperty('mollie_apple_pay_button_enabled_express_checkout') === 'yes', 'hideCompanyField' => $hideCompanyFieldFilter, 'shouldLoadComponents' => $shouldLoadComponents, 'isMultiStepsCheckout' => $isMultiStepsCheckout, 'componentsDescription' => $componentsDescription];
        }
        $dataToScript['gatewayData'] = $gatewayData;
        $base_location = wc_get_base_location();
        $shopCountryCode = $base_location['country'];
        $totalLabel = get_bloginfo('name');
        $appleButtonData = ['shop' => ['countryCode' => $shopCountryCode, 'totalLabel' => $totalLabel], 'nonce' => wp_create_nonce('mollie_apple_pay_blocks'), 'ajaxUrl' => admin_url('admin-ajax.php')];
        $dataToScript['appleButtonData'] = $appleButtonData;
        $dataToScript['isOrderPayPage'] = $isOrderPayPage;
        if ($componentData !== null) {
            $dataToScript['componentData'] = $componentData;
            $dataToScript['componentData']['isMultistepsCheckout'] = $isMultiStepsCheckout;
        }
        return $dataToScript;
    }
}

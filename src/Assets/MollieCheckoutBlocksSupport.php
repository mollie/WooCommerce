<?php

namespace Mollie\WooCommerce\Assets;

use Inpsyde\PaymentGateway\Icon;
use Mollie\WooCommerce\Components\ComponentDataService;
use Mollie\WooCommerce\PaymentMethods\PaymentFieldsStrategies\DefaultFieldsStrategy;
use Mollie\WooCommerce\Shared\Data;
use Psr\Container\ContainerInterface;

final class MollieCheckoutBlocksSupport
{
    private const SCRIPT_HANDLE = 'mollie_block_index';
    private const GATEWAY_PREFIX = 'mollie_wc_gateway_';
    private const GATEWAY_PREFIX_LENGTH = 18;

    private const PHONE_PLACEHOLDERS = [
        'BE' => '+32xxxxxxxxx',
        'NL' => '+316xxxxxxxx',
        'DE' => '+49xxxxxxxxx',
        'AT' => '+43xxxxxxxxx',
        'ES' => '+34xxxxxxxxx',
        'NO' => '+47xxxxxxxxx',
        'DK' => '+45xxxxxxxxx',
        'FI' => '+358xxxxxxxx'
    ];

    protected Data $dataService;
    protected array $gatewayInstances;
    private array $appleData;
    private array $paypalData;

    public function __construct(
        Data $dataService,
        array $gatewayInstances,
        array $appleData = [],
        array $paypalData = []
    ) {
        $this->dataService = $dataService;
        $this->gatewayInstances = $gatewayInstances;
        $this->appleData = $appleData;
        $this->paypalData = $paypalData;
    }

    public function getScriptHandle(): string
    {
        return self::SCRIPT_HANDLE;
    }

    public function localizeWCBlocksData(ContainerInterface $container): void
    {
        wp_enqueue_style('mollie-applepaydirect');
        wp_localize_script(
            self::SCRIPT_HANDLE,
            'mollieBlockData',
            [
                'gatewayData' => $this->gatewayDataForWCBlocks($container),
                'mollieApplePayBlockDataCart' => $this->appleData,
                'molliePayPalBlockDataCart' => $this->paypalData,
            ]
        );
    }

    public function gatewayDataForWCBlocks(ContainerInterface $container): array
    {
        $componentDataService = $container->get('components.data_service');
        $componentData = $componentDataService->getComponentData();
        $isMultiStepsCheckout = get_option('woocommerce_gzdp_checkout_enable') === 'yes';
        $billingCountry = WC()->customer ? WC()->customer->get_billing_country() : '';

        $gatewayData = $this->buildGatewaysData(
            $container,
            $componentDataService,
            $isMultiStepsCheckout,
            $billingCountry
        );

        return [
            'gatewayData' => $gatewayData,
            'appleButtonData' => $this->buildAppleButtonData(),
            'isOrderPayPage' => is_checkout_pay_page(),
            'componentData' => $componentData ? array_merge(
                $componentData,
                ['isMultistepsCheckout' => $isMultiStepsCheckout]
            ) : null,
        ];
    }

    private function buildGatewaysData(
        ContainerInterface $container,
        ComponentDataService $componentDataService,
        bool $isMultiStepsCheckout,
        string $billingCountry
    ): array {
        $paymentGateways = WC()->payment_gateways()->payment_gateways();
        $gatewayData = [];

        foreach ($paymentGateways as $gatewayKey => $gateway) {
            if (!$this->shouldProcessGateway($gateway, $gatewayKey)) {
                continue;
            }

            $deprecatedGateway = $this->gatewayInstances[$gatewayKey];
            $method = $deprecatedGateway->paymentMethod();
            $gatewayId = $this->getGatewayId($method);

            if (!$this->isGatewayEnabled($gateway, $gatewayId)) {
                continue;
            }

            $gatewayData[] = $this->buildSingleGatewayData(
                $container,
                $gateway,
                $gatewayKey,
                $deprecatedGateway,
                $method,
                $gatewayId,
                $componentDataService,
                $isMultiStepsCheckout,
                $billingCountry
            );
        }

        return $gatewayData;
    }

    private function buildSingleGatewayData(
        ContainerInterface $container,
        $gateway,
        string $gatewayKey,
        $deprecatedGateway,
        $method,
        string $gatewayId,
        ComponentDataService $componentDataService,
        bool $isMultiStepsCheckout,
        string $billingCountry
    ): array {
        $title = $method->title($container);
        $content = $method->getProcessedDescriptionForBlock();
        $issuers = $this->getIssuers($method, $deprecatedGateway, $gateway);
        $labelContent = $this->buildLabelContent($method, $title, $container);

        [$issuers, $componentsDescription] = $this->applyCreditCardOverrides(
            $gatewayId,
            $issuers
        );

        $dataToScript = [
            'name' => $gatewayKey,
            'label' => $labelContent,
            'content' => $content,
            'issuers' => $issuers,
            'hasSurcharge' => $method->hasSurcharge(),
            'title' => $title,
            'contentFallback' => __(
                'Please choose a billing country to see the available payment methods',
                'mollie-payments-for-woocommerce'
            ),
            'edit' => $content,
            'paymentMethodId' => $gatewayKey,
            'allowedCountries' => $this->getAllowedCountries($method),
            'ariaLabel' => $method->getProperty('defaultDescription'),
            'supports' => $gateway->supports,
            'errorMessage' => $method->getProperty('errorMessage'),
            'companyPlaceholder' => $method->getProperty('companyPlaceholder'),
            'phoneLabel' => $method->getProperty('phoneLabel'),
            'phonePlaceholder' => $this->getPhonePlaceholder($billingCountry),
            'birthdatePlaceholder' => $method->getProperty('birthdatePlaceholder'),
            'isExpressEnabled' => $method->isExpressCheckoutEnabled(),
            'hideCompanyField' => apply_filters('mollie_wc_hide_company_field', false),
            'shouldLoadComponents' => $componentDataService->isComponentsEnabled($method),
            'isMultiStepsCheckout' => $isMultiStepsCheckout,
            'componentsDescription' => $componentsDescription,
        ];
        $componentData = $componentDataService->getComponentData();
        if ($componentData !== null) {
            $dataToScript['componentData'] = $componentData;
            $dataToScript['componentData']['isMultistepsCheckout'] = $isMultiStepsCheckout;
        }
        return $dataToScript;
    }

    private function shouldProcessGateway($gateway, string $gatewayKey): bool
    {
        return substr($gateway->id, 0, self::GATEWAY_PREFIX_LENGTH) === self::GATEWAY_PREFIX
            && isset($this->gatewayInstances[$gatewayKey]);
    }

    private function getGatewayId($method): string
    {
        $id = $method->getProperty('id');
        return is_string($id) ? $id : '';
    }

    private function isGatewayEnabled($gateway, string $gatewayId): bool
    {
        if ($gateway->enabled !== 'yes') {
            return false;
        }
        return !($gatewayId === 'directdebit' && !is_admin());
    }

    private function getIssuers($method, $deprecatedGateway, $gateway)
    {
        if ($method->getProperty('paymentFields') !== true) {
            return false;
        }

        $strategyClass = sprintf(
            'Mollie\\WooCommerce\\PaymentMethods\\PaymentFieldsStrategies\\%sFieldsStrategy',
            ucfirst($method->getProperty('id'))
        );

        $strategy = class_exists($strategyClass)
            ? new $strategyClass($deprecatedGateway, $gateway->get_description(), $this->dataService)
            : new DefaultFieldsStrategy($deprecatedGateway, $gateway->get_description(), $this->dataService);

        return $strategy->getFieldMarkup($deprecatedGateway, $this->dataService);
    }

    private function buildLabelContent($method, string $title, ContainerInterface $container): array
    {
        $iconProvider = $method->paymentMethodIconProvider($container);
        $icons = $iconProvider->provideIcons();

        $iconsArray = $method->shouldDisplayIcon()
            ? array_map(fn(Icon $icon) => [
                'id' => $icon->id(),
                'src' => $icon->src(),
                'alt' => $icon->alt(),
            ], $icons)
            : [];

        return [
            'title' => $title,
            'iconsArray' => $iconsArray,
        ];
    }

    private function applyCreditCardOverrides(string $gatewayId, $issuers): array
    {
        if ($gatewayId !== 'creditcard') {
            return [$issuers, ''];
        }

        $lockIcon = file_get_contents($this->dataService->pluginPath() . '/public/images/lock-icon.svg');
        $mollieLogo = file_get_contents($this->dataService->pluginPath() . '/public/images/mollie-logo.svg');
        $description = __('Secure payments provided by', 'mollie-payments-for-woocommerce');

        return [false, "{$lockIcon} {$description} {$mollieLogo}"];
    }

    private function getAllowedCountries($method): array
    {
        $countries = $method->getProperty('allowed_countries');
        return is_array($countries) ? $countries : [];
    }

    private function getPhonePlaceholder(string $country): string
    {
        return self::PHONE_PLACEHOLDERS[$country] ?? self::PHONE_PLACEHOLDERS['NL'];
    }

    private function buildAppleButtonData(): array
    {
        $baseLocation = wc_get_base_location();

        return [
            'shop' => [
                'countryCode' => $baseLocation['country'],
                'totalLabel' => get_bloginfo('name'),
            ],
            'nonce' => wp_create_nonce('mollie_apple_pay_blocks'),
            'ajaxUrl' => admin_url('admin-ajax.php'),
        ];
    }
}

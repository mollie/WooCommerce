<?php

# -*- coding: utf-8 -*-

declare(strict_types=1);

namespace Mollie\WooCommerce\Assets;

use Inpsyde\Modularity\Module\ExecutableModule;
use Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use Mollie\Api\Exceptions\ApiException;
use Mollie\WooCommerce\Buttons\ApplePayButton\DataToAppleButtonScripts;
use Mollie\WooCommerce\Buttons\PayPalButton\DataToPayPal;
use Mollie\WooCommerce\Components\AcceptedLocaleValuesDictionary;
use Mollie\WooCommerce\Settings\Settings;
use Mollie\WooCommerce\Shared\Data;
use Psr\Container\ContainerInterface;

class AssetsModule implements ExecutableModule
{
    use ModuleClassNameIdTrait;

    /**
     * @var mixed
     */
    protected $pluginUrl;
    /**
     * @var mixed
     */
    protected $pluginPath;
    protected $settingsHelper;
    protected $pluginVersion;
    protected $dataService;

    public function run(ContainerInterface $container): bool
    {
        $this->pluginUrl = $container->get('shared.plugin_url');
        $this->pluginPath = $container->get('shared.plugin_path');
        $this->settingsHelper = $container->get('settings.settings_helper');
        assert($this->settingsHelper instanceof Settings);
        $this->pluginVersion = $container->get('shared.plugin_version');
        $this->dataService = $container->get('settings.data_helper');
        assert($this->dataService instanceof Data);

        add_action(
            'init',
            function () use ($container) {
                $hasBlocksEnabled = $this->dataService->isBlockPluginActive();
                self::registerFrontendScripts();

                // Enqueue Scripts
                add_action('wp_enqueue_scripts', [$this, 'enqueueFrontendScripts']);
                add_action('wp_enqueue_scripts', [$this, 'enqueueComponentsAssets']);
                add_action('wp_enqueue_scripts', [$this, 'enqueueApplePayDirectScripts']);
                add_action('wp_enqueue_scripts', [$this, 'enqueuePayPalButtonScripts']);

                if($hasBlocksEnabled){
                    $gatewayInstances = $container->get('gateway.instances');
                    self::registerBlockScripts();
                    add_action('wp_enqueue_scripts', function () use ($gatewayInstances) {
                        $this->enqueueBlockCheckoutScripts($gatewayInstances);
                    });
                    $this->registerButtonsBlockScripts();
                }
            }
        );
        add_action(
            'admin_init',
            function () use ($container) {
                if (is_admin()) {
                    $hasBlocksEnabled = $this->dataService->isBlockPluginActive();
                    wp_register_script(
                        'mollie_wc_admin_settings',
                        $this->getPluginUrl('/public/js/settings.min.js'),
                        ['underscore', 'jquery'],
                        $this->pluginVersion
                    );
                    wp_enqueue_script('mollie_wc_admin_settings');
                    wp_register_script(
                        'mollie_wc_gateway_advanced_settings',
                        $this->getPluginUrl(
                            '/public/js/advancedSettings.min.js'
                        ),
                        ['underscore', 'jquery'],
                        $this->pluginVersion
                    );
                    add_action('admin_enqueue_scripts', [$this, 'enqueueAdvancedSettingsJS'], 10, 1 );
                    global $current_section;
                    wp_localize_script(
                        'mollie_wc_admin_settings',
                        'mollieSettingsData',
                        [
                            'current_section' => $current_section,
                        ]
                    );

                    wp_register_script(
                        'mollie_wc_gateway_settings',
                        $this->getPluginUrl(
                            '/public/js/gatewaySettings.min.js'
                        ),
                        ['underscore', 'jquery'],
                        $this->pluginVersion
                    );

                    if($hasBlocksEnabled){
                        $gatewayInstances = $container->get('gateway.instances');
                        $this->enqueueBlockCheckoutScripts($gatewayInstances);
                    }

                    $this->enqueueIconSettings($current_section);
                }
            }
        );
        return true;
    }

    public function enqueueBlockCheckoutScripts($gatewayInstances)
    {
        wp_enqueue_script('mollie_block_index');
        wp_enqueue_style('mollie-gateway-icons');
        wp_localize_script(
            'mollie_block_index',
            'mollieBlockData',
            [
                'gatewayData' => $this->gatewayDataForWCBlocks($gatewayInstances),
            ]
        );
    }

    public function registerButtonsBlockScripts()
    {
        add_action('woocommerce_blocks_enqueue_cart_block_scripts_after', function () {
            $cart = WC()->cart;
            $shouldShow = !$cart->needs_shipping();
            $shouldShow = !$this->cartHasSubscription($cart) && $shouldShow;
            if (mollieWooCommerceIsPayPalButtonEnabled('cart') && $shouldShow) {
                wp_register_script(
                    'mollie_paypalButtonBlock',
                    $this->getPluginUrl(
                        '/public/js/paypalButtonBlockComponent.min.js'
                    ),
                    [],
                    filemtime(
                        $this->getPluginPath(
                            '/public/js/paypalButtonBlockComponent.min.js'
                        )
                    ),
                    true
                );
                $dataToScripts = new DataToPayPal($this->pluginUrl);
                wp_enqueue_style('unabledButton');
                wp_enqueue_script('mollie_paypalButtonBlock');
                wp_localize_script(
                    'mollie_paypalButtonBlock',
                    'molliepaypalButtonCart',
                    $dataToScripts->paypalbuttonScriptData(true)
                );
            }
            if (mollieWooCommerceIsApplePayDirectEnabled('cart') && !$this->cartHasSubscription($cart)) {
                wp_register_script(
                    'mollie_applepayButtonBlock',
                    $this->getPluginUrl(
                        '/public/js/applepayButtonBlockComponent.min.js'
                    ),
                    [],
                    filemtime(
                        $this->getPluginPath(
                            '/public/js/applepayButtonBlockComponent.min.js'
                        )
                    ),
                    true
                );
                $dataToScripts = new DataToAppleButtonScripts();
                wp_enqueue_style('mollie-applepaydirect');
                wp_enqueue_script('mollie_applepayButtonBlock');
                wp_localize_script(
                    'mollie_applepayButtonBlock',
                    'mollieApplePayBlockDataCart',
                    $dataToScripts->applePayScriptData(true)
                );
            }
        });
    }

    /**
     * Enqueues the ApplePay button scripts if enabled and in correct page
     */
    public function enqueueApplePayDirectScripts()
    {
        if (mollieWooCommerceIsApplePayDirectEnabled('product') && is_product()) {
            $dataToScripts = new DataToAppleButtonScripts();
            wp_enqueue_style('mollie-applepaydirect');
            wp_enqueue_script('mollie_applepaydirect');
            wp_localize_script(
                'mollie_applepaydirect',
                'mollieApplePayDirectData',
                $dataToScripts->applePayScriptData()
            );
        }
        if (mollieWooCommerceIsApplePayDirectEnabled('cart') && is_cart()) {
            $dataToScripts = new DataToAppleButtonScripts();
            wp_enqueue_style('mollie-applepaydirect');
            wp_enqueue_script('mollie_applepaydirectCart');
            wp_localize_script(
                'mollie_applepaydirectCart',
                'mollieApplePayDirectDataCart',
                $dataToScripts->applePayScriptData()
            );
        }
    }

    /**
     * Enqueues the ApplePay button scripts if enabled and in correct page
     */
    public function enqueuePayPalButtonScripts()
    {
        if (mollieWooCommerceIsPayPalButtonEnabled('product') && is_product()) {
            $product = wc_get_product(get_the_id());
            if (!$product || $product->is_type('subscription')) {
                return;
            }
            $productNeedShipping = mollieWooCommerceCheckIfNeedShipping($product);
            if (!$productNeedShipping) {
                $dataToScripts = new DataToPayPal($this->pluginUrl);
                wp_enqueue_style('unabledButton');
                wp_enqueue_script('mollie_paypalButton');
                wp_localize_script(
                    'mollie_paypalButton',
                    'molliepaypalbutton',
                    $dataToScripts->paypalbuttonScriptData()
                );
            }
        }
        if (mollieWooCommerceIsPayPalButtonEnabled('cart') && is_cart()) {
            $cart = WC()->cart;
            foreach ($cart->get_cart_contents() as $product) {
                if ($product['data']->is_type('subscription')) {
                    return;
                }
            }
            if (!$cart->needs_shipping()) {
                $dataToScripts = new DataToPayPal($this->pluginUrl);
                wp_enqueue_style('unabledButton');
                wp_enqueue_script('mollie_paypalButtonCart');
                wp_localize_script(
                    'mollie_paypalButtonCart',
                    'molliepaypalButtonCart',
                    $dataToScripts->paypalbuttonScriptData()
                );
            }
        }
    }

    /**
     * Register Scripts
     *
     * @return void
     */
    public function registerFrontendScripts()
    {
        wp_register_script(
            'babel-polyfill',
            $this->getPluginUrl('/public/js/babel-polyfill.min.js'),
            [],
            filemtime($this->getPluginPath('/public/js/babel-polyfill.min.js')),
            true
        );

        wp_register_script(
            'mollie_wc_gateway_applepay',
            $this->getPluginUrl('/public/js/applepay.min.js'),
            [],
            filemtime($this->getPluginPath('/public/js/applepay.min.js')),
            true
        );
        wp_register_style(
            'mollie-gateway-icons',
            $this->getPluginUrl('/public/css/mollie-gateway-icons.min.css'),
            [],
            filemtime($this->getPluginPath('/public/css/mollie-gateway-icons.min.css')),
            'screen'
        );
        wp_register_style(
            'mollie-components',
            $this->getPluginUrl('/public/css/mollie-components.min.css'),
            [],
            filemtime($this->getPluginPath('/public/css/mollie-components.min.css')),
            'screen'
        );
        wp_register_style(
            'mollie-applepaydirect',
            $this->getPluginUrl('/public/css/mollie-applepaydirect.min.css'),
            [],
            filemtime($this->getPluginPath('/public/css/mollie-applepaydirect.min.css')),
            'screen'
        );
        wp_register_script(
            'mollie_applepaydirect',
            $this->getPluginUrl('/public/js/applepayDirect.min.js'),
            ['underscore', 'jquery'],
            filemtime($this->getPluginPath('/public/js/applepayDirect.min.js')),
            true
        );
        wp_register_script(
            'mollie_paypalButton',
            $this->getPluginUrl('/public/js/paypalButton.min.js'),
            ['underscore', 'jquery'],
            filemtime($this->getPluginPath('/public/js/paypalButton.min.js')),
            true
        );
        wp_register_script(
            'mollie_paypalButtonCart',
            $this->getPluginUrl('/public/js/paypalButtonCart.min.js'),
            ['underscore', 'jquery'],
            filemtime($this->getPluginPath('/public/js/paypalButtonCart.min.js')),
            true
        );
        wp_register_script(
            'mollie_applepaydirectCart',
            $this->getPluginUrl('/public/js/applepayDirectCart.min.js'),
            ['underscore', 'jquery'],
            filemtime($this->getPluginPath('/public/js/applepayDirectCart.min.js')),
            true
        );
        wp_register_script('mollie', 'https://js.mollie.com/v1/mollie.js', [], null, true);
        wp_register_script(
            'mollie-components',
            $this->getPluginUrl('/public/js/mollie-components.min.js'),
            ['underscore', 'jquery', 'mollie', 'babel-polyfill'],
            filemtime($this->getPluginPath('/public/js/mollie-components.min.js')),
            true
        );

        wp_register_style(
            'unabledButton',
            $this->getPluginUrl('/public/css/unabledButton.min.css'),
            [],
            filemtime($this->getPluginPath('/public/css/unabledButton.min.css')),
            'screen'
        );
        wp_register_script(
            'gatewaySurcharge',
            $this->getPluginUrl('/public/js/gatewaySurcharge.min.js'),
            ['underscore', 'jquery'],
            filemtime($this->getPluginPath('/public/js/gatewaySurcharge.min.js')),
            true
        );
    }

    public function registerBlockScripts(){
        wp_register_script(
            'mollie_block_index',
            $this->getPluginUrl('/public/js/mollieBlockIndex.min.js'),
            ['wc-blocks-registry', 'underscore', 'jquery'],
            filemtime($this->getPluginPath('/public/js/mollieBlockIndex.min.js')),
            true
        );
    }

    /**
     * Enqueue Frontend only scripts
     *
     * @return void
     */
    public function enqueueFrontendScripts()
    {
        if (is_admin() || !mollieWooCommerceIsCheckoutContext()) {
            return;
        }
        wp_enqueue_style('mollie-gateway-icons');

        $applePayGatewayEnabled = mollieWooCommerceIsGatewayEnabled('mollie_wc_gateway_applepay_settings', 'enabled');

        if (!$applePayGatewayEnabled) {
            return;
        }
        wp_enqueue_style('unabledButton');
        wp_enqueue_script('mollie_wc_gateway_applepay');
    }

    /**
     * Enqueue Mollie Component Assets
     */
    public function enqueueComponentsAssets()
    {
        if (
            is_admin()
            || (!mollieWooCommerceIsCheckoutContext()
                && !has_block("woocommerce/checkout"))
        ) {
            return;
        }

        try {
            $merchantProfileId = $this->settingsHelper->mollieWooCommerceMerchantProfileId();
        } catch (ApiException $exception) {
            return;
        }

        $mollieComponentsStylesGateways = mollieWooCommerceComponentsStylesForAvailableGateways();
        $gatewayNames = array_keys($mollieComponentsStylesGateways);

        if (!$merchantProfileId || !$mollieComponentsStylesGateways) {
            return;
        }

        $locale = get_locale();
        $locale =  str_replace('_formal', '', $locale);
        $allowedLocaleValues = AcceptedLocaleValuesDictionary::ALLOWED_LOCALES_KEYS_MAP;
        if (!in_array($locale, $allowedLocaleValues)) {
            $locale = AcceptedLocaleValuesDictionary::DEFAULT_LOCALE_VALUE;
        }

        wp_enqueue_style('mollie-components');
        wp_enqueue_script('mollie-components');

        wp_localize_script(
            'mollie-components',
            'mollieComponentsSettings',
            [
                'merchantProfileId' => $merchantProfileId,
                'options' => [
                    'locale' => $locale,
                    'testmode' => $this->settingsHelper->isTestModeEnabled(),
                ],
                'enabledGateways' => $gatewayNames,
                'componentsSettings' => $mollieComponentsStylesGateways,
                'componentsAttributes' => [
                    [
                        'name' => 'cardHolder',
                        'label' => esc_html__('Name on card', 'mollie-payments-for-woocommerce'),
                    ],
                    [
                        'name' => 'cardNumber',
                        'label' => esc_html__('Card number', 'mollie-payments-for-woocommerce'),
                    ],
                    [
                        'name' => 'expiryDate',
                        'label' => esc_html__('Expiry date', 'mollie-payments-for-woocommerce'),
                    ],
                    [
                        'name' => 'verificationCode',
                        'label' => esc_html__(
                            'CVC/CVV',
                            'mollie-payments-for-woocommerce'
                        ),
                    ],
                ],
                'messages' => [
                    'defaultErrorMessage' => esc_html__(
                        'An unknown error occurred, please check the card fields.',
                        'mollie-payments-for-woocommerce'
                    ),
                ],
                'isCheckout' => is_checkout(),
                'isCheckoutPayPage' => is_checkout_pay_page(),
            ]
        );
    }

    protected function gatewayDataForWCBlocks(array $gatewayInstances): array
    {
        $filters = $this->dataService->wooCommerceFiltersForCheckout();
        $availableGateways = WC()->payment_gateways()->get_available_payment_gateways();

        foreach ($availableGateways as $key => $gateway){
            if(strpos($key, 'mollie_wc_gateway_') === false){
                unset($availableGateways[$key]);
            }
        }
        if (
            isset($filters['amount']['currency'])
            && isset($filters['locale'])
            && isset($filters['billingCountry'])
        ) {
            $filterKey = "{$filters['amount']['currency']}-{$filters['locale']}-{$filters['billingCountry']}";
            foreach ($availableGateways as $key => $gateway){
                $availablePaymentMethods[$filterKey][$key] = $gateway->paymentMethod->getProperty('id');
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
        $isSepaEnabled = isset($gatewayInstances['mollie_wc_gateway_directdebit']) && $gatewayInstances['mollie_wc_gateway_directdebit']->enabled === 'yes';
        foreach ($gatewayInstances as $gatewayKey => $gateway) {
            $gatewayId = $gateway->paymentMethod->getProperty('id');

            if ($gateway->enabled !== 'yes' || $gatewayId === 'directdebit') {
                continue;
            }
            $content = $gateway->paymentMethod->getProcessedDescriptionForBlock();
            $issuers = false;
            if ($gateway->paymentMethod->getProperty('paymentFields')) {
                $gateway->paymentMethod->paymentFieldsService->setStrategy($gateway->paymentMethod);
                $issuers = $gateway->paymentMethod->paymentFieldsService->getStrategyMarkup($gateway);
            }
            if($gatewayId == 'creditcard'){
                $content .= $issuers;
                $issuers = false;
            }
            $title = $gateway->paymentMethod->getProperty('title') === false?
                $gateway->paymentMethod->getProperty('defaultTitle') : $gateway->paymentMethod->getProperty('title');
            $labelMarkup = "<span style='margin-right: 1em'>{$title}</span>{$gateway->icon}";
            $hasSurcharge = $gateway->paymentMethod->hasSurcharge();
            $gatewayData[] = [
                'name' => $gatewayKey,
                'label' => $labelMarkup,
                'content' => $content,
                'issuers' => $issuers,
                'hasSurcharge' => $hasSurcharge,
                'title' => $title,
                'contentFallback'=> __('Please choose a billing country to see the available payment methods', 'mollie-payments-for-woocommerce'),
                'edit' => $content,
                'paymentMethodId' => $gatewayKey,
                'allowedCountries' => $gateway->paymentMethod->getProperty('allowed_countries'),
                'ariaLabel' => $gateway->paymentMethod->getProperty('defaultDescription'),
                'supports' => $this->gatewaySupportsFeatures($gateway->paymentMethod, $isSepaEnabled),
            ];
        }
        $dataToScript['gatewayData'] = $gatewayData;
        $dataToScript['availableGateways'] = isset($availablePaymentMethods) ?
            $availablePaymentMethods
            : [];

        return $dataToScript;
    }

    public function gatewaySupportsFeatures($paymentMethod, $isSepaEnabled):array
    {
        $supports = $paymentMethod->getProperty('supports');
        $isSepaPaymentMethod = $paymentMethod->getProperty('SEPA');
        if($isSepaEnabled && $isSepaPaymentMethod){
            array_push($supports, 'subscriptions');
        }

        return $supports;
    }

    protected function getPluginUrl(string $path = ''): string
    {
        return $this->pluginUrl . ltrim($path, '/');
    }

    protected function getPluginPath(string $path = ''): string
    {
        return $this->pluginPath . ltrim($path, '/');
    }

    /**
     * @param $current_section
     */
    protected function enqueueIconSettings($current_section): void
    {
        if (!$current_section || strpos($current_section, 'mollie_wc_gateway_') === false) {
            return;
        }
        wp_enqueue_script('mollie_wc_gateway_settings');
        wp_enqueue_style('mollie-gateway-icons');
        $settingsName = "{$current_section}_settings";
        $gatewaySettings = get_option($settingsName, false);
        $message = __('No custom logo selected', 'mollie-payments-for-woocommerce');
        $isEnabled = false;
        if ($gatewaySettings && isset($gatewaySettings['enable_custom_logo'])) {
            $isEnabled = $gatewaySettings['enable_custom_logo'] === 'yes';
        }
        $uploadFieldName = "{$current_section}_upload_logo";
        $enabledFieldName = "{$current_section}_enable_custom_logo";
        $gatewayIconUrl = '';
        if ($gatewaySettings && isset($gatewaySettings['iconFileUrl'])) {
            $gatewayIconUrl = $gatewaySettings['iconFileUrl'];
        }

        wp_localize_script(
            'mollie_wc_gateway_settings',
            'gatewaySettingsData',
            [
                'isEnabledIcon' => $isEnabled,
                'uploadFieldName' => $uploadFieldName,
                'enableFieldName' => $enabledFieldName,
                'iconUrl' => $gatewayIconUrl,
                'message' => $message,
                'pluginUrlImages' => plugins_url('public/images', M4W_FILE),
            ]
        );
    }

    /**
     * Enqueue inline JavaScript for Advanced Settings admin page
     * @param array $ar Can be ignored
     * @return void
     */
    public function enqueueAdvancedSettingsJS($ar)
    {
        // Only insert scripts on specific admin page
        global $current_screen, $current_tab, $current_section;
        if (
            $current_screen->id !== 'woocommerce_page_wc-settings'
            || $current_tab !== 'mollie_settings'
            || $current_section !== 'advanced'
        ) {
            return;
        }
        wp_enqueue_script('mollie_wc_gateway_advanced_settings');
    }

    /**
     * @param \WC_Cart $cart
     * @return bool
     */
    protected function cartHasSubscription(\WC_Cart $cart): bool
    {
        foreach ($cart->cart_contents as $cart_content) {
            if ($cart_content['data'] instanceof \WC_Product_Subscription_Variation) {
                return true;
            }
        }
        return false;
    }
}

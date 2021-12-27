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
        $this->pluginVersion = $container->get('shared.plugin_version');
        $this->dataService = $container->get('shared.data_helper');
        add_action(
            'init',
            function () use ($container){
                self::registerFrontendScripts();
                wp_register_script(
                    'mollie_wc_admin_settings',
                    $this->getPluginUrl('/public/js/settings.min.js'),
                    ['underscore', 'jquery'],
                    $this->pluginVersion
                );
                // Enqueue Scripts
                add_action('wp_enqueue_scripts', [$this, 'enqueueFrontendScripts']);
                add_action('wp_enqueue_scripts', [$this, 'enqueueComponentsAssets']);
                add_action('wp_enqueue_scripts', [$this, 'enqueueApplePayDirectScripts']);
                add_action('wp_enqueue_scripts', [$this, 'enqueuePayPalButtonScripts']);

                $gatewayInstances = $container->get('gateway.instances');
                add_action('wp_enqueue_scripts', function() use ($gatewayInstances){
                    $this->enqueueButtonBlocksCartScripts($gatewayInstances);
                });
                $this->registerButtonsBlockScripts();


                wp_enqueue_script('mollie_wc_admin_settings');
                global $current_section;

                wp_localize_script(
                    'mollie_wc_admin_settings',
                    'mollieSettingsData',
                    [
                        'current_section' => $current_section,
                    ]
                );
                if (is_admin()) {
                    wp_register_script(
                        'mollie_wc_gateway_settings',
                        $this->getPluginUrl(
                            '/public/js/gatewaySettings.min.js'
                        ),
                        ['underscore', 'jquery'],
                        $this->pluginVersion
                    );
                    $this->enqueueButtonBlocksCartScripts($gatewayInstances);
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
            }
        );
        return true;
    }

    public function enqueueButtonBlocksCartScripts($gatewayInstances){

        wp_enqueue_script('mollie_block_index');
        wp_enqueue_style('mollie-gateway-icons');
        wp_localize_script(
            'mollie_block_index',
            'mollieBlockData',
            [
                'gatewayData' => $this->gatewayDataForWCBlocks($gatewayInstances)
            ]
        );
    }

    public function registerButtonsBlockScripts ()
    {
        add_action('woocommerce_blocks_enqueue_cart_block_scripts_after', function (){
            if (mollieWooCommerceIsPayPalButtonEnabled('cart')) {
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
                $cart = WC()->cart;
                if (!$cart->needs_shipping()) {
                    $dataToScripts = new DataToPayPal($this->pluginUrl);
                    wp_enqueue_style('unabledButton');
                    wp_enqueue_script('mollie_paypalButtonBlock');
                    wp_localize_script(
                        'mollie_paypalButtonBlock',
                        'molliepaypalButtonCart',
                        $dataToScripts->paypalbuttonScriptData(true)
                    );
                }
            }
            if (mollieWooCommerceIsApplePayDirectEnabled('cart')) {
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
        if (is_admin()
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
                        'label' => esc_html__('Name on card', 'mollie-payments-for-woocommerce')
                    ],
                    [
                        'name' => 'cardNumber',
                        'label' => esc_html__('Card number', 'mollie-payments-for-woocommerce')
                    ],
                    [
                        'name' => 'expiryDate',
                        'label' => esc_html__('Expiry date', 'mollie-payments-for-woocommerce')
                    ],
                    [
                        'name' => 'verificationCode',
                        'label' => esc_html__(
                            'CVC/CVV',
                            'mollie-payments-for-woocommerce'
                        )
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

    protected function gatewayDataForWCBlocks($gatewayInstances): array
    {
        $filters = $this->dataService->wooCommerceFiltersForCheckout();

        if($filters){
            $availablePaymentMethods = $this->dataService->getAvailablePaymentMethodListForCheckout($filters);
        }

        $dataToScript = [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'filters' => [
                            'currency' => $filters['amount']['currency'],
                            'cartTotal' => $filters['amount']['value'],
                            'paymentLocale' => $filters['locale'],
                            'billingCountry' => $filters['billingCountry']
                        ]
        ];
        $gatewayData = [];
        foreach ($gatewayInstances as $gatewayKey => $gateway){
            $gatewayId = $gateway->paymentMethod->getProperty('id');
            if($gateway->enabled !== 'yes' || $gatewayId == 'directdebit'){
                continue;
            }
            $content = __($gateway->paymentMethod->getProperty('defaultDescription', 'mollie-payments-for-woocommerce'));

            if ($gateway->paymentMethod->getProperty('paymentFields')){

                $gateway->paymentMethod->paymentFieldsService->setStrategy($gateway->paymentMethod);

                $content = $gateway->paymentMethod->paymentFieldsService->getStrategyMarkup($gateway);
            }
            $labelMarkup = "<span style='margin-right: 1em'>{$gateway->paymentMethod->getProperty('id')}</span>{$gateway->icon}";

            $gatewayData[] = [
                'name' => $gatewayKey,
                'label' => $labelMarkup,
                'content' => $content,
                'edit' => $gateway->paymentMethod->getProperty('defaultDescription'),
                'paymentMethodId' => $gatewayKey,
                'allowedCountries'=> $gateway->paymentMethod->getProperty('allowed_countries'),
                'ariaLabel' => $gateway->paymentMethod->getProperty('defaultDescription'),
                'supports' => $gateway->paymentMethod->getProperty('supports')
            ];
        }
        $dataToScript['gatewayData'] = $gatewayData;
        $dataToScript['availableGateways']= isset($availablePaymentMethods)?$availablePaymentMethods:[];

        //var_dump($dataToScript);
        return $dataToScript;
    }

    protected function getPluginUrl(string $path = ''): string
    {
        return $this->pluginUrl . ltrim($path, '/');
    }

    protected function getPluginPath(string $path = ''): string
    {
        return $this->pluginPath . ltrim($path, '/');
    }
}

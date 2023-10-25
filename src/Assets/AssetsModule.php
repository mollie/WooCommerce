<?php

# -*- coding: utf-8 -*-

declare(strict_types=1);

namespace Mollie\WooCommerce\Assets;

use Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry;
use Mollie\WooCommerce\Vendor\Inpsyde\Modularity\Module\ExecutableModule;
use Mollie\WooCommerce\Vendor\Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use Mollie\Api\Exceptions\ApiException;
use Mollie\WooCommerce\Buttons\ApplePayButton\DataToAppleButtonScripts;
use Mollie\WooCommerce\Buttons\PayPalButton\DataToPayPal;
use Mollie\WooCommerce\Components\AcceptedLocaleValuesDictionary;
use Mollie\WooCommerce\Settings\Settings;
use Mollie\WooCommerce\Shared\Data;
use Mollie\WooCommerce\Vendor\Psr\Container\ContainerInterface;

class AssetsModule implements ExecutableModule
{
    use ModuleClassNameIdTrait;

    public function run(ContainerInterface $container): bool
    {
        $this->setupModuleActions($container);
        return true;
    }

    public function enqueueBlockCheckoutScripts(Data $dataService, array $gatewayInstances): void
    {
        if (!has_block('woocommerce/checkout')) {
            return;
        }
        wp_enqueue_script(MollieCheckoutBlocksSupport::getScriptHandle());
        wp_enqueue_style('mollie-gateway-icons');

        MollieCheckoutBlocksSupport::localizeWCBlocksData($dataService, $gatewayInstances);
    }

    public function registerButtonsBlockScripts(string $pluginUrl, string $pluginPath): void
    {
        add_action('woocommerce_blocks_enqueue_cart_block_scripts_after', function () use ($pluginUrl, $pluginPath) {
            $cart = WC()->cart;
            $shouldShow = !$cart->needs_shipping();
            $shouldShow = !$this->cartHasSubscription($cart) && $shouldShow;
            if (mollieWooCommerceIsPayPalButtonEnabled('cart') && $shouldShow) {
                wp_register_script(
                    'mollie_paypalButtonBlock',
                    $this->getPluginUrl(
                        $pluginUrl,
                        '/public/js/paypalButtonBlockComponent.min.js'
                    ),
                    [],
                    (string) filemtime(
                        $this->getPluginPath(
                            $pluginPath,
                            '/public/js/paypalButtonBlockComponent.min.js'
                        )
                    )
                );
                $dataToScripts = new DataToPayPal($pluginUrl);
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
                        $pluginUrl,
                        '/public/js/applepayButtonBlockComponent.min.js'
                    ),
                    [],
                    (string) filemtime(
                        $this->getPluginPath(
                            $pluginPath,
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
    public function enqueueApplePayDirectScripts(): void
    {
        if (mollieWooCommerceIsApplePayDirectEnabled('product') && is_product()) {
            $product = wc_get_product(get_the_id());
            if (!$product) {
                return;
            }

            if ($product->is_type('subscription') && !is_user_logged_in()) {
                return;
            }

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
            $cart = WC()->cart;
            if ($this->cartHasSubscription($cart) && !is_user_logged_in()) {
                return;
            }
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
    public function enqueuePayPalButtonScripts(string $pluginUrl): void
    {
        if (mollieWooCommerceIsPayPalButtonEnabled('product') && is_product()) {
            $product = wc_get_product(get_the_id());
            if (!$product || $product->is_type('subscription')) {
                return;
            }
            $productNeedShipping = mollieWooCommerceCheckIfNeedShipping($product);
            if ($productNeedShipping) {
                return;
            }
            $dataToScripts = new DataToPayPal($pluginUrl);
            wp_enqueue_style('unabledButton');
            wp_enqueue_script('mollie_paypalButton');
            wp_localize_script(
                'mollie_paypalButton',
                'molliepaypalbutton',
                $dataToScripts->paypalbuttonScriptData()
            );
        }
        if (mollieWooCommerceIsPayPalButtonEnabled('cart') && is_cart()) {
            $cart = WC()->cart;
            if ($this->cartHasSubscription($cart)) {
                return;
            }
            $dataToScripts = new DataToPayPal($pluginUrl);
            wp_enqueue_style('unabledButton');
            wp_enqueue_script('mollie_paypalButtonCart');
            wp_localize_script(
                'mollie_paypalButtonCart',
                'molliepaypalButtonCart',
                $dataToScripts->paypalbuttonScriptData()
            );
        }
    }

    /**
     * Register Scripts
     *
     * @return void
     */
    protected function registerFrontendScripts(string $pluginUrl, string $pluginPath)
    {
        wp_register_script(
            'babel-polyfill',
            $this->getPluginUrl($pluginUrl, '/public/js/babel-polyfill.min.js'),
            [],
            (string) filemtime($this->getPluginPath($pluginPath, '/public/js/babel-polyfill.min.js')),
            true
        );

        wp_register_script(
            'mollie_wc_gateway_applepay',
            $this->getPluginUrl($pluginUrl, '/public/js/applepay.min.js'),
            [],
            (string) filemtime($this->getPluginPath($pluginPath, '/public/js/applepay.min.js')),
            true
        );
        wp_register_style(
            'mollie-gateway-icons',
            $this->getPluginUrl($pluginUrl, '/public/css/mollie-gateway-icons.min.css'),
            [],
            (string) filemtime($this->getPluginPath($pluginPath, '/public/css/mollie-gateway-icons.min.css')),
            'screen'
        );
        wp_register_style(
            'mollie-components',
            $this->getPluginUrl($pluginUrl, '/public/css/mollie-components.min.css'),
            [],
            (string) filemtime($this->getPluginPath($pluginPath, '/public/css/mollie-components.min.css')),
            'screen'
        );
        wp_register_style(
            'mollie-applepaydirect',
            $this->getPluginUrl($pluginUrl, '/public/css/mollie-applepaydirect.min.css'),
            [],
            (string) filemtime($this->getPluginPath($pluginPath, '/public/css/mollie-applepaydirect.min.css')),
            'screen'
        );
        wp_register_script(
            'mollie_applepaydirect',
            $this->getPluginUrl($pluginUrl, '/public/js/applepayDirect.min.js'),
            ['underscore', 'jquery'],
            (string) filemtime($this->getPluginPath($pluginPath, '/public/js/applepayDirect.min.js')),
            true
        );
        wp_register_script(
            'mollie_paypalButton',
            $this->getPluginUrl($pluginUrl, '/public/js/paypalButton.min.js'),
            ['underscore', 'jquery'],
            (string) filemtime($this->getPluginPath($pluginPath, '/public/js/paypalButton.min.js')),
            true
        );
        wp_register_script(
            'mollie_paypalButtonCart',
            $this->getPluginUrl($pluginUrl, '/public/js/paypalButtonCart.min.js'),
            ['underscore', 'jquery'],
            (string) filemtime($this->getPluginPath($pluginPath, '/public/js/paypalButtonCart.min.js')),
            true
        );
        wp_register_script(
            'mollie_applepaydirectCart',
            $this->getPluginUrl($pluginUrl, '/public/js/applepayDirectCart.min.js'),
            ['underscore', 'jquery'],
            (string) filemtime($this->getPluginPath($pluginPath, '/public/js/applepayDirectCart.min.js')),
            true
        );
        wp_register_script('mollie', 'https://js.mollie.com/v1/mollie.js', [], date("d"), true);
        wp_register_script(
            'mollie-components',
            $this->getPluginUrl($pluginUrl, '/public/js/mollie-components.min.js'),
            ['underscore', 'jquery', 'mollie', 'babel-polyfill'],
            (string) filemtime($this->getPluginPath($pluginPath, '/public/js/mollie-components.min.js')),
            true
        );
        wp_register_script(
            'mollie-components-blocks',
            $this->getPluginUrl($pluginUrl, '/public/js/mollie-components-blocks.min.js'),
            ['underscore', 'jquery', 'mollie', 'babel-polyfill'],
            (string) filemtime($this->getPluginPath($pluginPath, '/public/js/mollie-components-blocks.min.js')),
            true
        );

        wp_register_style(
            'unabledButton',
            $this->getPluginUrl($pluginUrl, '/public/css/unabledButton.min.css'),
            [],
            (string) filemtime($this->getPluginPath($pluginPath, '/public/css/unabledButton.min.css')),
            'screen'
        );
        wp_register_script(
            'gatewaySurcharge',
            $this->getPluginUrl($pluginUrl, '/public/js/gatewaySurcharge.min.js'),
            ['underscore', 'jquery'],
            (string) filemtime($this->getPluginPath($pluginPath, '/public/js/gatewaySurcharge.min.js')),
            true
        );
        wp_register_script(
            'mollie-billie-classic-handles',
            $this->getPluginUrl($pluginUrl, '/public/js/mollieBillie.min.js'),
            ['underscore', 'jquery'],
            (string) filemtime($this->getPluginPath($pluginPath, '/public/js/mollieBillie.min.js')),
            true
        );
        wp_register_script(
            'mollie-in3-classic-handles',
            $this->getPluginUrl($pluginUrl, '/public/js/mollieIn3.min.js'),
            ['underscore', 'jquery'],
            (string) filemtime($this->getPluginPath($pluginPath, '/public/js/mollieIn3.min.js')),
            true
        );
    }

    public function registerBlockScripts(string $pluginUrl, string $pluginPath): void
    {

        wp_register_script(
            'mollie_block_index',
            $this->getPluginUrl($pluginUrl, '/public/js/mollieBlockIndex.min.js'),
            ['wc-blocks-registry', 'underscore', 'jquery'],
            (string) filemtime($this->getPluginPath($pluginPath, '/public/js/mollieBlockIndex.min.js')),
            true
        );
    }

    /**
     * Enqueue Frontend only scripts
     *
     * @param $container Container
     * @return void
     */
    public function enqueueFrontendScripts($container)
    {
        if (is_admin() || !mollieWooCommerceIsCheckoutContext()) {
            return;
        }
        wp_enqueue_style('mollie-gateway-icons');
        $isBillieEnabled = mollieWooCommerceIsGatewayEnabled('mollie_wc_gateway_billie_settings', 'enabled');
        $allMethodsEnabledAtMollie = $container->get('gateway.paymentMethodsEnabledAtMollie');
        $isBillieEnabledAtMollie = in_array('billie', $allMethodsEnabledAtMollie, true);
        if ($isBillieEnabled && $isBillieEnabledAtMollie) {
            wp_enqueue_script('mollie-billie-classic-handles');
        }
        $isIn3Enabled = mollieWooCommerceIsGatewayEnabled('mollie_wc_gateway_in3_settings', 'enabled');
        $isIn3EnabledAtMollie = in_array('in3', $allMethodsEnabledAtMollie, true);
        if ($isIn3Enabled && $isIn3EnabledAtMollie) {
            wp_enqueue_script('mollie-in3-classic-handles');
        }

        $applePayGatewayEnabled = mollieWooCommerceIsGatewayEnabled('mollie_wc_gateway_applepay_settings', 'enabled');
        $isAppleEnabledAtMollie = in_array('applepay', $allMethodsEnabledAtMollie, true);
        if (!$applePayGatewayEnabled || !$isAppleEnabledAtMollie) {
            return;
        }
        wp_enqueue_style('unabledButton');
        wp_enqueue_script('mollie_wc_gateway_applepay');
    }

    /**
     * Enqueue Mollie Component Assets
     */
    public function enqueueComponentsAssets(Settings $settingsHelper): void
    {
        if (
            is_admin()
            || (!mollieWooCommerceIsCheckoutContext()
                && !has_block("woocommerce/checkout"))
        ) {
            return;
        }

        try {
            $merchantProfileId = $settingsHelper->mollieWooCommerceMerchantProfileId();
        } catch (ApiException $exception) {
            return;
        }

        $mollieComponentsStylesGateways = mollieWooCommerceComponentsStylesForAvailableGateways();
        $gatewayNames = array_keys($mollieComponentsStylesGateways);

        if (!$merchantProfileId || !$mollieComponentsStylesGateways) {
            return;
        }

        $locale = get_locale();
        $locale = str_replace('_formal', '', $locale);
        $allowedLocaleValues = AcceptedLocaleValuesDictionary::ALLOWED_LOCALES_KEYS_MAP;
        if (!in_array($locale, $allowedLocaleValues, true)) {
            $locale = AcceptedLocaleValuesDictionary::DEFAULT_LOCALE_VALUE;
        }

        wp_enqueue_style('mollie-components');
        $object_name = 'mollieComponentsSettings';
        $data = [
            'merchantProfileId' => $merchantProfileId,
            'options' => [
                'locale' => $locale,
                'testmode' => $settingsHelper->isTestModeEnabled(),
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
        ];
        if (has_block("woocommerce/checkout")) {
            wp_enqueue_script('mollie-components-blocks');
            wp_localize_script(
                'mollie-components-blocks',
                $object_name,
                $data
            );
            return;
        }

        wp_enqueue_script('mollie-components');
        wp_localize_script(
            'mollie-components',
            $object_name,
            $data
        );
    }

    protected function getPluginUrl(string $pluginUrl, string $path = ''): string
    {
        return $pluginUrl . ltrim($path, '/');
    }

    protected function getPluginPath(string $pluginPath, string $path = ''): string
    {
        return $pluginPath . ltrim($path, '/');
    }

    /**
     * @param ?string $current_section
     */
    protected function enqueueIconSettings(?string $current_section): void
    {
        $uri = isset($_SERVER['REQUEST_URI']) ? wc_clean(
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            wp_unslash($_SERVER['REQUEST_URI'])
        ) : '';
        if (is_string($uri) && strpos($uri, 'tab=mollie_settings')) {
            wp_enqueue_style('mollie-gateway-icons');
        }
        if (!$current_section || strpos($current_section, 'mollie_wc_gateway_') === false) {
            return;
        }
        wp_enqueue_script('mollie_wc_gateway_settings');
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
            if (
                $cart_content['data'] instanceof \WC_Product_Subscription
                || $cart_content['data'] instanceof \WC_Product_Subscription_Variation
            ) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param ContainerInterface $container
     * @return void
     * @psalm-suppress InvalidGlobal
     *
     */
    protected function setupModuleActions(ContainerInterface $container): void
    {
        /** @var Data */
        $dataService = $container->get('settings.data_helper');
        $hasBlocksEnabled = $dataService->isBlockPluginActive();
        /** @var string */
        $pluginVersion = $container->get('shared.plugin_version');
        /** @var string */
        $pluginUrl = $container->get('shared.plugin_url');
        /** @var string */
        $pluginPath = $container->get('shared.plugin_path');
        /** @var Settings */
        $settingsHelper = $container->get('settings.settings_helper');
        $gatewayInstances = $container->get('gateway.instances');

        /** Add support to Mollie blocks for Woocommerce checkout blocks functionality */
        //https://github.com/woocommerce/woocommerce-blocks/blob/trunk/docs/third-party-developers/extensibility/checkout-payment-methods/payment-method-integration.md#putting-it-all-together
        add_action(
            'woocommerce_blocks_loaded',
            function () use ($dataService, $gatewayInstances, $pluginUrl, $pluginPath, $hasBlocksEnabled) {
                if (
                    $hasBlocksEnabled && is_admin() && class_exists(
                        'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType'
                    )
                ) {
                    add_action(
                        'woocommerce_blocks_payment_method_type_registration',
                        function (PaymentMethodRegistry $paymentMethodRegistry) use (
                            $dataService,
                            $gatewayInstances,
                            $pluginUrl,
                            $pluginPath
                        ) {
                            $paymentMethodRegistry->register(
                                new MollieCheckoutBlocksSupport(
                                    $dataService,
                                    $gatewayInstances,
                                    $this->getPluginUrl($pluginUrl, '/public/js/mollieBlockIndex.min.js'),
                                    (string)filemtime(
                                        $this->getPluginPath($pluginPath, '/public/js/mollieBlockIndex.min.js')
                                    )
                                )
                            );
                        }
                    );
                }
            }
        );

        add_action(
            'init',
            function () use ($container, $hasBlocksEnabled, $settingsHelper, $pluginUrl, $pluginPath, $dataService) {
                self::registerFrontendScripts($pluginUrl, $pluginPath);

                // Enqueue Scripts
                add_action('wp_enqueue_scripts', function () use ($container) {
                    $this->enqueueFrontendScripts($container);
                });
                add_action('wp_enqueue_scripts', function () use ($settingsHelper) {
                    $this->enqueueComponentsAssets($settingsHelper);
                });
                add_action('wp_enqueue_scripts', [$this, 'enqueueApplePayDirectScripts']);
                add_action('wp_enqueue_scripts', function () use ($pluginUrl) {
                    $this->enqueuePayPalButtonScripts($pluginUrl);
                });

                if ($hasBlocksEnabled) {
                    /** @var array */
                    $gatewayInstances = $container->get('gateway.instances');
                    self::registerBlockScripts($pluginUrl, $pluginPath);
                    add_action('wp_enqueue_scripts', function () use ($dataService, $gatewayInstances) {
                        $this->enqueueBlockCheckoutScripts($dataService, $gatewayInstances);
                    });
                    $this->registerButtonsBlockScripts($pluginUrl, $pluginPath);
                }
            }
        );
        add_action(
            'admin_init',
            function () use ($container, $hasBlocksEnabled, $pluginVersion, $dataService, $pluginUrl) {
                if (is_admin()) {
                    global $current_section;
                    wp_register_script(
                        'mollie_wc_admin_settings',
                        $this->getPluginUrl($pluginUrl, '/public/js/settings.min.js'),
                        ['underscore', 'jquery'],
                        $pluginVersion
                    );
                    wp_enqueue_script('mollie_wc_admin_settings');
                    wp_register_script(
                        'mollie_wc_gateway_advanced_settings',
                        $this->getPluginUrl(
                            $pluginUrl,
                            '/public/js/advancedSettings.min.js'
                        ),
                        ['underscore', 'jquery'],
                        $pluginVersion,
                        true
                    );
                    add_action('admin_enqueue_scripts', [$this, 'enqueueAdvancedSettingsJS'], 10, 1);

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
                            $pluginUrl,
                            '/public/js/gatewaySettings.min.js'
                        ),
                        ['underscore', 'jquery'],
                        $pluginVersion,
                        true
                    );

                    $this->enqueueIconSettings($current_section);
                }
            }
        );
    }
}

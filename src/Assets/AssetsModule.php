<?php

# -*- coding: utf-8 -*-

declare(strict_types=1);

namespace Mollie\WooCommerce\Assets;

use Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry;
use Inpsyde\Modularity\Module\ExecutableModule;
use Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use Inpsyde\Modularity\Module\ServiceModule;
use Mollie\Api\Exceptions\ApiException;
use Mollie\WooCommerce\Buttons\ApplePayButton\DataToAppleButtonScripts;
use Mollie\WooCommerce\Buttons\PayPalButton\DataToPayPal;
use Mollie\WooCommerce\Components\AcceptedLocaleValuesDictionary;
use Mollie\WooCommerce\Components\ComponentDataService;
use Mollie\WooCommerce\Settings\Settings;
use Mollie\WooCommerce\Shared\Data;
use Psr\Container\ContainerInterface;

class AssetsModule implements ExecutableModule
{
    use ModuleClassNameIdTrait;

    public function run(ContainerInterface $container): bool
    {
        $this->setupModuleActions($container);
        return true;
    }

    public function enqueueBlockCheckoutScripts(Data $dataService, array $gatewayInstances, ContainerInterface $container): void
    {
        if (!has_block('woocommerce/checkout')) {
            return;
        }
        wp_enqueue_script(MollieCheckoutBlocksSupport::getScriptHandle());
        wp_enqueue_style('mollie-gateway-icons');
        wp_enqueue_style('mollie-block-custom-field');

        MollieCheckoutBlocksSupport::localizeWCBlocksData($dataService, $gatewayInstances, $container);
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
                        '/public/js/applepayButtonBlock.min.js'
                    ),
                    [],
                    (string) filemtime(
                        $this->getPluginPath(
                            $pluginPath,
                            '/public/js/applepayButtonBlock.min.js'
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
        wp_register_script('mollie', 'https://js.mollie.com/v1/mollie.js', [], gmdate("d"), true);
        wp_register_script(
            'mollie-components',
            $this->getPluginUrl($pluginUrl, '/public/js/mollie-components.min.js'),
            ['underscore', 'jquery', 'mollie'],
            (string) filemtime($this->getPluginPath($pluginPath, '/public/js/mollie-components.min.js')),
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
            'mollie-riverty-classic-handles',
            $this->getPluginUrl($pluginUrl, '/public/js/rivertyCountryPlaceholder.min.js'),
            ['jquery'],
            (string) filemtime($this->getPluginPath($pluginPath, '/public/js/rivertyCountryPlaceholder.min.js')),
            true
        );
    }

    public function registerBlockScripts(string $pluginUrl, string $pluginPath, ContainerInterface $container): void
    {
        wp_register_script(
            'mollie_block_index',
            $this->getPluginUrl($pluginUrl, '/public/js/mollieBlockIndex.min.js'),
            ['wc-blocks-registry', 'underscore', 'jquery', 'mollie'],
            (string) filemtime($this->getPluginPath($pluginPath, '/public/js/mollieBlockIndex.min.js')),
            true
        );

        /**
         * Ensure localized data via static method call
         * TODO rework the static method call
         */
        $dataService = $container->get('settings.data_helper');
        $gatewayInstances = $container->get('__deprecated.gateway_helpers');
        MollieCheckoutBlocksSupport::localizeWCBlocksData($dataService, $gatewayInstances, $container);

        wp_register_style(
            'mollie-block-custom-field',
            $this->getPluginUrl($pluginUrl, '/public/css/mollie-block-custom-field.min.css'),
            [],
            (string) filemtime($this->getPluginPath($pluginPath, '/public/css/mollie-block-custom-field.min.css')),
            'screen'
        );
    }

    /**
     * Enqueue Frontend only scripts
     *
     * @param ContainerInterface $container
     * @return void
     */
    public function enqueueFrontendScripts($container)
    {
        if (is_admin() || !mollieWooCommerceIsCheckoutContext()) {
            return;
        }
        wp_enqueue_style('mollie-gateway-icons');
        $allMethodsEnabledAtMollie = $container->get('gateway.paymentMethodsEnabledAtMollie');
        $isRivertyEnabled = mollieWooCommerceIsGatewayEnabled('mollie_wc_gateway_riverty_settings', 'enabled');
        $isRivertyEnabledAtMollie = in_array('riverty', $allMethodsEnabledAtMollie, true);
        if ($isRivertyEnabled && $isRivertyEnabledAtMollie) {
            wp_enqueue_script('mollie-riverty-classic-handles');
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
    public function enqueueComponentsAssets(ComponentDataService $componentDataService): void
    {
        if (!$componentDataService->shouldLoadComponents()) {
            return;
        }

        $componentData = $componentDataService->getComponentDataWithContext(
            is_checkout(),
            is_checkout_pay_page()
        );

        if ($componentData === null) {
            return;
        }

        wp_enqueue_style('mollie-components');
        $object_name = 'mollieComponentsSettings';

        wp_enqueue_script('mollie-components');
        wp_localize_script('mollie-components', $object_name, $componentData);
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
            || $current_section !== 'mollie_advanced'
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
        $gatewayInstances = $container->get('__deprecated.gateway_helpers');

        add_action('woocommerce_blocks_loaded', static function () {
            woocommerce_store_api_register_update_callback(
                [
                    'namespace' => 'mollie-payments-for-woocommerce',
                    'callback' => static function () {
                        // Do nothing
                    },
                ]
            );
        });

        add_action(
            'woocommerce_init',
            function () use ($container, $hasBlocksEnabled, $settingsHelper, $pluginUrl, $pluginPath, $dataService) {
                self::registerFrontendScripts($pluginUrl, $pluginPath);

                // Enqueue Scripts
                add_action('wp_enqueue_scripts', function () use ($container) {
                    $this->enqueueFrontendScripts($container);
                });
                add_action('wp_enqueue_scripts', function () use ($container) {
                    /** @var ComponentDataService */
                    $componentDataService = $container->get('components.data_service');
                    assert($componentDataService instanceof ComponentDataService);
                    $this->enqueueComponentsAssets($componentDataService);
                });
                add_action('wp_enqueue_scripts', [$this, 'enqueueApplePayDirectScripts']);
                add_action('wp_enqueue_scripts', function () use ($pluginUrl) {
                    $this->enqueuePayPalButtonScripts($pluginUrl);
                });
                //we need to hook into the payment library before it's loaded
                add_filter('inpsyde_payment_gateway_blocks_dependencies', function($dependencies) {
                    $dependencies[] = 'mollie_block_index';
                    return $dependencies;
                });
                if ($hasBlocksEnabled) {
                    /** @var array */
                    $gatewayInstances = $container->get('__deprecated.gateway_helpers');
                    self::registerBlockScripts($pluginUrl, $pluginPath, $container);
                    add_action('wp_enqueue_scripts', function () use ($dataService, $gatewayInstances, $container) {
                        $this->enqueueBlockCheckoutScripts($dataService, $gatewayInstances, $container);
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

                    wp_register_script(
                        'mollie_wc_settings_2024',
                        $this->getPluginUrl(
                            $pluginUrl,
                            '/public/js/mollie-settings-2024.min.js'
                        ),
                        ['underscore', 'jquery'],
                        $pluginVersion,
                        true
                    );
                    $this->enqueueMollieSettings();
                    $this->enqueueIconSettings($current_section);
                }
            }
        );
    }

    protected function enqueueMollieSettings()
    {

        $uri = isset($_SERVER['REQUEST_URI']) ? wc_clean(
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            wp_unslash($_SERVER['REQUEST_URI'])
        ) : '';
        if (is_string($uri) && strpos($uri, 'tab=mollie_settings')) {
            wp_enqueue_script('mollie_wc_settings_2024');
        }
    }
}

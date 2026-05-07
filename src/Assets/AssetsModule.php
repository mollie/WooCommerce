<?php

# -*- coding: utf-8 -*-
declare (strict_types=1);
namespace Mollie\WooCommerce\Assets;

use Mollie\Inpsyde\Modularity\Module\ExecutableModule;
use Mollie\Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use Mollie\Inpsyde\Modularity\Module\ServiceModule;
use Mollie\WooCommerce\Buttons\ApplePayButton\DataToAppleButtonScripts;
use Mollie\WooCommerce\Buttons\PayPalButton\DataToPayPal;
use Mollie\WooCommerce\Components\ComponentDataService;
use Mollie\Psr\Container\ContainerInterface;
class AssetsModule implements ExecutableModule, ServiceModule
{
    use ModuleClassNameIdTrait;
    public function services(): array
    {
        return [DataToAppleButtonScripts::class => static function (ContainerInterface $container): DataToAppleButtonScripts {
            return new DataToAppleButtonScripts();
        }, DataToPayPal::class => static function (ContainerInterface $container): DataToPayPal {
            $pluginUrl = $container->get('shared.plugin_url');
            return new DataToPayPal($pluginUrl);
        }];
    }
    public function run(ContainerInterface $container): bool
    {
        $this->setupModuleActions($container);
        return \true;
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
            wp_localize_script('mollie_applepaydirect', 'mollieApplePayDirectData', $dataToScripts->applePayScriptData());
        }
        if (mollieWooCommerceIsApplePayDirectEnabled('cart') && is_cart()) {
            $cart = WC()->cart;
            if ($this->cartHasSubscription($cart) && !is_user_logged_in()) {
                return;
            }
            $dataToScripts = new DataToAppleButtonScripts();
            wp_enqueue_style('mollie-applepaydirect');
            wp_enqueue_script('mollie_applepaydirectCart');
            wp_localize_script('mollie_applepaydirectCart', 'mollieApplePayDirectDataCart', $dataToScripts->applePayScriptData());
        }
        if (mollieWooCommerceIsApplePayDirectEnabled('express_checkout') && (is_checkout() || is_cart())) {
            wp_enqueue_style('mollie-applepaydirect');
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
            wp_localize_script('mollie_paypalButton', 'molliepaypalbutton', $dataToScripts->paypalbuttonScriptData());
        }
        if (mollieWooCommerceIsPayPalButtonEnabled('cart') && is_cart()) {
            $cart = WC()->cart;
            if ($this->cartHasSubscription($cart)) {
                return;
            }
            $dataToScripts = new DataToPayPal($pluginUrl);
            wp_enqueue_style('unabledButton');
            wp_enqueue_script('mollie_paypalButtonCart');
            wp_localize_script('mollie_paypalButtonCart', 'molliepaypalButtonCart', $dataToScripts->paypalbuttonScriptData());
        }
    }
    /**
     * Register Scripts
     *
     * @return void
     */
    protected function registerFrontendScripts(string $pluginUrl, string $pluginPath)
    {
        wp_register_script('mollie_wc_gateway_applepay', $this->getPluginUrl($pluginUrl, '/public/js/applepay.min.js'), [], (string) filemtime($this->getPluginPath($pluginPath, '/public/js/applepay.min.js')), \true);
        wp_register_style('mollie-gateway-icons', $this->getPluginUrl($pluginUrl, '/public/css/mollie-gateway-icons.min.css'), [], (string) filemtime($this->getPluginPath($pluginPath, '/public/css/mollie-gateway-icons.min.css')), 'screen');
        wp_register_style('mollie-components', $this->getPluginUrl($pluginUrl, '/public/css/mollie-components.min.css'), [], (string) filemtime($this->getPluginPath($pluginPath, '/public/css/mollie-components.min.css')), 'screen');
        wp_register_style('mollie-applepaydirect', $this->getPluginUrl($pluginUrl, '/public/css/mollie-applepaydirect.min.css'), [], (string) filemtime($this->getPluginPath($pluginPath, '/public/css/mollie-applepaydirect.min.css')), 'screen');
        wp_register_script('mollie_applepaydirect', $this->getPluginUrl($pluginUrl, '/public/js/applepayDirect.min.js'), ['underscore', 'jquery'], (string) filemtime($this->getPluginPath($pluginPath, '/public/js/applepayDirect.min.js')), \true);
        //paypal in product page
        wp_register_script('mollie_paypalButton', $this->getPluginUrl($pluginUrl, '/public/js/paypalButton.min.js'), ['underscore', 'jquery'], (string) filemtime($this->getPluginPath($pluginPath, '/public/js/paypalButton.min.js')), \true);
        //paypal in classic cart page
        wp_register_script('mollie_paypalButtonCart', $this->getPluginUrl($pluginUrl, '/public/js/paypalButtonCart.min.js'), ['underscore', 'jquery'], (string) filemtime($this->getPluginPath($pluginPath, '/public/js/paypalButtonCart.min.js')), \true);
        wp_register_script('mollie_applepaydirectCart', $this->getPluginUrl($pluginUrl, '/public/js/applepayDirectCart.min.js'), ['underscore', 'jquery'], (string) filemtime($this->getPluginPath($pluginPath, '/public/js/applepayDirectCart.min.js')), \true);
        wp_register_script('mollie', 'https://js.mollie.com/v1/mollie.js', [], gmdate("d"), \true);
        wp_register_script('mollie-components', $this->getPluginUrl($pluginUrl, '/public/js/mollie-components.min.js'), ['underscore', 'jquery', 'mollie'], (string) filemtime($this->getPluginPath($pluginPath, '/public/js/mollie-components.min.js')), \true);
        wp_register_style('unabledButton', $this->getPluginUrl($pluginUrl, '/public/css/unabledButton.min.css'), [], (string) filemtime($this->getPluginPath($pluginPath, '/public/css/unabledButton.min.css')), 'screen');
        wp_register_script('gatewaySurcharge', $this->getPluginUrl($pluginUrl, '/public/js/gatewaySurcharge.min.js'), ['underscore', 'jquery'], (string) filemtime($this->getPluginPath($pluginPath, '/public/js/gatewaySurcharge.min.js')), \true);
        wp_register_script('mollie-riverty-classic-handles', $this->getPluginUrl($pluginUrl, '/public/js/rivertyCountryPlaceholder.min.js'), ['jquery'], (string) filemtime($this->getPluginPath($pluginPath, '/public/js/rivertyCountryPlaceholder.min.js')), \true);
    }
    public function registerBlockScripts(string $pluginUrl, string $pluginPath, ContainerInterface $container): void
    {
        $assetFilePath = $this->getPluginPath($pluginPath, '/public/js/mollieBlockIndex.min.asset.php');
        $assetData = file_exists($assetFilePath) ? require $assetFilePath : ['dependencies' => [], 'version' => '1.0.0'];
        // Runtime globals not detectable by webpack's dependency extraction
        $runtimeDependencies = ['jquery', 'mollie'];
        wp_register_script('mollie_block_index', $this->getPluginUrl($pluginUrl, '/public/js/mollieBlockIndex.min.js'), array_unique(array_merge($assetData['dependencies'], $runtimeDependencies)), (string) ($assetData['version'] ?? filemtime($this->getPluginPath($pluginPath, '/public/js/mollieBlockIndex.min.js'))), \true);
        // Localize the gateway list on our handle so it's available before the
        // library's script runs. The library will set the same global again on
        // inpsyde-blocks — that's harmless, same data.
        wp_localize_script('mollie_block_index', 'inpsydeGateways', $container->get('payment_gateways.methods_supporting_blocks'));
        wp_register_style('mollie-block-custom-field', $this->getPluginUrl($pluginUrl, '/public/css/mollie-block-custom-field.min.css'), [], (string) filemtime($this->getPluginPath($pluginPath, '/public/css/mollie-block-custom-field.min.css')), 'screen');
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
        $isRivertyEnabledAtMollie = in_array('riverty', $allMethodsEnabledAtMollie, \true);
        if ($isRivertyEnabled && $isRivertyEnabledAtMollie) {
            wp_enqueue_script('mollie-riverty-classic-handles');
        }
        $applePayGatewayEnabled = mollieWooCommerceIsGatewayEnabled('mollie_wc_gateway_applepay_settings', 'enabled');
        $isAppleEnabledAtMollie = in_array('applepay', $allMethodsEnabledAtMollie, \true);
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
        wp_enqueue_style('mollie-components');
        if (!$componentDataService->shouldLoadComponents()) {
            return;
        }
        $componentData = $componentDataService->getComponentDataWithContext(is_checkout(), is_checkout_pay_page());
        if ($componentData === null) {
            return;
        }
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
        if (!$current_section || strpos($current_section, 'mollie_wc_gateway_') === \false) {
            return;
        }
        wp_enqueue_script('mollie_wc_gateway_settings');
        $settingsName = "{$current_section}_settings";
        $gatewaySettings = get_option($settingsName, \false);
        $message = __('No custom logo selected', 'mollie-payments-for-woocommerce');
        $isEnabled = \false;
        if ($gatewaySettings && isset($gatewaySettings['enable_custom_logo'])) {
            $isEnabled = $gatewaySettings['enable_custom_logo'] === 'yes';
        }
        $uploadFieldName = "{$current_section}_upload_logo";
        $enabledFieldName = "{$current_section}_enable_custom_logo";
        $gatewayIconUrl = '';
        if ($gatewaySettings && isset($gatewaySettings['iconFileUrl'])) {
            $gatewayIconUrl = $gatewaySettings['iconFileUrl'];
        }
        wp_localize_script('mollie_wc_gateway_settings', 'gatewaySettingsData', ['isEnabledIcon' => $isEnabled, 'uploadFieldName' => $uploadFieldName, 'enableFieldName' => $enabledFieldName, 'iconUrl' => $gatewayIconUrl, 'message' => $message, 'pluginUrlImages' => plugins_url('public/images', \M4W_FILE)]);
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
        if ($current_screen->id !== 'woocommerce_page_wc-settings' || $current_tab !== 'mollie_settings' || $current_section !== 'mollie_advanced') {
            return;
        }
        wp_enqueue_script('mollie_wc_gateway_advanced_settings');
        wp_localize_script('mollie_wc_gateway_advanced_settings', 'mollieWebhookTestData', ['ajaxUrl' => admin_url('admin-ajax.php'), 'messages' => ['waiting' => __('Waiting for webhook...', 'mollie-payments-for-woocommerce'), 'takingLong' => __('Still waiting... This is taking longer than expected.', 'mollie-payments-for-woocommerce'), 'timeout' => __('⚠ Webhook test timed out. Please check your firewall settings and try again.', 'mollie-payments-for-woocommerce'), 'success' => __('✓ Webhook received successfully!', 'mollie-payments-for-woocommerce'), 'noWebhook' => __('✗ Webhook not received. Please check your server configuration.', 'mollie-payments-for-woocommerce'), 'error' => __('An error occurred. Please try again.', 'mollie-payments-for-woocommerce'), 'creating' => __('Creating test payment...', 'mollie-payments-for-woocommerce'), 'checkoutRequired' => __('A test payment has been created. Please complete the steps below to trigger the webhook.', 'mollie-payments-for-woocommerce'), 'step1' => __('Step 1:', 'mollie-payments-for-woocommerce'), 'step2' => __('Step 2:', 'mollie-payments-for-woocommerce'), 'step3' => __('Step 3:', 'mollie-payments-for-woocommerce'), 'clickCheckout' => __('Click the button below to open the Mollie test payment page in a new tab.', 'mollie-payments-for-woocommerce'), 'openCheckout' => __('Open Test Payment Page', 'mollie-payments-for-woocommerce'), 'selectStatus' => __('Select a payment status "Paid" on the Mollie page. Then click on Continue.', 'mollie-payments-for-woocommerce'), 'clickVerify' => __('Return to this tab and click the button below to verify the webhook was received.', 'mollie-payments-for-woocommerce'), 'verifyWebhook' => __('Verify Webhook', 'mollie-payments-for-woocommerce'), 'cancelTest' => __('Cancel Test', 'mollie-payments-for-woocommerce')]]);
        wp_enqueue_style('mollie-advanced-settings');
    }
    /**
     * @param \WC_Cart $cart
     * @return bool
     */
    protected function cartHasSubscription(\WC_Cart $cart): bool
    {
        foreach ($cart->cart_contents as $cart_content) {
            if ($cart_content['data'] instanceof \WC_Product_Subscription || $cart_content['data'] instanceof \WC_Product_Subscription_Variation) {
                return \true;
            }
        }
        return \false;
    }
    /**
     * @param ContainerInterface $container
     * @return void
     * @psalm-suppress InvalidGlobal
     *
     */
    protected function setupModuleActions(ContainerInterface $container): void
    {
        /** @var string */
        $pluginVersion = $container->get('shared.plugin_version');
        /** @var string */
        $pluginUrl = $container->get('shared.plugin_url');
        /** @var string */
        $pluginPath = $container->get('shared.plugin_path');
        add_action('woocommerce_blocks_loaded', static function () {
            woocommerce_store_api_register_update_callback(['namespace' => 'mollie-payments-for-woocommerce', 'callback' => static function () {
                // Do nothing
            }]);
            // Expose is_virtual per cart item in the WC Store API.
            // Called directly here (no hook wrapper) — setupModuleActions() runs
            // during module boot on plugins_loaded after WooCommerce has loaded,
            // so the function and StoreApi container are already available.
            // ExtendSchema::register_endpoint_data just appends to an in-memory
            // array consumed on REST requests, so there is no timing deadline.
            woocommerce_store_api_register_endpoint_data(['endpoint' => 'cart-item', 'namespace' => 'mollie-payments', 'data_callback' => static function (array $cart_item): array {
                $product = $cart_item['data'] ?? null;
                return ['virtual' => $product instanceof \WC_Product && $product->is_virtual()];
            }, 'schema_callback' => static function (): array {
                return ['virtual' => ['description' => 'Whether the cart item is a virtual product', 'type' => 'boolean', 'context' => ['view', 'edit'], 'readonly' => \true]];
            }, 'schema_type' => \ARRAY_A]);
        });
        add_action('woocommerce_init', function () use ($container, $pluginUrl, $pluginPath) {
            self::registerFrontendScripts($pluginUrl, $pluginPath);
            $this->registerBlockScripts($pluginUrl, $pluginPath, $container);
            // Enqueue Scripts
            add_action('wp_enqueue_scripts', function () use ($container) {
                $this->enqueueFrontendScripts($container);
            });
            add_action('wp_enqueue_scripts', function () use ($container) {
                if (!mollieWooCommerceIsCheckoutContext()) {
                    return;
                }
                $componentDataService = $container->get('components.data_service');
                assert($componentDataService instanceof ComponentDataService);
                wp_localize_script('mollie_block_index', 'mollieServerData', ['isOrderPayPage' => is_checkout_pay_page(), 'componentData' => $componentDataService->getComponentData()]);
                $this->enqueueComponentsAssets($componentDataService);
            });
            add_action('wp_enqueue_scripts', [$this, 'enqueueApplePayDirectScripts']);
            add_action('wp_enqueue_scripts', function () use ($pluginUrl) {
                $this->enqueuePayPalButtonScripts($pluginUrl);
            });
            add_filter('inpsyde_payment_gateway_blocks_dependencies', function ($dependencies) {
                $dependencies[] = 'mollie_block_index';
                return $dependencies;
            });
        });
        add_action('admin_init', function () use ($container, $pluginVersion, $pluginUrl) {
            if (is_admin()) {
                global $current_section;
                wp_register_script('mollie_wc_admin_settings', $this->getPluginUrl($pluginUrl, '/public/js/settings.min.js'), ['underscore', 'jquery'], $pluginVersion);
                wp_enqueue_script('mollie_wc_admin_settings');
                wp_register_script('mollie_wc_gateway_advanced_settings', $this->getPluginUrl($pluginUrl, '/public/js/advancedSettings.min.js'), ['underscore', 'jquery'], $pluginVersion, \true);
                wp_register_style('mollie-advanced-settings', $this->getPluginUrl($pluginUrl, '/public/css/mollie-advanced-settings.min.css'), [], $pluginVersion, 'screen');
                add_action('admin_enqueue_scripts', [$this, 'enqueueAdvancedSettingsJS'], 10, 1);
                wp_localize_script('mollie_wc_admin_settings', 'mollieSettingsData', ['current_section' => $current_section]);
                wp_register_script('mollie_wc_gateway_settings', $this->getPluginUrl($pluginUrl, '/public/js/gatewaySettings.min.js'), ['underscore', 'jquery'], $pluginVersion, \true);
                wp_register_script('mollie_wc_settings_2024', $this->getPluginUrl($pluginUrl, '/public/js/mollie-settings-2024.min.js'), ['underscore', 'jquery'], $pluginVersion, \true);
                $this->enqueueMollieSettings();
                $this->enqueueIconSettings($current_section);
            }
        });
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

<?php

# -*- coding: utf-8 -*-

declare(strict_types=1);

namespace Mollie\WooCommerce\Gateway;

use Inpsyde\Modularity\Module\ExecutableModule;
use Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use Inpsyde\Modularity\Module\ServiceModule;
use Mollie\WooCommerce\BlockService\CheckoutBlockService;
use Mollie\WooCommerce\Buttons\ApplePayButton\AppleAjaxRequests;
use Mollie\WooCommerce\Buttons\ApplePayButton\ApplePayDirectHandler;
use Mollie\WooCommerce\Buttons\ApplePayButton\ResponsesToApple;
use Mollie\WooCommerce\Buttons\PayPalButton\DataToPayPal;
use Mollie\WooCommerce\Buttons\PayPalButton\PayPalAjaxRequests;
use Mollie\WooCommerce\Buttons\PayPalButton\PayPalButtonHandler;
use Mollie\WooCommerce\Gateway\Voucher\MaybeDisableGateway;
use Mollie\WooCommerce\Notice\AdminNotice;
use Mollie\WooCommerce\Notice\NoticeInterface;
use Mollie\WooCommerce\Payment\MollieObject;
use Mollie\WooCommerce\Payment\MollieOrderService;
use Mollie\WooCommerce\Payment\OrderInstructionsService;
use Mollie\WooCommerce\Payment\PaymentCheckoutRedirectService;
use Mollie\WooCommerce\Payment\PaymentFactory;
use Mollie\WooCommerce\Payment\PaymentFieldsService;
use Mollie\WooCommerce\Payment\PaymentService;
use Mollie\WooCommerce\PaymentMethods\IconFactory;
use Mollie\WooCommerce\PaymentMethods\PaymentMethodI;
use Mollie\WooCommerce\SDK\Api;
use Mollie\WooCommerce\SDK\HttpResponse;
use Mollie\WooCommerce\Settings\Settings;
use Mollie\WooCommerce\Shared\Data;
use Mollie\WooCommerce\Shared\GatewaySurchargeHandler;
use Mollie\WooCommerce\Shared\SharedDataDictionary;
use Mollie\WooCommerce\Subscription\MollieSepaRecurringGateway;
use Mollie\WooCommerce\Subscription\MollieSubscriptionGateway;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface as Logger;

class GatewayModule implements ServiceModule, ExecutableModule
{
    use ModuleClassNameIdTrait;

    public const APPLE_PAY_METHOD_ALLOWED_KEY = 'mollie_apple_pay_method_allowed';
    public const POST_DATA_KEY = 'post_data';
    /**
     * @var mixed
     */
    protected $gatewayClassnames;
    /**
     * @var mixed
     */
    protected $pluginId;

    public function services(): array
    {
        return [
            'gateway.classnames' => static function (): array {
                return SharedDataDictionary::GATEWAY_CLASSNAMES;
            },
            'gateway.instances' => function (ContainerInterface $container): array {
                return $this->instantiatePaymentMethodGateways($container);
            },
            'gateway.paymentMethods' => static function (ContainerInterface $container): array {
                return (new self())->instantiatePaymentMethods($container);
            },
            'gateway.paymentMethodsEnabledAtMollie' => static function (ContainerInterface $container): array {
                $dataHelper = $container->get('settings.data_helper');
                assert($dataHelper instanceof Data);
                $settings = $container->get('settings.settings_helper');
                assert($settings instanceof Settings);
                $apiKey = $settings->getApiKey();
                $methods = $apiKey ? $dataHelper->getAllPaymentMethods($apiKey) : [];
                $enabledMethods = [];
                foreach ($methods as $method) {
                    $enabledMethods[] = $method['id'];
                }
                return $enabledMethods;
            },
            'gateway.listAllMethodsAvailable' => static function (ContainerInterface $container): array {
                $dataHelper = $container->get('settings.data_helper');
                assert($dataHelper instanceof Data);
                $settings = $container->get('settings.settings_helper');
                assert($settings instanceof Settings);
                $apiKey = $settings->getApiKey();
                $methods = $apiKey ? $dataHelper->getAllAvailablePaymentMethods() : [];
                $availableMethods = [];
                $implementedMethods = $container->get('gateway.classnames');
                foreach ($methods as $method) {
                    if (in_array('Mollie_WC_Gateway_' . ucfirst($method['id']), $implementedMethods, true)) {
                        $availableMethods[] = $method;
                    }
                }
                return $availableMethods;
            },
            'gateway.isSDDGatewayEnabled' => static function (ContainerInterface $container): bool {
                $enabledMethods = $container->get('gateway.paymentMethodsEnabledAtMollie');
                return in_array('directdebit', $enabledMethods, true);
            },
            IconFactory::class => static function (ContainerInterface $container): IconFactory {
                $pluginUrl = $container->get('shared.plugin_url');
                $pluginPath = $container->get('shared.plugin_path');
                return new IconFactory($pluginUrl, $pluginPath);
            },
            PaymentService::class => static function (ContainerInterface $container): PaymentService {
                $logger = $container->get(Logger::class);
                assert($logger instanceof Logger);
                $notice = $container->get(AdminNotice::class);
                assert($notice instanceof AdminNotice);
                $paymentFactory = $container->get(PaymentFactory::class);
                assert($paymentFactory instanceof PaymentFactory);
                $data = $container->get('settings.data_helper');
                assert($data instanceof Data);
                $api = $container->get('SDK.api_helper');
                assert($api instanceof Api);
                $settings = $container->get('settings.settings_helper');
                assert($settings instanceof Settings);
                $pluginId = $container->get('shared.plugin_id');
                $paymentCheckoutRedirectService = $container->get(PaymentCheckoutRedirectService::class);
                assert($paymentCheckoutRedirectService instanceof PaymentCheckoutRedirectService);
                $voucherDefaultCategory = $container->get('voucher.defaultCategory');
                return new PaymentService($notice, $logger, $paymentFactory, $data, $api, $settings, $pluginId, $paymentCheckoutRedirectService, $voucherDefaultCategory);
            },
            OrderInstructionsService::class => static function (): OrderInstructionsService {
                return new OrderInstructionsService();
            },
            PaymentFieldsService::class => static function (ContainerInterface $container): PaymentFieldsService {
                $data = $container->get('settings.data_helper');
                assert($data instanceof Data);
                return new PaymentFieldsService($data);
            },
            PaymentCheckoutRedirectService::class => static function (
                ContainerInterface $container
            ): PaymentCheckoutRedirectService {
                $data = $container->get('settings.data_helper');
                assert($data instanceof Data);
                return new PaymentCheckoutRedirectService($data);
            },
            Surcharge::class => static function (ContainerInterface $container): Surcharge {
                return new Surcharge();
            },
            MollieOrderService::class => static function (ContainerInterface $container): MollieOrderService {
                $HttpResponseService = $container->get('SDK.HttpResponse');
                assert($HttpResponseService instanceof HttpResponse);
                $logger = $container->get(Logger::class);
                assert($logger instanceof Logger);
                $paymentFactory = $container->get(PaymentFactory::class);
                assert($paymentFactory instanceof PaymentFactory);
                $data = $container->get('settings.data_helper');
                assert($data instanceof Data);
                $pluginId = $container->get('shared.plugin_id');
                return new MollieOrderService($HttpResponseService, $logger, $paymentFactory, $data, $pluginId);
            },
            OrderMandatoryGatewayDisabler::class => static function (ContainerInterface $container): OrderMandatoryGatewayDisabler {
                $settings = $container->get('settings.settings_helper');
                assert($settings instanceof Settings);
                $isSettingsOrderApi = $settings->isOrderApiSetting();
                return new OrderMandatoryGatewayDisabler($isSettingsOrderApi);
            },
            'gateway.isBillieEnabled' => static function (ContainerInterface $container): bool {
                $settings = $container->get('settings.settings_helper');
                assert($settings instanceof Settings);
                $isSettingsOrderApi = $settings->isOrderApiSetting();
                $billie = isset($container->get('gateway.paymentMethods')['billie']) ? $container->get('gateway.paymentMethods')['billie'] : null;
                $isBillieEnabled = false;
                if ($billie instanceof PaymentMethodI) {
                    $isBillieEnabled = $billie->getProperty('enabled') === 'yes';
                }
                return $isSettingsOrderApi && $isBillieEnabled;
            },
        ];
    }

    public function run(ContainerInterface $container): bool
    {
        $this->pluginId = $container->get('shared.plugin_id');
        $this->gatewayClassnames = $container->get('gateway.classnames');
        add_filter($this->pluginId . '_retrieve_payment_gateways', function () {
            return $this->gatewayClassnames;
        });

        add_filter('woocommerce_payment_gateways', static function ($gateways) use ($container) {
            $mollieGateways = $container->get('gateway.instances');
            return array_merge($gateways, $mollieGateways);
        });
        add_filter('woocommerce_payment_gateways', [$this, 'maybeDisableApplePayGateway'], 20);
        add_filter('woocommerce_payment_gateways', static function ($gateways) use ($container) {
            $orderMandatoryGatewayDisabler = $container->get(OrderMandatoryGatewayDisabler::class);
            assert($orderMandatoryGatewayDisabler instanceof OrderMandatoryGatewayDisabler);
            return $orderMandatoryGatewayDisabler->processGateways($gateways);
        });
         add_filter('woocommerce_payment_gateways', static function ($gateways) {
            $maybeEnablegatewayHelper = new MaybeDisableGateway();

            return $maybeEnablegatewayHelper->maybeDisableMealVoucherGateway($gateways);
         });
        add_filter(
            'woocommerce_payment_gateways',
            [$this, 'maybeDisableBankTransferGateway'],
            20
        );
        add_filter(
            'woocommerce_payment_gateways',
            [$this, 'maybeDisableBillieGateway'],
            20
        );
        // Disable SEPA as payment option in WooCommerce checkout
        add_filter(
            'woocommerce_available_payment_gateways',
            [$this, 'disableSEPAInCheckout'],
            11,
            1
        );

        // Disable Mollie methods on some pages
        add_filter(
            'woocommerce_available_payment_gateways',
            [$this, 'disableMollieOnPaymentMethodChange'],
            11,
            1
        );
        add_action(
            'woocommerce_after_order_object_save',
            static function () {
                $mollieWooCommerceSession = mollieWooCommerceSession();
                if ($mollieWooCommerceSession instanceof \WC_Session) {
                    $mollieWooCommerceSession->__unset(self::APPLE_PAY_METHOD_ALLOWED_KEY);
                }
            }
        );
        $isBillieEnabled = $container->get('gateway.isBillieEnabled');
        if ($isBillieEnabled) {
            add_filter(
                'woocommerce_after_checkout_validation',
                [$this, 'organizationBillingFieldMandatory'],
                11,
                2
            );
        }
        // Set order to paid and processed when eventually completed without Mollie
        add_action('woocommerce_payment_complete', [$this, 'setOrderPaidByOtherGateway'], 10, 1);
        $appleGateway = isset($container->get('gateway.instances')['mollie_wc_gateway_applepay']) ? $container->get(
            'gateway.instances'
        )['mollie_wc_gateway_applepay'] : false;
        $notice = $container->get(AdminNotice::class);
        assert($notice instanceof AdminNotice);
        $logger = $container->get(Logger::class);
        assert($logger instanceof Logger);
        $pluginUrl = $container->get('shared.plugin_url');
        $apiHelper = $container->get('SDK.api_helper');
        assert($apiHelper instanceof Api);
        $settingsHelper = $container->get('settings.settings_helper');
        assert($settingsHelper instanceof Settings);
        $surchargeService = $container->get(Surcharge::class);
        assert($surchargeService instanceof Surcharge);
        $this->gatewaySurchargeHandling($surchargeService);
        if ($appleGateway) {
            $this->mollieApplePayDirectHandling($notice, $logger, $apiHelper, $settingsHelper, $appleGateway);
        }

        $paypalGateway = isset($container->get('gateway.instances')['mollie_wc_gateway_paypal']) ? $container->get(
            'gateway.instances'
        )['mollie_wc_gateway_paypal'] : false;
        if ($paypalGateway) {
            $this->molliePayPalButtonHandling($paypalGateway, $notice, $logger, $pluginUrl);
        }

        $maybeDisableVoucher = new MaybeDisableGateway();
        $dataService = $container->get('settings.data_helper');
        assert($dataService instanceof Data);
        $checkoutBlockHandler = new CheckoutBlockService($dataService, $maybeDisableVoucher);
        $checkoutBlockHandler->bootstrapAjaxRequest();
        add_action(
            'woocommerce_rest_checkout_process_payment_with_context',
            static function ($paymentContext) {
                if (strpos($paymentContext->payment_method, 'mollie_wc_gateway_') === false) {
                    return;
                }
                $title = isset($paymentContext->payment_data['payment_method_title']) ? $paymentContext->payment_data['payment_method_title'] : false;
                if (!$title) {
                    return;
                }
                $order = $paymentContext->order;
                $order->set_payment_method_title($title);
                $order->save();
            }
        );

        return true;
    }

    /**
     * Disable Bank Transfer Gateway
     *
     * @param ?array $gateways
     * @return array
     */
    public function maybeDisableBillieGateway(?array $gateways): array
    {
        if (!is_array($gateways)) {
            return [];
        }
        $isWcApiRequest = (bool)filter_input(INPUT_GET, 'wc-api', FILTER_SANITIZE_SPECIAL_CHARS);

        /*
         * There is only one case where we want to filter the gateway and it's when the
         * pay-page render the available payments methods AND the setting is enabled
         *
         * For any other case we want to be sure billie gateway is included.
         */
        if (
            $isWcApiRequest ||
            is_checkout() && ! is_wc_endpoint_url('order-pay') ||
            !wp_doing_ajax() && ! is_wc_endpoint_url('order-pay') ||
            is_admin()
        ) {
            return $gateways;
        }
        if (isset($gateways['mollie_wc_gateway_billie'])) {
            unset($gateways['mollie_wc_gateway_billie']);
        }

        return  $gateways;
    }
    /**
     * Disable Bank Transfer Gateway
     *
     * @param ?array $gateways
     * @return array
     */
    public function maybeDisableBankTransferGateway(?array $gateways): array
    {
        if (!is_array($gateways)) {
            return [];
        }
        $isWcApiRequest = (bool)filter_input(INPUT_GET, 'wc-api', FILTER_SANITIZE_SPECIAL_CHARS);

        $bankTransferSettings = get_option('mollie_wc_gateway_banktransfer_settings', false);
        $isSettingActivated = $bankTransferSettings && isset($bankTransferSettings['activate_expiry_days_setting']) && $bankTransferSettings['activate_expiry_days_setting'] === "yes";
        if ($isSettingActivated  && isset($bankTransferSettings['order_dueDate'])) {
            $isSettingActivated = $bankTransferSettings['order_dueDate'] > 0;
        }

        /*
         * There is only one case where we want to filter the gateway and it's when the
         * pay-page render the available payments methods AND the setting is enabled
         *
         * For any other case we want to be sure bank transfer gateway is included.
         */
        if (
            $isWcApiRequest ||
            !$isSettingActivated ||
            is_checkout() && ! is_wc_endpoint_url('order-pay') ||
            !wp_doing_ajax() && ! is_wc_endpoint_url('order-pay') ||
            is_admin()
        ) {
            return $gateways;
        }
        $bankTransferGatewayClassName = 'mollie_wc_gateway_banktransfer';
        unset($gateways[$bankTransferGatewayClassName]);

        return  $gateways;
    }

    /**
     * Disable Apple Pay Gateway
     *
     * @param ?array $gateways
     * @return array
     */
    public function maybeDisableApplePayGateway(?array $gateways): array
    {
        if (!is_array($gateways)) {
            return [];
        }
        $isWcApiRequest = (bool)filter_input(INPUT_GET, 'wc-api', FILTER_SANITIZE_SPECIAL_CHARS);
        $wooCommerceSession = mollieWooCommerceSession();

        /*
         * There is only one case where we want to filter the gateway and it's when the checkout
         * page render the available payments methods.
         *
         * For any other case we want to be sure apple pay gateway is included.
         */
        if (
            $isWcApiRequest ||
            !$wooCommerceSession instanceof \WC_Session ||
            !doing_action('woocommerce_payment_gateways') ||
            !wp_doing_ajax() && ! is_wc_endpoint_url('order-pay') ||
            is_admin()
        ) {
            return $gateways;
        }

        if ($wooCommerceSession->get(self::APPLE_PAY_METHOD_ALLOWED_KEY, false)) {
            return $gateways;
        }

        $applePayGatewayClassName = 'mollie_wc_gateway_applepay';
        // phpcs:ignore
        $postData = isset($_POST[self::POST_DATA_KEY]) ? wc_clean(wp_unslash($_POST[self::POST_DATA_KEY])) : '';
        parse_str($postData, $postData);
        $applePayAllowed = isset($postData[self::APPLE_PAY_METHOD_ALLOWED_KEY])
            && $postData[self::APPLE_PAY_METHOD_ALLOWED_KEY];

        if (!$applePayAllowed) {
            unset($gateways[$applePayGatewayClassName]);
        }

        if ($applePayAllowed) {
            $wooCommerceSession->set(self::APPLE_PAY_METHOD_ALLOWED_KEY, true);
        }

        return $gateways;
    }

    public function gatewaySurchargeHandling(Surcharge $surcharge)
    {
        new GatewaySurchargeHandler($surcharge);
    }

    /**
     * Don't show SEPA Direct Debit in WooCommerce Checkout
     */
    public function disableSEPAInCheckout($available_gateways)
    {
        if (is_checkout()) {
            unset($available_gateways['mollie_wc_gateway_directdebit']);
        }

        return $available_gateways;
    }

    /**
     * Don't show Mollie Payment Methods in WooCommerce Account > Subscriptions
     */
    public function disableMollieOnPaymentMethodChange($available_gateways)
    {
        // Can't use $wp->request or is_wc_endpoint_url()
        // to check if this code only runs on /subscriptions and /view-subscriptions,
        // because slugs/endpoints can be translated (with WPML) and other plugins.
        // So disabling on is_account_page (if not checkout, bug in WC) and $_GET['change_payment_method'] for now.

        // Only disable payment methods if WooCommerce Subscriptions is installed
        if (class_exists('WC_Subscription')) {
            // Do not disable if account page is also checkout
            // (workaround for bug in WC), do disable on change payment method page (param)
            if ((! is_checkout() && is_account_page()) || ! empty($_GET['change_payment_method'])) {
                foreach ($available_gateways as $key => $value) {
                    if (strpos($key, 'mollie_') !== false) {
                        unset($available_gateways[ $key ]);
                    }
                }
            }
        }

        return $available_gateways;
    }

    /**
     * If an order is paid with another payment method (gateway) after a first payment was
     * placed with Mollie, set a flag, so status updates (like expired) aren't processed by
     * Mollie Payments for WooCommerce.
     */
    public function setOrderPaidByOtherGateway($order_id)
    {
        $order = wc_get_order($order_id);

        $mollie_payment_id = $order->get_meta('_mollie_payment_id', $single = true);
        $order_payment_method = $order->get_payment_method();

        if ($mollie_payment_id !== '' && (strpos($order_payment_method, 'mollie') === false)) {
            $order->update_meta_data('_mollie_paid_by_other_gateway', '1');
            $order->save();
        }
        return true;
    }

    /**
     * Bootstrap the ApplePay button logic if feature enabled
     */
    public function mollieApplePayDirectHandling(NoticeInterface $notice, Logger $logger, Api $apiHelper, Settings $settingsHelper, MollieSubscriptionGateway $appleGateway)
    {
        $buttonEnabledCart = mollieWooCommerceIsApplePayDirectEnabled('cart');
        $buttonEnabledProduct = mollieWooCommerceIsApplePayDirectEnabled('product');

        if ($buttonEnabledCart || $buttonEnabledProduct) {
            $notices = new AdminNotice();
            $responseTemplates = new ResponsesToApple($logger, $appleGateway);
            $ajaxRequests = new AppleAjaxRequests($responseTemplates, $notice, $logger, $apiHelper, $settingsHelper);
            $applePayHandler = new ApplePayDirectHandler($notices, $ajaxRequests);
            $applePayHandler->bootstrap($buttonEnabledProduct, $buttonEnabledCart);
        }
    }

    /**
     * Bootstrap the Mollie_WC_Gateway_PayPal button logic if feature enabled
     */
    public function molliePayPalButtonHandling(
        $gateway,
        NoticeInterface $notice,
        Logger $logger,
        string $pluginUrl
    ) {

        $enabledInProduct = (mollieWooCommerceIsPayPalButtonEnabled('product'));
        $enabledInCart = (mollieWooCommerceIsPayPalButtonEnabled('cart'));
        $shouldBuildIt = $enabledInProduct || $enabledInCart;

        if ($shouldBuildIt) {
            $ajaxRequests = new PayPalAjaxRequests($gateway, $notice, $logger);
            $data = new DataToPayPal($pluginUrl);
            $payPalHandler = new PayPalButtonHandler($ajaxRequests, $data);
            $payPalHandler->bootstrap($enabledInProduct, $enabledInCart);
        }
    }

    public function instantiatePaymentMethodGateways(ContainerInterface $container): array
    {
        $logger = $container->get(Logger::class);
        assert($logger instanceof Logger);
        $notice = $container->get(AdminNotice::class);
        assert($notice instanceof AdminNotice);
        $paymentService = $container->get(PaymentService::class);
        assert($paymentService instanceof PaymentService);
        $mollieOrderService = $container->get(MollieOrderService::class);
        assert($mollieOrderService instanceof MollieOrderService);
        $HttpResponseService = $container->get('SDK.HttpResponse');
        assert($HttpResponseService instanceof HttpResponse);
        $settingsHelper = $container->get('settings.settings_helper');
        assert($settingsHelper instanceof Settings);
        $apiHelper = $container->get('SDK.api_helper');
        assert($apiHelper instanceof Api);
        $paymentMethods = $container->get('gateway.paymentMethods');
        $data = $container->get('settings.data_helper');
        assert($data instanceof Data);
        $orderInstructionsService = new OrderInstructionsService();
        $mollieObject = $container->get(MollieObject::class);
        assert($mollieObject instanceof MollieObject);
        $paymentFactory = $container->get(PaymentFactory::class);
        assert($paymentFactory instanceof PaymentFactory);
        $pluginId = $container->get('shared.plugin_id');
        $gateways = [];
        if (empty($paymentMethods)) {
            return $gateways;
        }

        foreach ($paymentMethods as $paymentMethod) {
            $paymentMethodId = $paymentMethod->getIdFromConfig();
            if (! in_array($paymentMethodId, $container->get('gateway.paymentMethodsEnabledAtMollie'))) {
                continue;
            }
            $isSepa = $paymentMethod->getProperty('SEPA');
            $key = 'mollie_wc_gateway_' . $paymentMethodId;
            if ($isSepa) {
                $directDebit = $paymentMethods['directdebit'];
                $gateways[$key] = new MollieSepaRecurringGateway(
                    $directDebit,
                    $paymentMethod,
                    $paymentService,
                    $orderInstructionsService,
                    $mollieOrderService,
                    $data,
                    $logger,
                    $notice,
                    $HttpResponseService,
                    $settingsHelper,
                    $mollieObject,
                    $paymentFactory,
                    $pluginId,
                    $apiHelper
                );
            } elseif ($paymentMethod->getProperty('Subscription')) {
                $gateways[$key] = new MollieSubscriptionGateway(
                    $paymentMethod,
                    $paymentService,
                    $orderInstructionsService,
                    $mollieOrderService,
                    $data,
                    $logger,
                    $notice,
                    $HttpResponseService,
                    $settingsHelper,
                    $mollieObject,
                    $paymentFactory,
                    $pluginId,
                    $apiHelper
                );
            } else {
                $gateways[$key] = new MolliePaymentGateway(
                    $paymentMethod,
                    $paymentService,
                    $orderInstructionsService,
                    $mollieOrderService,
                    $data,
                    $logger,
                    $notice,
                    $HttpResponseService,
                    $mollieObject,
                    $paymentFactory,
                    $pluginId
                );
            }
        }
        return $gateways;
    }

    /**
     * @param $container
     * @return array
     */
    protected function instantiatePaymentMethods($container): array
    {
        $paymentMethods = [];
        $listAllAvailabePaymentMethods = $container->get('gateway.listAllMethodsAvailable');
        $iconFactory = $container->get(IconFactory::class);
        assert($iconFactory instanceof IconFactory);
        $settingsHelper = $container->get('settings.settings_helper');
        assert($settingsHelper instanceof Settings);
        $surchargeService = $container->get(Surcharge::class);
        assert($surchargeService instanceof Surcharge);
        $paymentFieldsService = $container->get(PaymentFieldsService::class);
        assert($paymentFieldsService instanceof PaymentFieldsService);
        foreach ($listAllAvailabePaymentMethods as $paymentMethodAvailable) {
            $paymentMethodId = $paymentMethodAvailable['id'];
            $paymentMethods[$paymentMethodId] = $this->buildPaymentMethod(
                $paymentMethodId,
                $iconFactory,
                $settingsHelper,
                $paymentFieldsService,
                $surchargeService,
                $paymentMethods
            );
        }

        //I need DirectDebit to create SEPA gateway
        if (!in_array('directdebit', array_keys($paymentMethods), true)) {
            $paymentMethodId = 'directdebit';
            $paymentMethods[$paymentMethodId] = $this->buildPaymentMethod(
                $paymentMethodId,
                $iconFactory,
                $settingsHelper,
                $paymentFieldsService,
                $surchargeService,
                $paymentMethods
            );
        }
        return $paymentMethods;
    }

    public function organizationBillingFieldMandatory($fields, $errors)
    {
        $billiePaymentMethod = "mollie_wc_gateway_billie";
        if ($fields['payment_method'] === $billiePaymentMethod) {
            if (!isset($fields['billing_company'])) {
                $companyFieldPosted = filter_input(INPUT_POST, 'billing_company', FILTER_SANITIZE_SPECIAL_CHARS) ?? false;
                if ($companyFieldPosted) {
                    $fields['billing_company'] = $companyFieldPosted;
                } else {
                    $errors->add(
                        'validation',
                        __(
                            'Error processing Billie payment, the company name field is required.',
                            'mollie-payments-for-woocommerce'
                        )
                    );
                }
            }
            if ($fields['billing_company'] === '') {
                $errors->add(
                    'validation',
                    __(
                        'Please enter your company name, this is required for Billie payments',
                        'mollie-payments-for-woocommerce'
                    )
                );
            }
        }

        return $fields;
    }

    /**
     * @param string $id
     * @param IconFactory $iconFactory
     * @param Settings $settingsHelper
     * @param PaymentFieldsService $paymentFieldsService
     * @param Surcharge $surchargeService
     * @param array $paymentMethods
     * @return PaymentMethodI
     */
    public function buildPaymentMethod(
        string $id,
        IconFactory $iconFactory,
        Settings $settingsHelper,
        PaymentFieldsService $paymentFieldsService,
        Surcharge $surchargeService,
        array $paymentMethods
    ): PaymentMethodI {
        $paymentMethodClassName = 'Mollie\\WooCommerce\\PaymentMethods\\' . ucfirst($id);
        $paymentMethod = new $paymentMethodClassName(
            $iconFactory,
            $settingsHelper,
            $paymentFieldsService,
            $surchargeService
        );

        return $paymentMethod;
    }
}

<?php

# -*- coding: utf-8 -*-

declare(strict_types=1);

namespace Mollie\WooCommerce\Gateway;

use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;
use Automattic\WooCommerce\StoreApi\Exceptions\RouteException;
use DateTime;
use Mollie\WooCommerce\Vendor\Inpsyde\Modularity\Module\ExecutableModule;
use Mollie\WooCommerce\Vendor\Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use Mollie\WooCommerce\Vendor\Inpsyde\Modularity\Module\ServiceModule;
use Mollie\WooCommerce\BlockService\CheckoutBlockService;
use Mollie\WooCommerce\Buttons\ApplePayButton\AppleAjaxRequests;
use Mollie\WooCommerce\Buttons\ApplePayButton\ApplePayDirectHandler;
use Mollie\WooCommerce\Buttons\ApplePayButton\ResponsesToApple;
use Mollie\WooCommerce\Buttons\PayPalButton\DataToPayPal;
use Mollie\WooCommerce\Buttons\PayPalButton\PayPalAjaxRequests;
use Mollie\WooCommerce\Buttons\PayPalButton\PayPalButtonHandler;
use Mollie\WooCommerce\Gateway\Voucher\MaybeDisableGateway;
use Mollie\WooCommerce\Notice\AdminNotice;
use Mollie\WooCommerce\Notice\FrontendNotice;
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
use Mollie\WooCommerce\PaymentMethods\Constants;
use Mollie\WooCommerce\Vendor\Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface as Logger;
use WP_Post;

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

    const FIELD_IN3_BIRTHDATE = 'billing_birthdate';
    const GATEWAY_NAME_IN3 = "mollie_wc_gateway_in3";

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
            'gateway.getPaymentMethodsAfterFeatureFlag' => static function (ContainerInterface $container): array {
                $availablePaymentMethods = $container->get('gateway.listAllMethodsAvailable');
                $klarnaOneFlag = apply_filters('inpsyde.feature-flags.mollie-woocommerce.klarna_one_enabled', true);
                if (!$klarnaOneFlag) {
                    return array_filter($availablePaymentMethods, static function ($method) {
                        return $method['id'] !== Constants::KLARNA;
                    });
                }
                $bancomatpayFlag = apply_filters('inpsyde.feature-flags.mollie-woocommerce.bancomatpay_enabled', true);
                if (!$bancomatpayFlag) {
                    return array_filter($availablePaymentMethods, static function ($method) {
                        return $method['id'] !== Constants::BANCOMATPAY;
                    });
                }
                return $availablePaymentMethods;
            },
            'gateway.isSDDGatewayEnabled' => static function (ContainerInterface $container): bool {
                $enabledMethods = $container->get('gateway.paymentMethodsEnabledAtMollie');
                return in_array(Constants::DIRECTDEBIT, $enabledMethods, true);
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
                [$this, 'BillieFieldsMandatory'],
                11,
                2
            );
        }
        $isIn3Enabled = mollieWooCommerceIsGatewayEnabled('mollie_wc_gateway_in3_settings', 'enabled');
        if ($isIn3Enabled) {
            add_filter(
                'woocommerce_after_checkout_validation',
                [$this, 'in3FieldsMandatory'],
                11,
                2
            );
            add_action(
                'woocommerce_before_pay_action',
                [$this, 'in3FieldsMandatoryPayForOrder'],
                11
            );
            add_action(
                'woocommerce_checkout_posted_data',
                [$this, 'switchFields'],
                11
            );
            add_action('woocommerce_rest_checkout_process_payment_with_context', [$this, 'addPhoneWhenRest'], 11);
            add_action('woocommerce_rest_checkout_process_payment_with_context', [$this, 'addBirthdateWhenRest'], 11);
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
        add_action('add_meta_boxes_woocommerce_page_wc-orders', [$this, 'addShopOrderMetabox'], 10);
        add_filter('woocommerce_checkout_fields', static function ($fields) use ($container) {
            if (!isset($fields['billing']['billing_phone'])) {
                update_option('mollie_wc_is_phone_required_flag', false);
            } else {
                update_option('mollie_wc_is_phone_required_flag', true);
            }
            return $fields;
        }, 10, 3);
        return true;
    }

    /**
     * @param Object $post
     * @return void
     */
    public function addShopOrderMetabox(object $post)
    {
        if (! $post instanceof \WC_Order) {
            return;
        }
        $meta = $post->get_meta('_mollie_payment_instructions');
        if (empty($meta)) {
            return;
        }
        $screen = wc_get_container()->get(CustomOrdersTableController::class)->custom_orders_table_usage_is_enabled()
            ? wc_get_page_screen_id('shop-order')
            : 'shop_order';
        add_meta_box('mollie_order_details', __('Mollie Payment Details', 'mollie-payments-for-woocommerce'), static function () use ($meta) {
            $allowedTags = ['strong' => []];
            printf(
                '<p style="border-bottom:solid 1px #eee;padding-bottom:13px;">%s</p>',
                wp_kses($meta, $allowedTags)
            );
        }, $screen, 'side', 'high');
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
        //If the setting is active is forced Payment API so we need to filter the gateway when order is in pay-page
        // as it might have been created with Orders API
        $isActiveExpiryDate = $bankTransferSettings
            && isset($bankTransferSettings['activate_expiry_days_setting'])
            && $bankTransferSettings['activate_expiry_days_setting'] === "yes"
            && isset($bankTransferSettings['order_dueDate'])
            && $bankTransferSettings['order_dueDate'] > 0;

        /*
         * There is only one case where we want to filter the gateway and it's when the
         * pay-page render the available payments methods AND the setting is enabled
         *
         * For any other case we want to be sure bank transfer gateway is included.
         */
        if (
            $isWcApiRequest ||
            !$isActiveExpiryDate ||
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
        $notice = $container->get(FrontendNotice::class);
        assert($notice instanceof FrontendNotice);
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
                $directDebit = $paymentMethods[Constants::DIRECTDEBIT];
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
        $listAllAvailablePaymentMethods = $container->get('gateway.getPaymentMethodsAfterFeatureFlag');
        $iconFactory = $container->get(IconFactory::class);
        assert($iconFactory instanceof IconFactory);
        $settingsHelper = $container->get('settings.settings_helper');
        assert($settingsHelper instanceof Settings);
        $surchargeService = $container->get(Surcharge::class);
        assert($surchargeService instanceof Surcharge);
        $paymentFieldsService = $container->get(PaymentFieldsService::class);
        assert($paymentFieldsService instanceof PaymentFieldsService);
        foreach ($listAllAvailablePaymentMethods as $paymentMethodAvailable) {
            $paymentMethodId = $paymentMethodAvailable['id'];
            $paymentMethods[$paymentMethodId] = $this->buildPaymentMethod(
                $paymentMethodId,
                $iconFactory,
                $settingsHelper,
                $paymentFieldsService,
                $surchargeService,
                $paymentMethodAvailable
            );
        }

        //I need DirectDebit to create SEPA gateway
        if (!in_array(Constants::DIRECTDEBIT, array_keys($paymentMethods), true)) {
            $paymentMethodId = Constants::DIRECTDEBIT;
            $paymentMethods[$paymentMethodId] = $this->buildPaymentMethod(
                $paymentMethodId,
                $iconFactory,
                $settingsHelper,
                $paymentFieldsService,
                $surchargeService,
                []
            );
        }
        return $paymentMethods;
    }

    public function BillieFieldsMandatory($fields, $errors)
    {
        $gatewayName = "mollie_wc_gateway_billie";
        $field = 'billing_company';
        $companyLabel = __('Company', 'mollie-payments-for-woocommerce');
        return $this->addPaymentMethodMandatoryFields($fields, $gatewayName, $field, $companyLabel, $errors);
    }

    public function in3FieldsMandatory($fields, $errors)
    {
        $gatewayName = "mollie_wc_gateway_in3";
        $phoneField = 'billing_phone_in3';
        $phoneLabel = __('Phone', 'mollie-payments-for-woocommerce');
        return $this->addPaymentMethodMandatoryFieldsPhoneVerification($fields, $gatewayName, $phoneField, $phoneLabel, $errors);
    }

    /**
     * @param $order
     */
    public function in3FieldsMandatoryPayForOrder($order)
    {
        $paymentMethod = filter_input(INPUT_POST, 'payment_method', FILTER_SANITIZE_SPECIAL_CHARS) ?? false;

        if ($paymentMethod !== self::GATEWAY_NAME_IN3) {
            return;
        }

        $phoneValue = filter_input(INPUT_POST, 'billing_phone_in3', FILTER_SANITIZE_SPECIAL_CHARS) ?? false;
        $phoneValue = transformPhoneToNLFormat($phoneValue);
        $phoneValid = $phoneValue && $this->isPhoneValid($phoneValue) ? $phoneValue : null;

        if ($phoneValid) {
            $order->set_billing_phone($phoneValue);
        }
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
        array $apiMethod
    ): PaymentMethodI {

        $paymentMethodClassName = 'Mollie\\WooCommerce\\PaymentMethods\\' . ucfirst($id);
        $paymentMethod = new $paymentMethodClassName(
            $iconFactory,
            $settingsHelper,
            $paymentFieldsService,
            $surchargeService,
            $apiMethod
        );

        return $paymentMethod;
    }

    /**
     * Some payment methods require mandatory fields, this function will add them to the checkout fields array
     * @param $fields
     * @param string $gatewayName
     * @param string $field
     * @param $errors
     * @return mixed
     */
    public function addPaymentMethodMandatoryFields($fields, string $gatewayName, string $field, string $fieldLabel, $errors)
    {
        if ($fields['payment_method'] !== $gatewayName) {
            return $fields;
        }
        if (!isset($fields[$field])) {
            $fieldPosted = filter_input(INPUT_POST, $field, FILTER_SANITIZE_SPECIAL_CHARS) ?? false;
            if ($fieldPosted) {
                $fields[$field] = $fieldPosted;
            } else {
                $errors->add(
                    'validation',
                    sprintf(
                        __('%s is a required field.', 'woocommerce'),
                        "<strong>$fieldLabel</strong>"
                    )
                );
            }
        }

        return $fields;
    }

    public function addPaymentMethodMandatoryFieldsPhoneVerification(
        $fields,
        string $gatewayName,
        string $field,
        string $fieldLabel,
        $errors
    ) {
        if ($fields['payment_method'] !== $gatewayName) {
            return $fields;
        }
        if (!empty($fields['billing_phone']) && $this->isPhoneValid($fields['billing_phone'])) {
            return $fields;
        }
        if (!empty($fields['billing_phone']) && !$this->isPhoneValid($fields['billing_phone'])) {
            $fields['billing_phone'] = null;
            return $fields;
        }
        $fieldPosted = filter_input(INPUT_POST, $field, FILTER_SANITIZE_SPECIAL_CHARS) ?? false;

        if ($fieldPosted && !$this->isPhoneValid($fieldPosted)) {
            $fields['billing_phone'] = $fieldPosted;
            return $fields;
        }
        $fields['billing_phone'] = null;
        return $fields;
    }

    public function switchFields($data)
    {
        if (isset($data['payment_method']) && $data['payment_method'] === 'mollie_wc_gateway_in3') {
            $fieldPosted = filter_input(INPUT_POST, 'billing_phone_in3', FILTER_SANITIZE_SPECIAL_CHARS) ?? false;
            if ($fieldPosted) {
                $data['billing_phone'] = !empty($fieldPosted) ? $fieldPosted : $data['billing_phone'];
            }
        }
        if (isset($data['payment_method']) && $data['payment_method'] === 'mollie_wc_gateway_billie') {
            $fieldPosted = filter_input(INPUT_POST, 'billing_company_billie', FILTER_SANITIZE_SPECIAL_CHARS) ?? false;
            if ($fieldPosted) {
                $data['billing_company'] = !empty($fieldPosted) ? $fieldPosted : $data['billing_company'];
            }
        }
        return $data;
    }

    private function isPhoneValid($billing_phone)
    {
        return preg_match('/^\+[1-9]\d{10,13}$|^[1-9]\d{9,13}$|^06\d{9,13}$/', $billing_phone);
    }

    private function isBirthValid($billing_birthdate)
    {
        $today = new DateTime();
        $birthdate = DateTime::createFromFormat('Y-m-d', $billing_birthdate);
        if ($birthdate >= $today) {
            return false;
        }
        return true;
    }

    public function addPhoneWhenRest($arrayContext)
    {
        $context = $arrayContext;
        $phoneMandatoryGateways = ['mollie_wc_gateway_in3'];
        $paymentMethod = $context->payment_data['payment_method'];
        if (in_array($paymentMethod, $phoneMandatoryGateways)) {
            $billingPhone = $context->order->get_billing_phone();
            if (!empty($billingPhone) && $this->isPhoneValid($billingPhone)) {
                return;
            }
            if (!empty($billingPhone) && !$this->isPhoneValid($billingPhone)) {
                $context->order->set_billing_phone(null);
                $context->order->save();
                return;
            }
            $billingPhone = $context->payment_data['billing_phone'];
            if ($billingPhone && $this->isPhoneValid($billingPhone)) {
                $context->order->set_billing_phone($billingPhone);
                $context->order->save();
            }
        }
    }

    public function addBirthdateWhenRest($arrayContext)
    {
        $context = $arrayContext;
        $birthMandatoryGateways = ['mollie_wc_gateway_in3'];
        $paymentMethod = $context->payment_data['payment_method'];
        if (in_array($paymentMethod, $birthMandatoryGateways)) {
            $billingBirthdate = $context->payment_data['billing_birthdate'];
            if ($billingBirthdate && $this->isBirthValid($billingBirthdate)) {
                $context->order->update_meta_data('billing_birthdate', $billingBirthdate);
                $context->order->save();
            } else {
                $message = __('Please introduce a valid birthdate number.', 'mollie-payments-for-woocommerce');
                throw new RouteException(
                    'woocommerce_rest_checkout_process_payment_error',
                    $message,
                    402
                );
            }
        }
    }
}

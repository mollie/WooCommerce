<?php

# -*- coding: utf-8 -*-

declare(strict_types=1);

namespace Mollie\WooCommerce\Gateway;

use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;
use Automattic\WooCommerce\StoreApi\Exceptions\RouteException;
use Inpsyde\Modularity\Module\ExecutableModule;
use Inpsyde\Modularity\Module\ExtendingModule;
use Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use Inpsyde\Modularity\Module\ServiceModule;
use Inpsyde\PaymentGateway\PaymentMethodServiceProviderTrait;
use Mollie\WooCommerce\BlockService\CheckoutBlockService;
use Mollie\WooCommerce\Buttons\ApplePayButton\ApplePayDirectHandler;
use Mollie\WooCommerce\Buttons\PayPalButton\PayPalButtonHandler;
use Mollie\WooCommerce\Gateway\Voucher\MaybeDisableGateway;
use Mollie\WooCommerce\PaymentMethods\IconFactory;
use Mollie\WooCommerce\PaymentMethods\PaymentMethodI;
use Mollie\WooCommerce\Settings\Settings;
use Mollie\WooCommerce\Shared\Data;
use Mollie\WooCommerce\Shared\GatewaySurchargeHandler;
use Mollie\WooCommerce\PaymentMethods\Constants;
use Mollie\WooCommerce\Shared\SharedDataDictionary;
use Psr\Container\ContainerInterface;

class GatewayModule implements ServiceModule, ExecutableModule, ExtendingModule
{
    use ModuleClassNameIdTrait;
    use PaymentMethodServiceProviderTrait;

    public const APPLE_PAY_METHOD_ALLOWED_KEY = 'mollie_apple_pay_method_allowed';

    /**
     * @var mixed
     */
    protected $gatewayClassnames;
    /**
     * @var mixed
     */
    protected $pluginId;

    public function run(ContainerInterface $container): bool
    {
        $this->pluginId = $container->get('shared.plugin_id');
        $this->gatewayClassnames = $container->get('gateway.classnames');
        add_filter($this->pluginId . '_retrieve_payment_gateways', function () {
            return $this->gatewayClassnames;
        });
        add_filter('woocommerce_payment_gateways', static function ($gateways) use ($container) {
            $orderMandatoryGatewayDisabler = $container->get(OrderMandatoryGatewayDisabler::class);
            assert($orderMandatoryGatewayDisabler instanceof OrderMandatoryGatewayDisabler);
            return $orderMandatoryGatewayDisabler->processGateways($gateways);
        });
        add_filter('woocommerce_payment_gateways', static function ($gateways) {
            $maybeEnablegatewayHelper = new MaybeDisableGateway();
            $gateways = $maybeEnablegatewayHelper->maybeDisableBankTransferGateway($gateways);
            return $maybeEnablegatewayHelper->maybeDisableMealVoucherGateway($gateways);
        });

        add_filter(
            'woocommerce_payment_gateways',
            static function ($gateways) use ($container) {
                $deprecatedGatewayHelpers = $container->get('__deprecated.gateway_helpers');
                foreach ($gateways as $gateway) {
                    $isMolliegateway = is_string($gateway) && strpos($gateway, 'mollie_wc_gateway_') !== false
                    || is_object($gateway) && strpos($gateway->id, 'mollie_wc_gateway_') !== false;
                    if (!$isMolliegateway) {
                        continue;
                    }
                    assert($gateway instanceof \Inpsyde\PaymentGateway\PaymentGateway);
                    // Add subscription filters after payment gateways are loaded
                    $isSubscriptiongateway = $gateway->supports('subscriptions');
                    if ($isSubscriptiongateway && method_exists($deprecatedGatewayHelpers[$gateway->id], 'addSubscriptionFilters')) {
                        $deprecatedGatewayHelpers[$gateway->id]->addSubscriptionFilters($gateway);
                    }

                    // Add payment instructions
                    $displayInstructionsService = $container->get('gateway.hooks.displayInstructions');
                    $displayInstructionsService($gateway);
                    // Add thankyou page actions for gateway
                    $thankyouPageService = $container->get('gateway.hooks.thankyouPage');
                    if (!has_action('woocommerce_thankyou_' . $gateway->id)) {
                        $thankyouPageService($gateway);
                    }
                    // Add subscription payment hooks
                    $isSubscriptionPaymentService = $container->get('gateway.hooks.isSubscriptionPayment');
                    $isSubscriptionPaymentService($gateway);
                }
                return $gateways;
            },
            30
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
        $isIn3Enabled = mollieWooCommerceIsGatewayEnabled('mollie_wc_gateway_in3_settings', 'enabled');
        $isRivertyEnabled = mollieWooCommerceIsGatewayEnabled('mollie_wc_gateway_riverty_settings', 'enabled');
        if ($isIn3Enabled) {
            add_filter(
                'woocommerce_after_checkout_validation',
                [$this, 'in3FieldsMandatory'],
                11,
                2
            );
        }
        if ($isIn3Enabled || $isRivertyEnabled) {
            add_action(
                'woocommerce_before_pay_action',
                [$this, 'fieldsMandatoryPayForOrder'],
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

        $surchargeService = $container->get(Surcharge::class);
        assert($surchargeService instanceof Surcharge);
        $this->gatewaySurchargeHandling($surchargeService);

        $this->paymentButtonsBootstrap($container);

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

        add_action('init', static function () use ($container) {
            $paymentMethods = $container->get('gateway.paymentMethods');
            foreach ($paymentMethods as $paymentMethod) {
                assert($paymentMethod instanceof PaymentMethodI);
                $paymentMethod->initializeTranslations();
                $paymentMethod->updateSettingsWithDefaults($container);
            }
        });
        add_filter('woocommerce_get_transaction_url', static function ($return_url, $order, \WC_Payment_Gateway $wcGateway) {
            if ($return_url || strpos($wcGateway->id, 'mollie_wc_gateway_') === false) {
                return $return_url;
            }
            $transactionId = $order->get_transaction_id();
            $isPaymentApi = substr($transactionId, 0, 3) === 'tr_'  ;
            $resource = ($transactionId && !$isPaymentApi) ? 'orders' : 'payments';
            return 'https://my.mollie.com/dashboard/' . $resource . '/' . trim($transactionId) . '?utm_source=woocommerce&utm_medium=plugin&utm_campaign=partner';
        }, 10, 3);
        return true;
    }

    public function services(): array
    {
        static $services;

        if ($services === null) {
            $services = require_once __DIR__ . '/inc/services.php';
        }

        return $services();
    }

    /**
     * @inheritDoc
     */
    public function extensions(): array
    {
        static $extensions;

        if ($extensions === null) {
            $extensions = require_once __DIR__ . '/inc/extensions.php';
        }

        return $extensions();
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

        $mollie_payment_id = $order->get_meta('_mollie_payment_id', true);
        $order_payment_method = $order->get_payment_method();

        if ($mollie_payment_id !== '' && (strpos($order_payment_method, 'mollie') === false)) {
            $order->update_meta_data('_mollie_paid_by_other_gateway', '1');
            $order->save();
        }
        return true;
    }

    /**
     * @param ContainerInterface $container
     * @return void
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function paymentButtonsBootstrap(ContainerInterface $container): void
    {
        $applePayDirectHandler = $container->get(ApplePayDirectHandler::class);
        if ($applePayDirectHandler instanceof ApplePayDirectHandler) {
            $buttonEnabledCart = mollieWooCommerceIsApplePayDirectEnabled('cart');
            $buttonEnabledProduct = mollieWooCommerceIsApplePayDirectEnabled('product');
            if ($buttonEnabledCart || $buttonEnabledProduct) {
                $applePayDirectHandler->bootstrap($buttonEnabledProduct, $buttonEnabledCart);
            }
        }

        $paypalButtonHandler = $container->get(PayPalButtonHandler::class);
        if ($paypalButtonHandler instanceof PayPalButtonHandler) {
            $enabledInProduct = (mollieWooCommerceIsPayPalButtonEnabled('product'));
            $enabledInCart = (mollieWooCommerceIsPayPalButtonEnabled('cart'));
            $shouldBuildIt = $enabledInProduct || $enabledInCart;

            if ($shouldBuildIt) {
                $paypalButtonHandler->bootstrap($enabledInProduct, $enabledInCart);
            }
        }
    }

    /**
     * This instantiates all payment methods that we have implemented
     * disregards if they are available at Mollie or not
     *
     * @return array
     */
    protected function instantiatePaymentMethods(): array
    {
        $paymentMethods = [];
        $allGatewayClassNames = SharedDataDictionary::GATEWAY_CLASSNAMES;
        foreach ($allGatewayClassNames as $gatewayClassName) {
            $parts = explode('_', $gatewayClassName);
            $methodId = strtolower(end($parts));
            $paymentMethods[$methodId] = $this->buildPaymentMethod(
                $methodId
            );
        }

        //I need DirectDebit to create SEPA gateway
        if (!in_array(Constants::DIRECTDEBIT, array_keys($paymentMethods), true)) {
            $methodId = Constants::DIRECTDEBIT;
            $paymentMethods[$methodId] = $this->buildPaymentMethod(
                $methodId
            );
        }
        return $paymentMethods;
    }
    /**
     * @param string $id
     * @param IconFactory $iconFactory
     * @param Settings $settingsHelper
     * @param Surcharge $surchargeService
     * @param array $paymentMethods
     * @return PaymentMethodI | array
     */
    public function buildPaymentMethod(
        string $id
    ) {

        $transformedId = ucfirst($id);
        $paymentMethodClassName = 'Mollie\\WooCommerce\\PaymentMethods\\' . $transformedId;

        return new $paymentMethodClassName();
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
    public function fieldsMandatoryPayForOrder($order)
    {
        $paymentMethod = filter_input(INPUT_POST, 'payment_method', FILTER_SANITIZE_SPECIAL_CHARS) ?? false;
        $methods = [
            Constants::RIVERTY,
            Constants::IN3,
        ];
        foreach ($methods as $method) {
            if ($paymentMethod !== $method) {
                continue;
            }
          
            $phoneValue = filter_input(INPUT_POST, 'billing_phone_' . $method, FILTER_SANITIZE_SPECIAL_CHARS) ?? false;
            $phoneValid = $phoneValue && $this->isPhoneValid($phoneValue) ? $phoneValue : null;

            if ($phoneValid) {
                $order->set_billing_phone($phoneValue);
            }
        }
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
                    /* translators: Placeholder 1: field name. */
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
        $methods = [
            Constants::RIVERTY,
            Constants::IN3,
        ];
        foreach ($methods as $method) {
            if (isset($data['payment_method']) && $data['payment_method'] === 'mollie_wc_gateway_' . $method) {
                $fieldPosted = filter_input(INPUT_POST, 'billing_phone_' . $method, FILTER_SANITIZE_SPECIAL_CHARS) ?? false;
                if ($fieldPosted) {
                    $data['billing_phone'] = !empty($fieldPosted) ? $fieldPosted : $data['billing_phone'];
                }
            }
        }

        return $data;
    }

    private function isPhoneValid($billing_phone)
    {
        return preg_match('/^\+[1-9]\d{10,13}$|^[1-9]\d{9,13}$|^06\d{9,13}$/', $billing_phone);
    }

    private function isBirthValid($billing_birthdate): bool
    {
        return isMollieBirthValid($billing_birthdate);
    }

    public function addPhoneWhenRest($arrayContext)
    {
        $context = $arrayContext;
        $phoneMandatoryGateways = ['mollie_wc_gateway_in3', 'mollie_wc_gateway_riverty'];
        $paymentMethod = $context->payment_data['payment_method'] ?? null;
        if ($paymentMethod && in_array($paymentMethod, $phoneMandatoryGateways)) {
            $billingPhone = $context->order->get_billing_phone();
            if (!empty($billingPhone) && $this->isPhoneValid($billingPhone)) {
                return;
            }
            if (!empty($billingPhone) && !$this->isPhoneValid($billingPhone)) {
                $context->order->set_billing_phone(null);
                $context->order->save();
                return;
            }
            $billingPhone = $context->payment_data['billing_phone'] ?? null;
            if ($billingPhone && $this->isPhoneValid($billingPhone)) {
                $context->order->set_billing_phone($billingPhone);
                $context->order->save();
            }
        }
    }

    public function addBirthdateWhenRest($arrayContext)
    {
        $context = $arrayContext;
        $birthMandatoryGateways = ['mollie_wc_gateway_in3', 'mollie_wc_gateway_riverty'];
        $paymentMethod = $context->payment_data['payment_method'] ?? null;
        if ($paymentMethod && in_array($paymentMethod, $birthMandatoryGateways)) {
            $billingBirthdate = $context->payment_data['billing_birthdate'] ?? null;
            if ($billingBirthdate && $this->isBirthValid($billingBirthdate)) {
                $context->order->update_meta_data('billing_birthdate', $billingBirthdate);
                $context->order->save();
            } else {
                throw new RouteException(
                    'woocommerce_rest_checkout_process_payment_error',
                    esc_html__('Please introduce a valid birthdate number.', 'mollie-payments-for-woocommerce'),
                    402
                );
            }
        }
    }
}

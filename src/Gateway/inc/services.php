<?php

declare (strict_types=1);
namespace Mollie;

use Mollie\Inpsyde\PaymentGateway\PaymentGateway;
use Mollie\Inpsyde\PaymentGateway\RefundProcessorInterface;
use Mollie\WooCommerce\Buttons\ApplePayButton\AppleAjaxRequests;
use Mollie\WooCommerce\Buttons\ApplePayButton\ApplePayDirectHandler;
use Mollie\WooCommerce\Buttons\ApplePayButton\ResponsesToApple;
use Mollie\WooCommerce\Buttons\PayPalButton\DataToPayPal;
use Mollie\WooCommerce\Buttons\PayPalButton\PayPalAjaxRequests;
use Mollie\WooCommerce\Buttons\PayPalButton\PayPalButtonHandler;
use Mollie\WooCommerce\Gateway\DeprecatedGatewayBuilder;
use Mollie\WooCommerce\Gateway\Refund\OrderItemsRefunder;
use Mollie\WooCommerce\Gateway\Refund\RefundLineItemsBuilder;
use Mollie\WooCommerce\Gateway\Refund\RefundProcessor;
use Mollie\WooCommerce\Gateway\Surcharge;
use Mollie\WooCommerce\Notice\AdminNotice;
use Mollie\WooCommerce\Payment\MollieOrderService;
use Mollie\WooCommerce\PaymentMethods\InstructionStrategies\OrderInstructionsManager;
use Mollie\WooCommerce\Payment\PaymentCheckoutRedirectService;
use Mollie\WooCommerce\Payment\PaymentFactory;
use Mollie\WooCommerce\Payment\PaymentProcessor;
use Mollie\WooCommerce\PaymentMethods\Constants;
use Mollie\WooCommerce\PaymentMethods\IconFactory;
use Mollie\WooCommerce\PaymentMethods\PaymentMethodI;
use Mollie\WooCommerce\SDK\Api;
use Mollie\WooCommerce\SDK\HttpResponse;
use Mollie\WooCommerce\Settings\Settings;
use Mollie\WooCommerce\Shared\Data;
use Mollie\WooCommerce\Shared\SharedDataDictionary;
use Mollie\Psr\Container\ContainerInterface;
use Mollie\Psr\Log\LoggerInterface as Logger;
return static function (): array {
    $services = ['gateway.classnames' => static function (): array {
        return SharedDataDictionary::GATEWAY_CLASSNAMES;
    }, '__deprecated.gateway_helpers' => static function (ContainerInterface $container): array {
        $oldGatewayBuilder = new DeprecatedGatewayBuilder();
        return $oldGatewayBuilder->instantiatePaymentMethodGateways($container);
    }, 'gateway.paymentMethods' => static function (ContainerInterface $container): array {
        $onlyAvailableMethods = $container->get('gateway.getPaymentMethodsAfterFeatureFlag');
        $allPaymentMethods = (new self())->instantiatePaymentMethods();
        //we want only the methods after the feature flags
        return \array_filter($allPaymentMethods, static function ($method, $key) use ($onlyAvailableMethods) {
            return \array_key_exists($key, $onlyAvailableMethods);
        }, \ARRAY_FILTER_USE_BOTH);
    }, 'gateway.paymentMethodsEnabledAtMollie' => static function (ContainerInterface $container): array {
        $dataHelper = $container->get('settings.data_helper');
        \assert($dataHelper instanceof Data);
        $settings = $container->get('settings.settings_helper');
        \assert($settings instanceof Settings);
        $apiKey = $settings->getApiKey();
        $methods = $apiKey ? $dataHelper->getAllPaymentMethods($apiKey) : [];
        $enabledMethods = [];
        foreach ($methods as $method) {
            $enabledMethods[] = $method['id'];
        }
        return $enabledMethods;
    }, 'gateway.listAllMethodsAvailable' => static function (ContainerInterface $container): array {
        $dataHelper = $container->get('settings.data_helper');
        \assert($dataHelper instanceof Data);
        $settings = $container->get('settings.settings_helper');
        \assert($settings instanceof Settings);
        $apiKey = $settings->getApiKey();
        $methods = $apiKey ? $dataHelper->getAllAvailablePaymentMethods() : [];
        $availableMethods = [];
        $implementedMethods = $container->get('gateway.classnames');
        foreach ($methods as $method) {
            if (\in_array('Mollie_WC_Gateway_' . \ucfirst($method['id']), $implementedMethods, \true)) {
                $availableMethods[$method['id']] = $method;
            }
        }
        return $availableMethods;
    }, 'gateway.getPaymentMethodsAfterFeatureFlag' => static function (ContainerInterface $container): array {
        $availablePaymentMethods = $container->get('gateway.listAllMethodsAvailable');
        $klarnaOneFlag = (bool) \apply_filters('inpsyde.feature-flags.mollie-woocommerce.klarna_one_enabled', \true);
        if (!$klarnaOneFlag) {
            $availablePaymentMethods = \array_filter($availablePaymentMethods, static function ($method) {
                return $method['id'] !== Constants::KLARNA;
            });
        }
        $bancomatpayFlag = (bool) \apply_filters('inpsyde.feature-flags.mollie-woocommerce.bancomatpay_enabled', \true);
        if (!$bancomatpayFlag) {
            $availablePaymentMethods = \array_filter($availablePaymentMethods, static function ($method) {
                return $method['id'] !== Constants::BANCOMATPAY;
            });
        }
        $almaFlag = (bool) \apply_filters('inpsyde.feature-flags.mollie-woocommerce.alma_enabled', \true);
        if (!$almaFlag) {
            $availablePaymentMethods = \array_filter($availablePaymentMethods, static function ($method) {
                return $method['id'] !== Constants::ALMA;
            });
        }
        $swishFlag = (bool) \apply_filters('inpsyde.feature-flags.mollie-woocommerce.swish_enabled', \true);
        if (!$swishFlag) {
            $availablePaymentMethods = \array_filter($availablePaymentMethods, static function ($method) {
                return $method['id'] !== Constants::SWISH;
            });
        }
        $vippsFlag = (bool) \apply_filters('inpsyde.feature-flags.mollie-woocommerce.vippsmobilepay_enabled', \true);
        if (!$vippsFlag) {
            $availablePaymentMethods = \array_filter($availablePaymentMethods, static function ($method) {
                return $method['id'] !== Constants::VIPPSMOBILEPAY;
            });
        }
        $bizumFlag = (bool) \apply_filters('inpsyde.feature-flags.mollie-woocommerce.bizum_enabled', \false);
        if (!$bizumFlag) {
            $availablePaymentMethods = \array_filter($availablePaymentMethods, static function ($method) {
                return $method['id'] !== Constants::BIZUM;
            });
        }
        return $availablePaymentMethods;
    }, IconFactory::class => static function (ContainerInterface $container): IconFactory {
        $pluginUrl = $container->get('shared.plugin_url');
        $pluginPath = $container->get('shared.plugin_path');
        return new IconFactory($pluginUrl, $pluginPath);
    }, RefundLineItemsBuilder::class => static function (ContainerInterface $container): RefundLineItemsBuilder {
        $data = $container->get('settings.data_helper');
        return new RefundLineItemsBuilder($data);
    }, OrderItemsRefunder::class => static function (ContainerInterface $container): OrderItemsRefunder {
        $data = $container->get('settings.data_helper');
        $refundLineItemsBuilder = $container->get(RefundLineItemsBuilder::class);
        $apiHelper = $container->get('SDK.api_helper');
        $apiKey = $container->get('settings.settings_helper')->getApiKey();
        $orderEndpoint = $apiHelper->getApiClient($apiKey)->orders;
        return new OrderItemsRefunder($refundLineItemsBuilder, $data, $orderEndpoint);
    }, PaymentProcessor::class => static function (ContainerInterface $container): PaymentProcessor {
        $logger = $container->get(Logger::class);
        \assert($logger instanceof Logger);
        $notice = $container->get(AdminNotice::class);
        \assert($notice instanceof AdminNotice);
        $paymentFactory = $container->get(PaymentFactory::class);
        \assert($paymentFactory instanceof PaymentFactory);
        $data = $container->get('settings.data_helper');
        \assert($data instanceof Data);
        $api = $container->get('SDK.api_helper');
        \assert($api instanceof Api);
        $settings = $container->get('settings.settings_helper');
        \assert($settings instanceof Settings);
        $pluginId = $container->get('shared.plugin_id');
        $paymentCheckoutRedirectService = $container->get(PaymentCheckoutRedirectService::class);
        \assert($paymentCheckoutRedirectService instanceof PaymentCheckoutRedirectService);
        $deprecatedGatewayInstances = $container->get('__deprecated.gateway_helpers');
        return new PaymentProcessor($notice, $logger, $paymentFactory, $data, $api, $settings, $pluginId, $paymentCheckoutRedirectService, $deprecatedGatewayInstances);
    }, OrderInstructionsManager::class => static function (): OrderInstructionsManager {
        return new OrderInstructionsManager();
    }, PaymentCheckoutRedirectService::class => static function (ContainerInterface $container): PaymentCheckoutRedirectService {
        $data = $container->get('settings.data_helper');
        \assert($data instanceof Data);
        return new PaymentCheckoutRedirectService($data);
    }, Surcharge::class => static function (ContainerInterface $container): Surcharge {
        return new Surcharge();
    }, MollieOrderService::class => static function (ContainerInterface $container): MollieOrderService {
        $HttpResponseService = $container->get('SDK.HttpResponse');
        \assert($HttpResponseService instanceof HttpResponse);
        $logger = $container->get(Logger::class);
        \assert($logger instanceof Logger);
        $paymentFactory = $container->get(PaymentFactory::class);
        \assert($paymentFactory instanceof PaymentFactory);
        $data = $container->get('settings.data_helper');
        \assert($data instanceof Data);
        $pluginId = $container->get('shared.plugin_id');
        return new MollieOrderService($HttpResponseService, $logger, $paymentFactory, $data, $pluginId, $container);
    }, ApplePayDirectHandler::class => static function (ContainerInterface $container) {
        $appleGateway = isset($container->get('__deprecated.gateway_helpers')['mollie_wc_gateway_applepay']) ? $container->get('__deprecated.gateway_helpers')['mollie_wc_gateway_applepay'] : \false;
        if (!$appleGateway) {
            return \false;
        }
        $notice = $container->get(AdminNotice::class);
        \assert($notice instanceof AdminNotice);
        $logger = $container->get(Logger::class);
        \assert($logger instanceof Logger);
        $apiHelper = $container->get('SDK.api_helper');
        \assert($apiHelper instanceof Api);
        $settingsHelper = $container->get('settings.settings_helper');
        \assert($settingsHelper instanceof Settings);
        $responseTemplates = new ResponsesToApple($logger, $appleGateway);
        $ajaxRequests = new AppleAjaxRequests($responseTemplates, $notice, $logger, $apiHelper, $settingsHelper);
        return new ApplePayDirectHandler($notice, $ajaxRequests);
    }, PayPalButtonHandler::class => static function (ContainerInterface $container) {
        $notice = $container->get(AdminNotice::class);
        \assert($notice instanceof AdminNotice);
        $logger = $container->get(Logger::class);
        \assert($logger instanceof Logger);
        $paymentGateways = $container->get('payment_gateways');
        if (!\in_array('mollie_wc_gateway_paypal', $paymentGateways)) {
            return \false;
        }
        $paypalGateway = new Inpsyde\PaymentGateway\PaymentGateway('mollie_wc_gateway_paypal', $container);
        $pluginUrl = $container->get('shared.plugin_url');
        $ajaxRequests = new PayPalAjaxRequests($paypalGateway, $notice, $logger);
        $data = new DataToPayPal($pluginUrl);
        return new PayPalButtonHandler($ajaxRequests, $data);
    }, 'payment_gateway.getRefundProcessor' => static function (ContainerInterface $container): callable {
        return static function (string $gatewayId) use ($container): RefundProcessorInterface {
            $oldGatewayInstances = $container->get('__deprecated.gateway_helpers');
            if (!isset($oldGatewayInstances['mollie_wc_gateway_' . $gatewayId])) {
                return $container->get('payment_gateways.noop_refund_processor');
            }
            $gateway = $oldGatewayInstances['mollie_wc_gateway_' . $gatewayId];
            return new RefundProcessor($gateway);
        };
    }, 'gateway.isBillieEnabled' => static function (ContainerInterface $container): bool {
        $settings = $container->get('settings.settings_helper');
        \assert($settings instanceof Settings);
        $isSettingsOrderApi = $settings->isOrderApiSetting();
        $billie = isset($container->get('gateway.paymentMethods')['billie']) ? $container->get('gateway.paymentMethods')['billie'] : null;
        $isBillieEnabled = \false;
        if ($billie instanceof PaymentMethodI) {
            $isBillieEnabled = $billie->getProperty('enabled') === 'yes';
        }
        return $isSettingsOrderApi && $isBillieEnabled;
    }, 'payment_request_validators' => static function (ContainerInterface $container): callable {
        return static function (string $gatewayId) use ($container): callable {
            //todo this is default
            return $container->get('payment_gateways.noop_payment_request_validator');
        };
    }, 'gateway.getMethodPropertyByGatewayId' => static function (ContainerInterface $container): callable {
        return static function (string $gatewayId, string $property) use ($container) {
            $paymentMethods = $container->get('gateway.paymentMethods');
            $methodId = \substr($gatewayId, \strrpos($gatewayId, '_') + 1);
            $paymentMethod = $paymentMethods[$methodId];
            return $paymentMethod->getProperty($property);
        };
    }, 'payment_gateway.getPaymentMethod' => static function (ContainerInterface $container): callable {
        return static function (string $gatewayId) use ($container): PaymentMethodI {
            $paymentMethods = $container->get('gateway.paymentMethods');
            $methodId = \substr($gatewayId, \strrpos($gatewayId, '_') + 1);
            return $paymentMethods[$methodId];
        };
    }, 'gateway.subscriptionsSupports' => static function (): array {
        return ['subscriptions', 'subscription_cancellation', 'subscription_suspension', 'subscription_reactivation', 'subscription_amount_changes', 'subscription_date_changes', 'multiple_subscriptions', 'subscription_payment_method_change', 'subscription_payment_method_change_admin', 'subscription_payment_method_change_customer'];
    }, 'gateway.hooks.thankyouPage' => static function (ContainerInterface $container) {
        return static function (PaymentGateway $paymentGateway) use ($container) {
            $instructionsManager = $container->get(OrderInstructionsManager::class);
            $oldGatewayInstances = $container->get('__deprecated.gateway_helpers');
            $gatewayId = $paymentGateway->id;
            $deprecatedGatewayHelper = $oldGatewayInstances[$gatewayId];
            \add_action('woocommerce_thankyou_' . $paymentGateway->id, static function ($order_id) use ($instructionsManager, $paymentGateway, $deprecatedGatewayHelper) {
                $order = \wc_get_order($order_id);
                // Order not found
                if (!$order) {
                    return;
                }
                // Empty cart
                if (\WC()->cart) {
                    \WC()->cart->empty_cart();
                }
                // Same as email instructions, just run that
                $instructionsManager->displayInstructions($paymentGateway, $deprecatedGatewayHelper, $order, \false, \false);
            });
        };
    }, 'gateway.hooks.displayInstructions' => static function (ContainerInterface $container) {
        return static function (PaymentGateway $paymentGateway) use ($container) {
            $instructionsManager = $container->get(OrderInstructionsManager::class);
            $oldGatewayInstances = $container->get('__deprecated.gateway_helpers');
            $gatewayId = $paymentGateway->id;
            $deprecatedGatewayHelper = $oldGatewayInstances[$gatewayId];
            \add_action('woocommerce_email_after_order_table', static function ($order, $sent_to_admin, $plain_text) use ($instructionsManager, $paymentGateway, $deprecatedGatewayHelper) {
                $instructionsManager->displayInstructions($paymentGateway, $deprecatedGatewayHelper, $order, $sent_to_admin, $plain_text);
            }, 10, 3);
            \add_action('woocommerce_email_order_meta', static function ($order, $sent_to_admin, $plain_text) use ($instructionsManager, $paymentGateway, $deprecatedGatewayHelper) {
                $instructionsManager->displayInstructions($paymentGateway, $deprecatedGatewayHelper, $order, $sent_to_admin, $plain_text);
            }, 10, 3);
        };
    }, 'gateway.hooks.isSubscriptionPayment' => static function (ContainerInterface $container) {
        return static function (PaymentGateway $paymentGateway) use ($container) {
            $pluginId = $container->get('shared.plugin_id');
            $dataHelper = $container->get('settings.data_helper');
            if ($paymentGateway->supports('subscriptions')) {
                \add_filter($pluginId . '_is_subscription_payment', static function ($isSubscription, $orderId) use ($pluginId, $dataHelper) {
                    if ($dataHelper->isWcSubscription($orderId)) {
                        \add_filter($pluginId . '_is_automatic_payment_disabled', static function ($filteredOption) {
                            if ('yes' == \get_option(\WC_Subscriptions_Admin::$option_prefix . '_turn_off_automatic_payments')) {
                                return \true;
                            }
                            return $filteredOption;
                        });
                        return \true;
                    }
                    return $isSubscription;
                }, 10, 2);
            }
        };
    }];
    $paymentMethods = (new self())->instantiatePaymentMethods();
    return \array_merge($services, (new self())->providePaymentMethodServices(...\array_values($paymentMethods)));
};

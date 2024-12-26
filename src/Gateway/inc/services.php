<?php

declare(strict_types=1);

use Dhii\Services\Factory;
use Inpsyde\PaymentGateway\PaymentRequestValidatorInterface;
use Inpsyde\PaymentGateway\RefundProcessorInterface;
use Mollie\WooCommerce\Buttons\ApplePayButton\AppleAjaxRequests;
use Mollie\WooCommerce\Buttons\ApplePayButton\ApplePayDirectHandler;
use Mollie\WooCommerce\Buttons\ApplePayButton\ResponsesToApple;
use Mollie\WooCommerce\Buttons\PayPalButton\DataToPayPal;
use Mollie\WooCommerce\Buttons\PayPalButton\PayPalAjaxRequests;
use Mollie\WooCommerce\Buttons\PayPalButton\PayPalButtonHandler;
use Mollie\WooCommerce\Gateway\DeprecatedGatewayBuilder;
use Mollie\WooCommerce\Gateway\OrderMandatoryGatewayDisabler;
use Mollie\WooCommerce\Gateway\Refund\OrderItemsRefunder;
use Mollie\WooCommerce\Gateway\Refund\RefundLineItemsBuilder;
use Mollie\WooCommerce\Gateway\Refund\RefundProcessor;
use Mollie\WooCommerce\Gateway\Surcharge;
use Mollie\WooCommerce\Notice\AdminNotice;
use Mollie\WooCommerce\Payment\MollieOrderService;
use Mollie\WooCommerce\Payment\OrderInstructionsManager;
use Mollie\WooCommerce\Payment\PaymentCheckoutRedirectService;
use Mollie\WooCommerce\Payment\PaymentFactory;
use Mollie\WooCommerce\PaymentMethods\PaymentFieldsStrategies\PaymentFieldsManager;
use Mollie\WooCommerce\Payment\PaymentProcessor;
use Mollie\WooCommerce\PaymentMethods\Constants;
use Mollie\WooCommerce\PaymentMethods\Icon\GatewayIconsRenderer;
use Mollie\WooCommerce\PaymentMethods\IconFactory;
use Mollie\WooCommerce\PaymentMethods\PaymentFieldsStrategies\NoopPaymentFieldsRenderer;
use Mollie\WooCommerce\PaymentMethods\PaymentFieldsStrategies\PaymentFieldsRenderer;
use Mollie\WooCommerce\PaymentMethods\PaymentMethodI;
use Mollie\WooCommerce\SDK\Api;
use Mollie\WooCommerce\SDK\HttpResponse;
use Mollie\WooCommerce\Settings\General\MultiCountrySettingsField;
use Mollie\WooCommerce\Settings\Settings;
use Mollie\WooCommerce\Shared\Data;
use Mollie\WooCommerce\Shared\SharedDataDictionary;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface as Logger;

return static function (): array {
    $services = [
        'gateway.classnames' => static function (): array {
            return SharedDataDictionary::GATEWAY_CLASSNAMES;
        },
        '__deprecated.gateway_helpers' => static function (ContainerInterface $container): array {
            $oldGatewayBuilder = new DeprecatedGatewayBuilder();
            return $oldGatewayBuilder->instantiatePaymentMethodGateways($container);
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
            $klarnaOneFlag = (bool) apply_filters('inpsyde.feature-flags.mollie-woocommerce.klarna_one_enabled', true);
            if (!$klarnaOneFlag) {
                $availablePaymentMethods = array_filter($availablePaymentMethods, static function ($method) {
                    return $method['id'] !== Constants::KLARNA;
                });
            }
            $bancomatpayFlag = (bool) apply_filters('inpsyde.feature-flags.mollie-woocommerce.bancomatpay_enabled', true);
            if (!$bancomatpayFlag) {
                $availablePaymentMethods = array_filter($availablePaymentMethods, static function ($method) {
                    return $method['id'] !== Constants::BANCOMATPAY;
                });
            }
            $almaFlag = (bool) apply_filters('inpsyde.feature-flags.mollie-woocommerce.alma_enabled', true);
            if (!$almaFlag) {
                $availablePaymentMethods = array_filter($availablePaymentMethods, static function ($method) {
                    return $method['id'] !== Constants::ALMA;
                });
            }
            $swishFlag = (bool) apply_filters('inpsyde.feature-flags.mollie-woocommerce.swish_enabled', false);
            if (!$swishFlag) {
                $availablePaymentMethods = array_filter($availablePaymentMethods, static function ($method) {
                    return $method['id'] !== Constants::SWISH;
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
        RefundLineItemsBuilder::class => static function (ContainerInterface $container): RefundLineItemsBuilder {
            $data = $container->get('settings.data_helper');
            return new RefundLineItemsBuilder($data);
        },
        OrderItemsRefunder::class => static function (ContainerInterface $container): OrderItemsRefunder {
            $data = $container->get('settings.data_helper');
            $refundLineItemsBuilder = $container->get(RefundLineItemsBuilder::class);
            $apiHelper = $container->get('SDK.api_helper');
            $apiKey = $container->get('settings.settings_helper')->getApiKey();
            $orderEndpoint = $apiHelper->getApiClient($apiKey)->orders;

            return new OrderItemsRefunder($refundLineItemsBuilder, $data, $orderEndpoint);
        },
        PaymentProcessor::class => static function (ContainerInterface $container): PaymentProcessor {
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
            return new PaymentProcessor($notice, $logger, $paymentFactory, $data, $api, $settings, $pluginId, $paymentCheckoutRedirectService, $voucherDefaultCategory);
        },
        OrderInstructionsManager::class => static function (): OrderInstructionsManager {
            return new OrderInstructionsManager();
        },
        PaymentFieldsManager::class => static function (ContainerInterface $container): PaymentFieldsManager {
            $data = $container->get('settings.data_helper');
            assert($data instanceof Data);
            return new PaymentFieldsManager($data);
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
            return new MollieOrderService($HttpResponseService, $logger, $paymentFactory, $data, $pluginId, $container);
        },
        OrderMandatoryGatewayDisabler::class => static function (ContainerInterface $container): OrderMandatoryGatewayDisabler {
            $settings = $container->get('settings.settings_helper');
            assert($settings instanceof Settings);
            $isSettingsOrderApi = $settings->isOrderApiSetting();
            $paymentMethods = $container->get('gateway.paymentMethods');
            return new OrderMandatoryGatewayDisabler($isSettingsOrderApi, $paymentMethods);
        },
        ApplePayDirectHandler::class => static function (ContainerInterface $container) {
            $appleGateway = isset($container->get('__deprecated.gateway_helpers')['mollie_wc_gateway_applepay']) ? $container->get(
                '__deprecated.gateway_helpers'
            )['mollie_wc_gateway_applepay'] : false;
            if (!$appleGateway) {
                return false;
            }
            $notice = $container->get(AdminNotice::class);
            assert($notice instanceof AdminNotice);
            $logger = $container->get(Logger::class);
            assert($logger instanceof Logger);

            $apiHelper = $container->get('SDK.api_helper');
            assert($apiHelper instanceof Api);
            $settingsHelper = $container->get('settings.settings_helper');
            assert($settingsHelper instanceof Settings);

            $responseTemplates = new ResponsesToApple($logger, $appleGateway);
            $ajaxRequests = new AppleAjaxRequests($responseTemplates, $notice, $logger, $apiHelper, $settingsHelper);
            return new ApplePayDirectHandler($notice, $ajaxRequests);
        },
        PayPalButtonHandler::class => static function (ContainerInterface $container) {
            $notice = $container->get(AdminNotice::class);
            assert($notice instanceof AdminNotice);
            $logger = $container->get(Logger::class);
            assert($logger instanceof Logger);
            $paypalGateway = isset($container->get('__deprecated.gateway_helpers')['mollie_wc_gateway_paypal']) ? $container->get(
                '__deprecated.gateway_helpers'
            )['mollie_wc_gateway_paypal'] : false;
            $pluginUrl = $container->get('shared.plugin_url');
            $ajaxRequests = new PayPalAjaxRequests($paypalGateway, $notice, $logger);
            $data = new DataToPayPal($pluginUrl);
            return new PayPalButtonHandler($ajaxRequests, $data);
        },
        'payment_gateway.getRefundProcessor' => static function (ContainerInterface $container): callable {
            return static function (string $gatewayId) use ($container): RefundProcessor {
                $oldGatewayInstances = $container->get('__deprecated.gateway_helpers');

                if (!isset($oldGatewayInstances[$gatewayId])) {
                    return $container->get('payment_gateways.noop_refund_processor');
                }

                $gateway = $oldGatewayInstances[$gatewayId];
                return new RefundProcessor($gateway);
            };
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
        'payment_request_validators' => static function (ContainerInterface $container): callable {
            return static function (string $gatewayId) use ($container): callable {
                //todo this is default
                return $container->get('payment_gateways.noop_payment_request_validator');
            };
        },
        'gateway.getMethodPropertyByGatewayId' => static function (ContainerInterface $container): callable {
            return static function (string $gatewayId, string $property) use ($container) {
                $paymentMethods = $container->get('gateway.paymentMethods');
                $methodId = substr($gatewayId, strrpos($gatewayId, '_') + 1);
                $paymentMethod = $paymentMethods[$methodId];
                return $paymentMethod->getProperty($property);
            };
        },
        'payment_gateway.getPaymentMethod' => static function (ContainerInterface $container): callable {
            return static function (string $gatewayId) use ($container): PaymentMethodI {
                $paymentMethods = $container->get('gateway.paymentMethods');
                $methodId = substr($gatewayId, strrpos($gatewayId, '_') + 1);
                return $paymentMethods[$methodId];
            };
        },
        'gateway.subscriptionHooks' => static function (): array {
            return [
                'subscriptions',
                'subscription_cancellation',
                'subscription_suspension',
                'subscription_reactivation',
                'subscription_amount_changes',
                'subscription_date_changes',
                'multiple_subscriptions',
                'subscription_payment_method_change',
                'subscription_payment_method_change_admin',
                'subscription_payment_method_change_customer',
            ];
        },

    ];
    $paymentMethods = SharedDataDictionary::GATEWAY_CLASSNAMES;


    $dynamicServices = [];
    foreach ($paymentMethods as $paymentMethod) {
        $gatewayId = strtolower($paymentMethod);

        $dynamicServices["payment_gateway.$gatewayId.payment_request_validator"] = static function (ContainerInterface $container): PaymentRequestValidatorInterface {
            return $container->get('payment_gateways.noop_payment_request_validator');
        };
        $dynamicServices["payment_gateway.$gatewayId.payment_processor"] = static function (ContainerInterface $container) use ($gatewayId): PaymentProcessor {
            $oldGatewayInstances = $container->get('__deprecated.gateway_helpers');
            $deprecatedGatewayHelper = $oldGatewayInstances[$gatewayId];
            $paymentProcessor = $container->get(PaymentProcessor::class);
            $paymentProcessor->setGateway($deprecatedGatewayHelper);
            return $paymentProcessor;
        };
        $dynamicServices["payment_gateway.$gatewayId.refund_processor"] = static function (ContainerInterface $container
        ) use ($gatewayId): RefundProcessorInterface {
            $getProperty = $container->get('gateway.getMethodPropertyByGatewayId');
            $supports = $getProperty($gatewayId, 'supports');
            $supportsRefunds = $supports && in_array('refunds', $supports, true);
            if ($supportsRefunds) {
                return $container->get('payment_gateway.getRefundProcessor')($gatewayId);
            }
            return $container->get('payment_gateways.noop_refund_processor');
        };
        $dynamicServices["payment_gateway.$gatewayId.has_fields"] = static function (ContainerInterface $container) use ($gatewayId): bool {
            $getProperty = $container->get('gateway.getMethodPropertyByGatewayId');
            if ($getProperty($gatewayId, 'paymentFields')) {
                return true;
            }

            /* Override show issuers dropdown? */
            $dropdownDisabled = $getProperty($gatewayId, 'issuers_dropdown_shown') === 'no';
            if ($dropdownDisabled) {
                return false;
            }
            return false;
        };
        $dynamicServices["payment_gateway.$gatewayId.gateway_icons_renderer"] = static function (ContainerInterface $container) use ($gatewayId) {
            return new GatewayIconsRenderer($gatewayId, $container);
        };
        $dynamicServices["payment_gateway.$gatewayId.payment_fields_renderer"] = static function (ContainerInterface $container) use ($gatewayId) {
            $paymentMethods = $container->get('gateway.paymentMethods');
            $methodId = substr($gatewayId, strrpos($gatewayId, '_') + 1);
            $paymentMethod = $paymentMethods[$methodId];

            $oldGatewayInstances = $container->get('__deprecated.gateway_helpers');
            //not all payment methods have a gateway
            if (!isset($oldGatewayInstances[$gatewayId])) {
                return new NoopPaymentFieldsRenderer();
            }
            //TODO im passing the deprecated gateway
            $gateway = $oldGatewayInstances[$gatewayId];
            return new PaymentFieldsRenderer($paymentMethod, $gateway);
        };

        $dynamicServices["payment_gateway.$gatewayId.title"] = static function (ContainerInterface $container) use ($gatewayId) {
            $paymentMethods = $container->get('gateway.paymentMethods');
            $methodId = substr($gatewayId, strrpos($gatewayId, '_') + 1);
            $paymentMethod = $paymentMethods[$methodId];

            return $paymentMethod->title();
        };
        $dynamicServices["payment_gateway.$gatewayId.method_title"] = static function (ContainerInterface $container) use ($gatewayId) {
            $paymentMethods = $container->get('gateway.paymentMethods');
            $methodId = substr($gatewayId, strrpos($gatewayId, '_') + 1);
            $paymentMethod = $paymentMethods[$methodId];

            return 'Mollie - ' . $paymentMethod->title();
        };
        $dynamicServices["payment_gateway.$gatewayId.method_description"] = static function (ContainerInterface $container) use ($gatewayId) {
            $paymentMethods = $container->get('gateway.paymentMethods');
            $methodId = substr($gatewayId, strrpos($gatewayId, '_') + 1);
            $paymentMethod = $paymentMethods[$methodId];

            return $paymentMethod->getProperty('settingsDescription');
        };
        $dynamicServices["payment_gateway.$gatewayId.description"] = static function (ContainerInterface $container) use ($gatewayId) {
            $paymentMethods = $container->get('gateway.paymentMethods');
            $methodId = substr($gatewayId, strrpos($gatewayId, '_') + 1);
            $paymentMethod = $paymentMethods[$methodId];

            $description = $paymentMethod->getProcessedDescription();
            return empty($description) ? false : $description;
        };
        $dynamicServices["payment_gateway.$gatewayId.availability_callback"] = new Factory(
            ['__deprecated.gateway_helpers'],
            static function (array $gatewayInstances) use($gatewayId): callable {
                return static function ($gateway) use ($gatewayInstances, $gatewayId): bool {
                    return $gatewayInstances[$gatewayId]->is_available($gateway);
                };
            }
        );
        $dynamicServices["payment_gateway.$gatewayId.form_fields"] = static function (ContainerInterface $container) use ($gatewayId) {
            $paymentMethods = $container->get('gateway.paymentMethods');
            $methodId = substr($gatewayId, strrpos($gatewayId, '_') + 1);
            $paymentMethod = $paymentMethods[$methodId];
            return $paymentMethod->getAllFormFields();
        };
        $dynamicServices["payment_gateway.$gatewayId.option_key"] = static function (ContainerInterface $container) use ($gatewayId) {
            return $gatewayId . '_settings';
        };
        $dynamicServices["payment_gateway.$gatewayId.supports"] = static function (ContainerInterface $container) use ($gatewayId) {
            $paymentMethods = $container->get('gateway.paymentMethods');
            $methodId = substr($gatewayId, strrpos($gatewayId, '_') + 1);
            $paymentMethod = $paymentMethods[$methodId];
            $supports = $paymentMethod->getProperty('supports');
            $isSepa = $paymentMethod->getProperty('SEPA') === true;
            $isSubscription = $paymentMethod->getProperty('Subscription') === true;
            $subscriptionHooks = $container->get('gateway.subscriptionHooks');
            if ($isSepa || $isSubscription) {
                $supports = array_merge($supports, $subscriptionHooks);
            }
            return $supports;

        };
        $dynamicServices["payment_gateway.$gatewayId.settings_field_renderer.multi_select_countries"] = static function (ContainerInterface $container) use ($gatewayId) {
            $paymentMethods = $container->get('gateway.paymentMethods');
            $methodId = substr($gatewayId, strrpos($gatewayId, '_') + 1);
            $paymentMethod = $paymentMethods[$methodId];

            return new MultiCountrySettingsField($paymentMethod);
        };
    }

    return array_merge($services, $dynamicServices);
};

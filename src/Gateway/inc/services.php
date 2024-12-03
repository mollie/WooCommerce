<?php

declare(strict_types=1);

use Dhii\Services\Factory;
use Inpsyde\PaymentGateway\PaymentRequestValidatorInterface;
use Mollie\WooCommerce\Gateway\MolliePaymentGateway;
use Mollie\WooCommerce\Gateway\OldGatewayBuilder;
use Mollie\WooCommerce\Gateway\OrderMandatoryGatewayDisabler;
use Mollie\WooCommerce\Gateway\RefundProcessor;
use Mollie\WooCommerce\Gateway\Surcharge;
use Mollie\WooCommerce\Notice\AdminNotice;
use Mollie\WooCommerce\Notice\NoticeInterface;
use Mollie\WooCommerce\Payment\MollieObject;
use Mollie\WooCommerce\Payment\MollieOrderService;
use Mollie\WooCommerce\Payment\OrderInstructionsService;
use Mollie\WooCommerce\Payment\PaymentCheckoutRedirectService;
use Mollie\WooCommerce\Payment\PaymentFactory;
use Mollie\WooCommerce\Payment\PaymentFieldsService;
use Mollie\WooCommerce\Payment\PaymentService;
use Mollie\WooCommerce\PaymentMethods\Constants;
use Mollie\WooCommerce\PaymentMethods\Icon\GatewayIconsRenderer;
use Mollie\WooCommerce\PaymentMethods\IconFactory;
use Mollie\WooCommerce\PaymentMethods\PaymentFieldsStrategies\NoopPaymentFieldsRenderer;
use Mollie\WooCommerce\PaymentMethods\PaymentFieldsStrategies\PaymentFieldsRenderer;
use Mollie\WooCommerce\PaymentMethods\PaymentMethodI;
use Mollie\WooCommerce\SDK\Api;
use Mollie\WooCommerce\SDK\HttpResponse;
use Mollie\WooCommerce\Settings\Settings;
use Mollie\WooCommerce\Shared\Data;
use Mollie\WooCommerce\Shared\SharedDataDictionary;
use Mollie\WooCommerce\Subscription\MollieSepaRecurringGateway;
use Mollie\WooCommerce\Subscription\MollieSubscriptionGateway;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface as Logger;

return static function (): array {
    $services = [
        'gateway.classnames' => static function (): array {
            return SharedDataDictionary::GATEWAY_CLASSNAMES;
        },
        'gateway.instances' => static function (ContainerInterface $container): array {
            //TODO remove this
            $oldGatewayBuilder = new OldGatewayBuilder();
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
        'payment_gateway.getRefundProcessor' => static function (ContainerInterface $container): callable {
            return static function (string $gatewayId) use ($container): RefundProcessor {
                $oldGatewayInstances = $container->get('gateway.instances');

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
        'gateway.getPropertyByGatewayId' => static function (ContainerInterface $container): callable {
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
        'payment_gateway.getOldGatewayInstances' => static function (ContainerInterface $container): callable {
            return static function () use ($container): array {
                return $container->get('gateway.instances');
            };
        },

    ];
    $paymentMethods = SharedDataDictionary::GATEWAY_CLASSNAMES;


    $dynamicServices = [];
    foreach ($paymentMethods as $paymentMethod) {
        $gatewayId = strtolower($paymentMethod);

        $dynamicServices["payment_gateway.$gatewayId.payment_request_validator"] = static function (ContainerInterface $container): PaymentRequestValidatorInterface {
            return $container->get('payment_gateways.noop_payment_request_validator');
        };
        $dynamicServices["payment_gateway.$gatewayId.payment_processor"] = static function (ContainerInterface $container) use ($gatewayId): PaymentService {
            $oldGatewayInstances = $container->get('gateway.instances');
            $gateway = $oldGatewayInstances[$gatewayId];
            $paymentService = $container->get(PaymentService::class);
            $paymentService->setGateway($gateway);
            return $paymentService;
        };
        $dynamicServices["payment_gateway.$gatewayId.refund_processor"] = static function (ContainerInterface $container
        ) use ($gatewayId): PaymentService {
            $getProperty = $container->get('gateway.getPropertyByGatewayId');
            $supports = $getProperty($gatewayId, 'supports');
            $supportsRefunds = $supports && in_array('refunds', $supports, true);
            if ($supportsRefunds) {
                return $container->get('payment_gateway.getRefundProcessor')($gatewayId);
            }
            return $container->get('payment_gateways.noop_refund_processor');
        };
        $dynamicServices["payment_gateway.$gatewayId.has_fields"] = static function (ContainerInterface $container) use ($gatewayId): bool {
            $getProperty = $container->get('gateway.getPropertyByGatewayId');
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

            $oldGatewayInstances = $container->get('gateway.instances');
            //not all payment methods have a gateway
            if (!isset($oldGatewayInstances[$gatewayId])) {
                return new NoopPaymentFieldsRenderer();
            }
            $gateway = $oldGatewayInstances[$gatewayId];
            return new PaymentFieldsRenderer($paymentMethod, $gateway);
        };

        $dynamicServices["payment_gateway.$gatewayId.title"] = static function (ContainerInterface $container) use ($gatewayId) {
            $paymentMethods = $container->get('gateway.paymentMethods');
            $methodId = substr($gatewayId, strrpos($gatewayId, '_') + 1);
            $paymentMethod = $paymentMethods[$methodId];

            return $paymentMethod->title();
        };
        $dynamicServices["payment_gateway.$gatewayId.description"] = static function (ContainerInterface $container) use ($gatewayId) {
            $paymentMethods = $container->get('gateway.paymentMethods');
            $methodId = substr($gatewayId, strrpos($gatewayId, '_') + 1);
            $paymentMethod = $paymentMethods[$methodId];

            $description = $paymentMethod->getProcessedDescription();
            return empty($description) ? false : $description;
        };
        $dynamicServices["payment_gateway.$gatewayId.availability_callback"] = new Factory(
            ['gateway.instances'],
            static function (array $gatewayInstances) use($gatewayId): callable {
                return static function () use ($gatewayInstances, $gatewayId): bool {
                    return $gatewayInstances[$gatewayId]->is_available();
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
    }

    return array_merge($services, $dynamicServices);
};

<?php

declare(strict_types=1);


use Mollie\WooCommerce\Gateway\Refund\OrderItemsRefunder;
use Mollie\WooCommerce\Payment\MollieObject;
use Mollie\WooCommerce\Payment\MollieOrder;
use Mollie\WooCommerce\Payment\MolliePayment;
use Mollie\WooCommerce\Payment\OrderLines;
use Mollie\WooCommerce\Payment\PaymentFactory;
use Mollie\WooCommerce\Payment\Request\Decorators\AddCustomRequestFieldsDecorator;
use Mollie\WooCommerce\Payment\Request\Decorators\AddressDecorator;
use Mollie\WooCommerce\Payment\Request\Decorators\AddSequenceTypeForSubscriptionsDecorator;
use Mollie\WooCommerce\Payment\Request\Decorators\ApplePayTokenDecorator;
use Mollie\WooCommerce\Payment\Request\Decorators\CardTokenDecorator;
use Mollie\WooCommerce\Payment\Request\Decorators\CustomerBirthdateDecorator;
use Mollie\WooCommerce\Payment\Request\Decorators\OrderLinesDecorator;
use Mollie\WooCommerce\Payment\Request\Decorators\PaymentDescriptionDecorator;
use Mollie\WooCommerce\Payment\Request\Decorators\SelectedIssuerDecorator;
use Mollie\WooCommerce\Payment\Request\Decorators\StoreCustomerDecorator;
use Mollie\WooCommerce\Payment\Request\Decorators\UrlDecorator;
use Mollie\WooCommerce\Payment\Request\Strategies\OrderRequestStrategy;
use Mollie\WooCommerce\Payment\Request\Strategies\PaymentRequestStrategy;
use Mollie\WooCommerce\Payment\Request\RequestFactory;
use Mollie\WooCommerce\Payment\Request\Strategies\RequestStrategyInterface;
use Mollie\WooCommerce\SDK\Api;
use Mollie\WooCommerce\Settings\Settings;
use Mollie\WooCommerce\Shared\Data;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface as Logger;

return static function (): array {
    return [
        MollieObject::class => static function (ContainerInterface $container): MollieObject {
            $logger = $container->get(Logger::class);
            assert($logger instanceof Logger);
            $data = $container->get('settings.data_helper');
            assert($data instanceof Data);
            $apiHelper = $container->get('SDK.api_helper');
            assert($apiHelper instanceof Api);
            $pluginId = $container->get('shared.plugin_id');
            $paymentFactory = $container->get(PaymentFactory::class);
            //assert($paymentFactory instanceof PaymentFactory);
            $settingsHelper = $container->get('settings.settings_helper');
            assert($settingsHelper instanceof Settings);
            $requestFactory = $container->get(RequestFactory::class);
            return new MollieObject($data, $logger, $paymentFactory, $apiHelper, $settingsHelper, $pluginId, $requestFactory);
        },
        OrderLines::class => static function (ContainerInterface $container): OrderLines {
            $data = $container->get('settings.data_helper');
            $pluginId = $container->get('shared.plugin_id');
            return new OrderLines($data, $pluginId);
        },

        PaymentFactory::class => static function (ContainerInterface $container): PaymentFactory {
            return new PaymentFactory(
                function () use ($container) {
                    return new MollieOrder(
                        $container->get(OrderItemsRefunder::class),
                        'order',
                        $container->get('shared.plugin_id'),
                        $container->get('SDK.api_helper'),
                        $container->get('settings.settings_helper'),
                        $container->get('settings.data_helper'),
                        $container->get(Logger::class),
                        $container->get(OrderLines::class),
                        $container->get(RequestFactory::class)
                    );
                },
                function () use ($container) {
                    return new MolliePayment(
                        'payment',
                        $container->get('shared.plugin_id'),
                        $container->get('SDK.api_helper'),
                        $container->get('settings.settings_helper'),
                        $container->get('settings.data_helper'),
                        $container->get(Logger::class),
                        $container->get(RequestFactory::class)
                    );
                }
            );
        },
        RequestFactory::class => static function (ContainerInterface $container): RequestFactory {
            return new RequestFactory($container);
        },
        CustomerBirthdateDecorator::class => static function (ContainerInterface $container): CustomerBirthdateDecorator {
            return new CustomerBirthdateDecorator($container->get('gateway.paymentMethods'));
        },
        ApplePayTokenDecorator::class => static function (): ApplePayTokenDecorator {
            return new ApplePayTokenDecorator();
        },
        CardTokenDecorator::class => static function (): CardTokenDecorator {
            return new CardTokenDecorator();
        },
        StoreCustomerDecorator::class => static function (ContainerInterface $container): StoreCustomerDecorator {
            return new StoreCustomerDecorator($container->get('settings.settings_helper'));
        },
        AddSequenceTypeForSubscriptionsDecorator::class => static function (ContainerInterface $container): AddSequenceTypeForSubscriptionsDecorator {
            $dataHelper = $container->get('settings.data_helper');
            $pluginId = $container->get('shared.plugin_id');
            return new AddSequenceTypeForSubscriptionsDecorator($dataHelper, $pluginId);
        },
        OrderLinesDecorator::class => static function (ContainerInterface $container): OrderLinesDecorator {
            $orderLines = $container->get(OrderLines::class);
            $voucherDefaultCategory = $container->get('voucher.defaultCategory');
            return new OrderLinesDecorator($orderLines, $voucherDefaultCategory);
        },
        AddressDecorator::class => static function (): AddressDecorator {
            return new AddressDecorator();
        },
        UrlDecorator::class => static function (ContainerInterface $container): UrlDecorator {
            return new UrlDecorator(
                $container->get('shared.plugin_id'),
                $container->get(Logger::class),
            );
        },
        SelectedIssuerDecorator::class => static function (ContainerInterface $container): SelectedIssuerDecorator {
            $pluginId = $container->get('shared.plugin_id');
            return new SelectedIssuerDecorator($pluginId);
        },
        PaymentDescriptionDecorator::class => static function (ContainerInterface $container): PaymentDescriptionDecorator {
            $dataHelper = $container->get('settings.data_helper');
            return new PaymentDescriptionDecorator($dataHelper);
        },
        AddCustomRequestFieldsDecorator::class => static function (ContainerInterface $container): AddCustomRequestFieldsDecorator {
            $paymentMethods = $container->get('gateway.paymentMethods');
            return new AddCustomRequestFieldsDecorator($paymentMethods, $container);
        },
        'request.strategy.order' => static function (ContainerInterface $container): RequestStrategyInterface {
            $dataHelper = $container->get('settings.data_helper');
            $settingsHelper = $container->get('settings.settings_helper');
            return new OrderRequestStrategy(
                $dataHelper,
                $settingsHelper,
                [
                    $container->get(CustomerBirthdateDecorator::class),
                    $container->get(ApplePayTokenDecorator::class),
                    $container->get(CardTokenDecorator::class),
                    $container->get(StoreCustomerDecorator::class),
                    $container->get(AddSequenceTypeForSubscriptionsDecorator::class),
                    $container->get(OrderLinesDecorator::class),
                    $container->get(AddressDecorator::class),
                    $container->get(UrlDecorator::class),
                    $container->get(SelectedIssuerDecorator::class),
                ]
            );
        },
        'request.strategy.payment' => static function (ContainerInterface $container): RequestStrategyInterface {
            $dataHelper = $container->get('settings.data_helper');
            $settingsHelper = $container->get('settings.settings_helper');
            $issuer = $container->get(SelectedIssuerDecorator::class);
            $url = $container->get(UrlDecorator::class);
            $sequenceType = $container->get(AddSequenceTypeForSubscriptionsDecorator::class);
            $cardToken = $container->get(CardTokenDecorator::class);
            $applePayToken = $container->get(ApplePayTokenDecorator::class);
            $storeCustomer = $container->get(StoreCustomerDecorator::class);
            $paymentDescription = $container->get(PaymentDescriptionDecorator::class);
            $addCustomRequestFields = $container->get(AddCustomRequestFieldsDecorator::class);

            return new PaymentRequestStrategy(
                $dataHelper,
                $settingsHelper,
                [
                    $issuer,
                    $url,
                    $sequenceType,
                    $cardToken,
                    $applePayToken,
                    $storeCustomer,
                    $paymentDescription,
                    $addCustomRequestFields,
                ]
            );
        },
    ];
};

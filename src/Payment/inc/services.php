<?php

declare(strict_types=1);


use Mollie\WooCommerce\Payment\Decorator\AddCustomRequestFieldsDecorator;
use Mollie\WooCommerce\Payment\Decorator\AddressDecorator;
use Mollie\WooCommerce\Payment\Decorator\AddSequenceTypeForSubscriptionsDecorator;
use Mollie\WooCommerce\Payment\Decorator\ApplePayTokenDecorator;
use Mollie\WooCommerce\Payment\Decorator\CardTokenDecorator;
use Mollie\WooCommerce\Payment\Decorator\OrderLinesDecorator;
use Mollie\WooCommerce\Payment\Decorator\PaymentDescriptionDecorator;
use Mollie\WooCommerce\Payment\Decorator\SelectedIssuerDecorator;
use Mollie\WooCommerce\Payment\Decorator\StoreCustomerDecorator;
use Mollie\WooCommerce\Payment\Decorator\UrlDecorator;
use Mollie\WooCommerce\Payment\MollieObject;
use Mollie\WooCommerce\Payment\OrderLines;
use Mollie\WooCommerce\Payment\PaymentFactory;
use Mollie\WooCommerce\Payment\Request\Decorators\CustomerBirthdateDecorator;
use Mollie\WooCommerce\Payment\Request\OrderRequestStrategy;
use Mollie\WooCommerce\Payment\Request\PaymentRequestStrategy;
use Mollie\WooCommerce\Payment\Request\RequestFactory;
use Mollie\WooCommerce\Payment\Request\RequestStrategyInterface;
use Mollie\WooCommerce\SDK\Api;
use Mollie\WooCommerce\Settings\Settings;
use Mollie\WooCommerce\Shared\Data;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface as Logger;

return static function (): array {
    return [
        OrderLines::class => static function (ContainerInterface $container): OrderLines {
            $data = $container->get('settings.data_helper');
            $pluginId = $container->get('shared.plugin_id');
            return new OrderLines($data, $pluginId);
        },
        PaymentFactory::class => static function (ContainerInterface $container): PaymentFactory {
            $settingsHelper = $container->get('settings.settings_helper');
            assert($settingsHelper instanceof Settings);
            $apiHelper = $container->get('SDK.api_helper');
            assert($apiHelper instanceof Api);
            $data = $container->get('settings.data_helper');
            assert($data instanceof Data);
            $pluginId = $container->get('shared.plugin_id');
            $logger = $container->get(Logger::class);
            assert($logger instanceof Logger);
            $orderLines = $container->get(OrderLines::class);
            return new PaymentFactory($data, $apiHelper, $settingsHelper, $pluginId, $logger, $orderLines);
        },
        RequestFactory::class => static function (ContainerInterface $container): RequestFactory {
            return new RequestFactory($container);
        },
        CustomerBirthdateDecorator::class => static function (ContainerInterface $container): CustomerBirthdateDecorator {
            return new CustomerBirthdateDecorator($container->get('payment_methods'));
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
            $pluginId = $container->get('shared.plugin_id');
            return new UrlDecorator($pluginId);
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
            $paymentMethod = $container->get('payment.method');
            return new AddCustomRequestFieldsDecorator($paymentMethod, $container);
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
            $decorators = [
                $container->get(SelectedIssuerDecorator::class),
                $container->get(UrlDecorator::class),
                $container->get(AddSequenceTypeForSubscriptionsDecorator::class),
                $container->get(ApplePayTokenDecorator::class),
                $container->get(CardTokenDecorator::class),
                $container->get(StoreCustomerDecorator::class),
                $container->get(PaymentDescriptionDecorator::class),
                $container->get(AddCustomRequestFieldsDecorator::class),
            ];
            return new PaymentRequestStrategy($dataHelper, $settingsHelper, $decorators);
        },
        MollieObject::class => static function (ContainerInterface $container): MollieObject {
            $logger = $container->get(Logger::class);
            assert($logger instanceof Logger);
            $data = $container->get('settings.data_helper');
            assert($data instanceof Data);
            $apiHelper = $container->get('SDK.api_helper');
            assert($apiHelper instanceof Api);
            $pluginId = $container->get('shared.plugin_id');
            $paymentFactory = $container->get(PaymentFactory::class);
            assert($paymentFactory instanceof PaymentFactory);
            $settingsHelper = $container->get('settings.settings_helper');
            assert($settingsHelper instanceof Settings);
            $requestFactory = $container->get(RequestFactory::class);
            return new MollieObject($data, $logger, $paymentFactory, $apiHelper, $settingsHelper, $pluginId, $requestFactory);
        },
    ];
};

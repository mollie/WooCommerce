<?php

declare (strict_types=1);
namespace Mollie;

use Mollie\WooCommerce\Gateway\Refund\OrderItemsRefunder;
use Mollie\WooCommerce\Payment\MollieObject;
use Mollie\WooCommerce\Payment\MollieOrder;
use Mollie\WooCommerce\Payment\MollieOrderService;
use Mollie\WooCommerce\Payment\MolliePayment;
use Mollie\WooCommerce\Payment\OrderLines;
use Mollie\WooCommerce\Payment\PaymentFactory;
use Mollie\WooCommerce\Payment\PaymentLines;
use Mollie\WooCommerce\Payment\Request\Middleware\AddCustomRequestFieldsMiddleware;
use Mollie\WooCommerce\Payment\Request\Middleware\AddressMiddleware;
use Mollie\WooCommerce\Payment\Request\Middleware\AddSequenceTypeForSubscriptionsMiddleware;
use Mollie\WooCommerce\Payment\Request\Middleware\ApplePayTokenMiddleware;
use Mollie\WooCommerce\Payment\Request\Middleware\CaptureModeMiddleware;
use Mollie\WooCommerce\Payment\Request\Middleware\CardTokenMiddleware;
use Mollie\WooCommerce\Payment\Request\Middleware\CustomerBirthdateMiddleware;
use Mollie\WooCommerce\Payment\Request\Middleware\MiddlewareHandler;
use Mollie\WooCommerce\Payment\Request\Middleware\OrderLinesMiddleware;
use Mollie\WooCommerce\Payment\Request\Middleware\PaymentDescriptionMiddleware;
use Mollie\WooCommerce\Payment\Request\Middleware\SelectedIssuerMiddleware;
use Mollie\WooCommerce\Payment\Request\Middleware\StoreCustomerMiddleware;
use Mollie\WooCommerce\Payment\Request\Middleware\UrlMiddleware;
use Mollie\WooCommerce\Payment\Request\Strategies\OrderRequestStrategy;
use Mollie\WooCommerce\Payment\Request\Strategies\PaymentRequestStrategy;
use Mollie\WooCommerce\Payment\Request\RequestFactory;
use Mollie\WooCommerce\Payment\Request\Strategies\RequestStrategyInterface;
use Mollie\WooCommerce\Payment\Webhooks\RestApi;
use Mollie\WooCommerce\SDK\Api;
use Mollie\WooCommerce\Settings\Settings;
use Mollie\WooCommerce\Settings\Webhooks\WebhookTestService;
use Mollie\WooCommerce\Shared\Data;
use Mollie\Psr\Container\ContainerInterface;
use Mollie\Psr\Log\LoggerInterface as Logger;
return static function (): array {
    return [MollieObject::class => static function (ContainerInterface $container): MollieObject {
        $logger = $container->get(Logger::class);
        \assert($logger instanceof Logger);
        $data = $container->get('settings.data_helper');
        \assert($data instanceof Data);
        $apiHelper = $container->get('SDK.api_helper');
        \assert($apiHelper instanceof Api);
        $pluginId = $container->get('shared.plugin_id');
        $paymentFactory = $container->get(PaymentFactory::class);
        \assert($paymentFactory instanceof PaymentFactory);
        $settingsHelper = $container->get('settings.settings_helper');
        \assert($settingsHelper instanceof Settings);
        $requestFactory = $container->get(RequestFactory::class);
        return new MollieObject($data, $logger, $paymentFactory, $apiHelper, $settingsHelper, $pluginId, $requestFactory);
    }, OrderLines::class => static function (ContainerInterface $container): OrderLines {
        $data = $container->get('settings.data_helper');
        $pluginId = $container->get('shared.plugin_id');
        return new OrderLines($data, $pluginId);
    }, PaymentLines::class => static function (ContainerInterface $container): PaymentLines {
        $data = $container->get('settings.data_helper');
        $pluginId = $container->get('shared.plugin_id');
        return new PaymentLines($data, $pluginId);
    }, PaymentFactory::class => static function (ContainerInterface $container): PaymentFactory {
        return new PaymentFactory(static function () use ($container) {
            return new MollieOrder($container->get(OrderItemsRefunder::class), 'order', $container->get('shared.plugin_id'), $container->get('SDK.api_helper'), $container->get('settings.settings_helper'), $container->get('settings.data_helper'), $container->get(Logger::class), $container->get(RequestFactory::class));
        }, static function () use ($container) {
            return new MolliePayment('payment', $container->get('shared.plugin_id'), $container->get('SDK.api_helper'), $container->get('settings.settings_helper'), $container->get('settings.data_helper'), $container->get(Logger::class), $container->get(RequestFactory::class));
        });
    }, RequestFactory::class => static function (ContainerInterface $container): RequestFactory {
        return new RequestFactory($container);
    }, CustomerBirthdateMiddleware::class => static function (ContainerInterface $container): CustomerBirthdateMiddleware {
        return new CustomerBirthdateMiddleware($container->get('gateway.paymentMethods'));
    }, CaptureModeMiddleware::class => static function (ContainerInterface $container): CaptureModeMiddleware {
        return new CaptureModeMiddleware($container->get('gateway.paymentMethods'));
    }, ApplePayTokenMiddleware::class => static function (): ApplePayTokenMiddleware {
        return new ApplePayTokenMiddleware();
    }, CardTokenMiddleware::class => static function (): CardTokenMiddleware {
        return new CardTokenMiddleware();
    }, StoreCustomerMiddleware::class => static function (ContainerInterface $container): StoreCustomerMiddleware {
        return new StoreCustomerMiddleware($container->get('settings.settings_helper'));
    }, AddSequenceTypeForSubscriptionsMiddleware::class => static function (ContainerInterface $container): AddSequenceTypeForSubscriptionsMiddleware {
        $dataHelper = $container->get('settings.data_helper');
        $pluginId = $container->get('shared.plugin_id');
        return new AddSequenceTypeForSubscriptionsMiddleware($dataHelper, $pluginId);
    }, OrderLinesMiddleware::class => static function (ContainerInterface $container): OrderLinesMiddleware {
        $orderLines = $container->get(OrderLines::class);
        $paymentLines = $container->get(PaymentLines::class);
        return new OrderLinesMiddleware($orderLines, $paymentLines);
    }, AddressMiddleware::class => static function (): AddressMiddleware {
        return new AddressMiddleware();
    }, UrlMiddleware::class => static function (ContainerInterface $container): UrlMiddleware {
        return new UrlMiddleware($container->get('shared.plugin_id'), $container->get(Logger::class));
    }, SelectedIssuerMiddleware::class => static function (ContainerInterface $container): SelectedIssuerMiddleware {
        $pluginId = $container->get('shared.plugin_id');
        return new SelectedIssuerMiddleware($pluginId);
    }, PaymentDescriptionMiddleware::class => static function (ContainerInterface $container): PaymentDescriptionMiddleware {
        $dataHelper = $container->get('settings.data_helper');
        return new PaymentDescriptionMiddleware($dataHelper);
    }, AddCustomRequestFieldsMiddleware::class => static function (ContainerInterface $container): AddCustomRequestFieldsMiddleware {
        $paymentMethods = $container->get('gateway.paymentMethods');
        return new AddCustomRequestFieldsMiddleware($paymentMethods, $container);
    }, 'request.strategy.order' => static function (ContainerInterface $container): RequestStrategyInterface {
        $dataHelper = $container->get('settings.data_helper');
        $settingsHelper = $container->get('settings.settings_helper');
        $middlewares = [$container->get(CustomerBirthdateMiddleware::class), $container->get(ApplePayTokenMiddleware::class), $container->get(CardTokenMiddleware::class), $container->get(StoreCustomerMiddleware::class), $container->get(AddSequenceTypeForSubscriptionsMiddleware::class), $container->get(OrderLinesMiddleware::class), $container->get(AddressMiddleware::class), $container->get(UrlMiddleware::class), $container->get(SelectedIssuerMiddleware::class)];
        $middlewareHandler = new MiddlewareHandler($middlewares);
        return new OrderRequestStrategy($dataHelper, $settingsHelper, $middlewareHandler);
    }, 'request.strategy.payment' => static function (ContainerInterface $container): RequestStrategyInterface {
        $dataHelper = $container->get('settings.data_helper');
        $settingsHelper = $container->get('settings.settings_helper');
        $issuer = $container->get(SelectedIssuerMiddleware::class);
        $url = $container->get(UrlMiddleware::class);
        $lines = $container->get(OrderLinesMiddleware::class);
        $address = $container->get(AddressMiddleware::class);
        $sequenceType = $container->get(AddSequenceTypeForSubscriptionsMiddleware::class);
        $cardToken = $container->get(CardTokenMiddleware::class);
        $applePayToken = $container->get(ApplePayTokenMiddleware::class);
        $storeCustomer = $container->get(StoreCustomerMiddleware::class);
        $paymentDescription = $container->get(PaymentDescriptionMiddleware::class);
        $addCustomRequestFields = $container->get(AddCustomRequestFieldsMiddleware::class);
        $middlewares = [$container->get(CaptureModeMiddleware::class), $issuer, $url, $address, $lines, $sequenceType, $cardToken, $applePayToken, $storeCustomer, $paymentDescription, $addCustomRequestFields];
        $middlewareHandler = new MiddlewareHandler($middlewares);
        return new PaymentRequestStrategy($dataHelper, $settingsHelper, $middlewareHandler);
    }, RestApi::class => static function (ContainerInterface $container): RestApi {
        $webhookTestService = $container->get(WebhookTestService::class);
        \assert($webhookTestService instanceof WebhookTestService);
        return new RestApi($container->get(MollieOrderService::class), $container->get(Logger::class), $webhookTestService);
    }];
};

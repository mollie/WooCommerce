<?php

namespace Mollie\WooCommerce\Gateway;

use Mollie\WooCommerce\Notice\FrontendNotice;
use Mollie\WooCommerce\Payment\MollieObject;
use Mollie\WooCommerce\Payment\MollieOrderService;
use Mollie\WooCommerce\Payment\PaymentFactory;
use Mollie\WooCommerce\Payment\PaymentProcessor;
use Mollie\WooCommerce\PaymentMethods\Constants;
use Mollie\WooCommerce\PaymentMethods\InstructionStrategies\OrderInstructionsManager;
use Mollie\WooCommerce\SDK\Api;
use Mollie\WooCommerce\SDK\HttpResponse;
use Mollie\WooCommerce\Settings\Settings;
use Mollie\WooCommerce\Shared\Data;
use Mollie\WooCommerce\Subscription\MollieSepaRecurringGatewayHandler;
use Mollie\WooCommerce\Subscription\MollieSubscriptionGatewayHandler;
use Mollie\Psr\Container\ContainerInterface;
use Mollie\Psr\Log\LoggerInterface as Logger;
class DeprecatedGatewayBuilder
{
    public function instantiatePaymentMethodGateways(ContainerInterface $container): array
    {
        $logger = $container->get(Logger::class);
        assert($logger instanceof Logger);
        $notice = $container->get(FrontendNotice::class);
        assert($notice instanceof FrontendNotice);
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
        $orderInstructionsManager = new OrderInstructionsManager();
        $mollieObject = $container->get(MollieObject::class);
        assert($mollieObject instanceof MollieObject);
        $paymentFactory = $container->get(PaymentFactory::class);
        assert($paymentFactory instanceof PaymentFactory);
        $pluginId = $container->get('shared.plugin_id');
        $gateways = [];
        if (empty($paymentMethods)) {
            return $gateways;
        }
        $enabledAtMollie = $container->get('gateway.paymentMethodsEnabledAtMollie');
        //we are using only the methods that are available and after feature flag
        foreach ($paymentMethods as $paymentMethod) {
            $paymentMethodId = $paymentMethod->getIdFromConfig();
            if (!in_array($paymentMethodId, $enabledAtMollie, \true)) {
                continue;
            }
            $isSepa = $paymentMethod->getProperty('SEPA');
            $key = 'mollie_wc_gateway_' . $paymentMethodId;
            if ($isSepa && isset($paymentMethods[Constants::DIRECTDEBIT])) {
                $directDebit = $paymentMethods[Constants::DIRECTDEBIT];
                $gateways[$key] = new MollieSepaRecurringGatewayHandler($directDebit, $paymentMethod, $orderInstructionsManager, $mollieOrderService, $data, $logger, $notice, $HttpResponseService, $settingsHelper, $mollieObject, $paymentFactory, $pluginId, $apiHelper);
            } elseif ($paymentMethod->getProperty('Subscription')) {
                $gateways[$key] = new MollieSubscriptionGatewayHandler($paymentMethod, $orderInstructionsManager, $mollieOrderService, $data, $logger, $notice, $HttpResponseService, $settingsHelper, $mollieObject, $paymentFactory, $pluginId, $apiHelper);
            } else {
                $gateways[$key] = new \Mollie\WooCommerce\Gateway\MolliePaymentGatewayHandler($paymentMethod, $orderInstructionsManager, $mollieOrderService, $data, $logger, $notice, $HttpResponseService, $mollieObject, $paymentFactory, $pluginId);
            }
        }
        return $gateways;
    }
}

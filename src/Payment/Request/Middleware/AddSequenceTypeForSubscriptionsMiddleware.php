<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Payment\Request\Middleware;

use Mollie\WooCommerce\Shared\Data;
use WC_Order;

class AddSequenceTypeForSubscriptionsMiddleware implements RequestMiddlewareInterface
{
    private Data $dataHelper;
    private string $pluginId;

    public function __construct($dataHelper, $pluginId)
    {
        $this->dataHelper = $dataHelper;
        $this->pluginId = $pluginId;
    }

    public function __invoke(array $requestData, WC_Order $order, $context = null, callable $next): array
    {
        $gateway = wc_get_payment_gateway_by_order($order);
        if ($gateway) {
            $requestData = $this->addSequenceTypeForSubscriptionsFirstPayments($order->get_id(), $gateway, $requestData, $context);
        }
        return $next($requestData, $order, $context);
    }

    private function addSequenceTypeForSubscriptionsFirstPayments($orderId, $gateway, $requestData, $context): array
    {
        if ($this->dataHelper->isSubscription($orderId) || $this->dataHelper->isWcSubscription($orderId)) {
            $disable_automatic_payments = apply_filters($this->pluginId . '_is_automatic_payment_disabled', false);
            $supports_subscriptions = $gateway->supports('subscriptions');

            if ($supports_subscriptions == true && $disable_automatic_payments == false) {
                $requestData = $this->addSequenceTypeFirst($requestData, $context);
            }
        }
        return $requestData;
    }

    private function addSequenceTypeFirst($requestData, $context)
    {
        if ($context === 'order') {
            $requestData['payment']['sequenceType'] = 'first';
        } elseif ($context === 'payment') {
            $requestData['sequenceType'] = 'first';
        }
        return $requestData;
    }
}

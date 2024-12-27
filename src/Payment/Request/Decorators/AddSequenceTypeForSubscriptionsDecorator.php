<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Payment\Request\Decorators;

use Mollie\WooCommerce\Shared\Data;
use WC_Order;

class AddSequenceTypeForSubscriptionsDecorator implements RequestDecoratorInterface
{
    private Data $dataHelper;
    private string $pluginId;

    public function __construct($dataHelper, $pluginId)
    {
        $this->dataHelper = $dataHelper;
        $this->pluginId = $pluginId;
    }

    public function decorate(array $requestData, WC_Order $order, $context = null): array
    {
        $gateway = wc_get_payment_gateway_by_order($order);
        if ($gateway) {
            $requestData = $this->addSequenceTypeForSubscriptionsFirstPayments($order->get_id(), $gateway, $requestData, $context);
        }
        return $requestData;
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

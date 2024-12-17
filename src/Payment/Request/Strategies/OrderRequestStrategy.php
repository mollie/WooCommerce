<?php

namespace Mollie\WooCommerce\Payment\Request;

use Inpsyde\PaymentGateway\PaymentGateway;
use Mollie\WooCommerce\Settings\Settings;
use Mollie\WooCommerce\Shared\Data;
use WC_Order;

class OrderRequestStrategy implements RequestStrategyInterface
{
    private Data $dataHelper;
    private Settings $settingsHelper;
    private array $decorators;

    public function __construct($dataHelper, $settingsHelper, array $decorators) {
        $this->dataHelper = $dataHelper;
        $this->settingsHelper = $settingsHelper;
        $this->decorators = $decorators;
    }
    public function createRequest(WC_Order $order, string $customerId): array
    {
        $settingsHelper = $this->settingsHelper;

        $gateway = wc_get_payment_gateway_by_order($order);

        if (! $gateway || ! ( $gateway instanceof PaymentGateway )) {
            return  [ 'result' => 'failure' ];
        }

        $methodId = substr($gateway->id, strrpos($gateway->id, '_') + 1);
        $paymentLocale = $settingsHelper->getPaymentLocale();
        // Build the Mollie order data
        $requestData = [
            'amount' => [
                'currency' => $this->dataHelper->getOrderCurrency($order),
                'value' => $this->dataHelper->formatCurrencyValue(
                    $order->get_total(),
                    $this->dataHelper->getOrderCurrency($order)
                ),
            ],
            'method' => $methodId,
            'locale' => $paymentLocale,
            'metadata' => apply_filters(
                $this->dataHelper->getPluginId() . '_payment_object_metadata',
                [
                    'order_id' => $order->get_id(),
                    'order_number' => $order->get_order_number(),
                ]
            ),
            'orderNumber' => $order->get_order_number(),
        ];


        $context = 'order';
        foreach ($this->decorators as $decorator) {
            $requestData = $decorator->decorate($requestData, $order, $context);
        }

        return $requestData;
    }
}

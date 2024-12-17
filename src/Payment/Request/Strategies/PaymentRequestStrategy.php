<?php

namespace Mollie\WooCommerce\Payment\Request;

use Inpsyde\PaymentGateway\PaymentGateway;
use WC_Order;

class PaymentRequestStrategy implements RequestStrategyInterface
{
    private $dataHelper;
    private $settingsHelper;
    private array $decorators;


    public function __construct($dataHelper, $settingsHelper, array $decorators) {
        $this->dataHelper = $dataHelper;
        $this->settingsHelper = $settingsHelper;
        $this->decorators = $decorators;
    }
    public function createRequest(WC_Order $order, string $customerId): array
    {

        $gateway = wc_get_payment_gateway_by_order($order);

        if (!$gateway || !($gateway instanceof PaymentGateway)) {
            return ['result' => 'failure'];
        }
        $settingsHelper = $this->settingsHelper;
        $methodId = substr($gateway->id, strrpos($gateway->id, '_') + 1);
        $paymentLocale = $settingsHelper->getPaymentLocale();

        $paymentRequestData = [
            'amount' => [
                'currency' => $this->dataHelper
                    ->getOrderCurrency($order),
                'value' => $this->dataHelper
                    ->formatCurrencyValue(
                        $order->get_total(),
                        $this->dataHelper->getOrderCurrency(
                            $order
                        )
                    ),
            ],
            'description' => $paymentDescription,
            'method' => $methodId,
            'locale' => $paymentLocale,
            'metadata' => apply_filters(
                $this->dataHelper->getPluginId() . '_payment_object_metadata',
                [
                    'order_id' => $order->get_id(),
                ]
            ),
        ];

        $paymentRequestData = $this->addCustomRequestFields($order, $paymentRequestData);
        $context = 'payment';
        foreach ($this->decorators as $decorator) {
            $requestData = $decorator->decorate($requestData, $order, $context);
        }

        return $requestData;
    }
}

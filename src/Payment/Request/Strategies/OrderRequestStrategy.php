<?php

namespace Mollie\WooCommerce\Payment\Request;

use Inpsyde\PaymentGateway\PaymentGateway;
use Mollie\WooCommerce\Payment\Request\RequestStrategyInterface;
use WC_Order;

class OrderRequestStrategy implements RequestStrategyInterface
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
        $settingsHelper = $this->settingsHelper;

        $gateway = wc_get_payment_gateway_by_order($order);

        if (! $gateway || ! ( $gateway instanceof PaymentGateway )) {
            return  [ 'result' => 'failure' ];
        }

        $gatewayId = $gateway->id;
        $paymentLocale = $settingsHelper->getPaymentLocale();
        $selectedIssuer = $this->getSelectedIssuer($gatewayId);
        $returnUrl = $gateway->get_return_url($order);
        $returnUrl = $this->getReturnUrl($order, $returnUrl);
        $webhookUrl = $this->getWebhookUrl($order, $gatewayId);
        $isPayPalExpressOrder = $order->get_meta('_mollie_payment_method_button') === 'PayPalButton';
        $billingAddress = null;
        if (!$isPayPalExpressOrder) {
            $billingAddress = $this->createBillingAddress($order);
            $shippingAddress = $this->createShippingAddress($order);
        }
        // Only add shippingAddress if all required fields are set
        if (
            !empty($shippingAddress->streetAndNumber)
            && !empty($shippingAddress->postalCode)
            && !empty($shippingAddress->city)
            && !empty($shippingAddress->country)
        ) {
            $requestData['shippingAddress'] = $shippingAddress;
        }

        // Generate order lines for Mollie Orders
        $orderLinesHelper = $this->orderLines;
        $orderLines = $orderLinesHelper->order_lines($order, $voucherDefaultCategory);
        $methodId = substr($gateway->id, strrpos($gateway->id, '_') + 1);

        // Build the Mollie order data
        $requestData = [
            'amount' => [
                'currency' => $this->dataHelper->getOrderCurrency($order),
                'value' => $this->dataHelper->formatCurrencyValue(
                    $order->get_total(),
                    $this->dataHelper->getOrderCurrency($order)
                ),
            ],
            'redirectUrl' => $returnUrl,
            'webhookUrl' => $webhookUrl,
            'method' => $methodId,
            'payment' => [
                'issuer' => $selectedIssuer,
            ],
            'locale' => $paymentLocale,
            'billingAddress' => $billingAddress,
            'metadata' => apply_filters(
                $this->pluginId . '_payment_object_metadata',
                [
                    'order_id' => $order->get_id(),
                    'order_number' => $order->get_order_number(),
                ]
            ),
            'lines' => $orderLines['lines'],
            'orderNumber' => $order->get_order_number(),
        ];


        $context = 'order';
        foreach ($this->decorators as $decorator) {
            $requestData = $decorator->decorate($requestData, $order, $context);
        }

        return $requestData;
    }
}

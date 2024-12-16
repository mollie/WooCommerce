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
        $optionName = $this->pluginId . '_' . 'api_payment_description';
        $option = get_option($optionName);
        $paymentDescription = $this->getPaymentDescription($order, $option);
        $paymentLocale = $settingsHelper->getPaymentLocale();
        $storeCustomer = $settingsHelper->shouldStoreCustomer();

        $gatewayId = $gateway->id;
        $selectedIssuer = $this->getSelectedIssuer($gatewayId);
        $returnUrl = $gateway->get_return_url($order);
        $returnUrl = $this->getReturnUrl($order, $returnUrl);
        $webhookUrl = $this->getWebhookUrl($order, $gatewayId);
        $orderId = $order->get_id();

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
            'redirectUrl' => $returnUrl,
            'webhookUrl' => $webhookUrl,
            'method' => $this->paymentMethod->getProperty('id'),
            'issuer' => $selectedIssuer,
            'locale' => $paymentLocale,
            'metadata' => apply_filters(
                $this->pluginId . '_payment_object_metadata',
                [
                    'order_id' => $order->get_id(),
                ]
            ),
        ];

        $paymentRequestData = $this->addSequenceTypeForSubscriptionsFirstPayments($order->get_id(), $gateway, $paymentRequestData);

        if ($storeCustomer) {
            $paymentRequestData['customerId'] = $customerId;
        }

        $cardToken = mollieWooCommerceCardToken();
        if ($cardToken) {
            $paymentRequestData['cardToken'] = $cardToken;
        }
        //phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $applePayToken = wc_clean(wp_unslash($_POST["token"] ?? ''));
        if ($applePayToken) {
            $encodedApplePayToken = wp_json_encode($applePayToken);
            $paymentRequestData['applePayPaymentToken'] = $encodedApplePayToken;
        }
        $paymentRequestData = $this->addCustomRequestFields($order, $paymentRequestData);
        $context = 'payment';
        foreach ($this->decorators as $decorator) {
            $requestData = $decorator->decorate($requestData, $order, $context);
        }

        return $requestData;
    }
}

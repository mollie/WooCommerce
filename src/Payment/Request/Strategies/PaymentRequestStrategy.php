<?php

namespace Mollie\WooCommerce\Payment\Request\Strategies;

use Mollie\Inpsyde\PaymentGateway\PaymentGateway;
use Mollie\WooCommerce\Payment\Request\Middleware\MiddlewareHandler;
use Mollie\WooCommerce\Settings\Settings;
use Mollie\WooCommerce\Shared\Data;
use WC_Order;
/**
 * Class PaymentRequestStrategy
 *
 * This class handles the creation of payment requests for the Mollie payments API.
 *
 * @package Mollie\WooCommerce\Payment\Request\Strategies
 */
class PaymentRequestStrategy implements \Mollie\WooCommerce\Payment\Request\Strategies\RequestStrategyInterface
{
    /**
     * @var Data Helper for data operations.
     */
    private Data $dataHelper;
    /**
     * @var Settings Helper for settings operations.
     */
    private Settings $settingsHelper;
    /**
     * @var MiddlewareHandler Handler for middleware operations.
     */
    private MiddlewareHandler $middlewareHandler;
    /**
     * PaymentRequestStrategy constructor.
     *
     * @param Data $dataHelper Helper for data operations.
     * @param Settings $settingsHelper Helper for settings operations.
     * @param MiddlewareHandler $middlewareHandler Handler for middleware operations.
     */
    public function __construct($dataHelper, $settingsHelper, MiddlewareHandler $middlewareHandler)
    {
        $this->dataHelper = $dataHelper;
        $this->settingsHelper = $settingsHelper;
        $this->middlewareHandler = $middlewareHandler;
    }
    /**
     * Create a payment request for the given order.
     *
     * @param WC_Order $order The order to create a request for.
     * @param string $customerId The customer ID.
     * @return array The request data.
     */
    public function createRequest(WC_Order $order, $customerId): array
    {
        $gateway = wc_get_payment_gateway_by_order($order);
        if (!$gateway || !mollieWooCommerceIsMollieGateway($gateway->id)) {
            return ['result' => 'failure'];
        }
        $settingsHelper = $this->settingsHelper;
        $methodId = substr($gateway->id, strrpos($gateway->id, '_') + 1);
        $paymentLocale = $settingsHelper->getPaymentLocale();
        $requestData = ['amount' => ['currency' => $this->dataHelper->getOrderCurrency($order), 'value' => $this->dataHelper->formatCurrencyValue($order->get_total(), $this->dataHelper->getOrderCurrency($order))], 'method' => $methodId, 'locale' => $paymentLocale, 'metadata' => apply_filters($this->dataHelper->getPluginId() . '_payment_object_metadata', ['order_id' => $order->get_id()]), 'customerId' => $customerId];
        $context = 'payment';
        return $this->middlewareHandler->handle($requestData, $order, $context);
    }
}

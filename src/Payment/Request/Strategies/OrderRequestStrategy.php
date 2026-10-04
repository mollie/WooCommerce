<?php

namespace Mollie\WooCommerce\Payment\Request\Strategies;

use Mollie\WooCommerce\Payment\Request\Middleware\MiddlewareHandler;
use Mollie\WooCommerce\Settings\Settings;
use Mollie\WooCommerce\Shared\Data;
use WC_Order;
/**
 * Class OrderRequestStrategy
 *
 * This class handles the creation of payment requests for the Mollie orders API.
 *
 * @package Mollie\WooCommerce\Payment\Request\Strategies
 */
class OrderRequestStrategy implements \Mollie\WooCommerce\Payment\Request\Strategies\RequestStrategyInterface
{
    /**
     * @var Data
     */
    private Data $dataHelper;
    /**
     * @var Settings
     */
    private Settings $settingsHelper;
    /**
     * @var MiddlewareHandler
     */
    private MiddlewareHandler $middlewareHandler;
    /**
     * OrderRequestStrategy constructor.
     *
     * @param Data $dataHelper
     * @param Settings $settingsHelper
     * @param MiddlewareHandler $middlewareHandler
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
        $settingsHelper = $this->settingsHelper;
        $gateway = wc_get_payment_gateway_by_order($order);
        if (!$gateway || !mollieWooCommerceIsMollieGateway($gateway->id)) {
            return ['result' => 'failure'];
        }
        $methodId = substr($gateway->id, strrpos($gateway->id, '_') + 1);
        $paymentLocale = $settingsHelper->getPaymentLocale();
        $requestData = ['amount' => ['currency' => $this->dataHelper->getOrderCurrency($order), 'value' => $this->dataHelper->formatCurrencyValue($order->get_total(), $this->dataHelper->getOrderCurrency($order))], 'method' => $methodId, 'locale' => $paymentLocale, 'metadata' => apply_filters($this->dataHelper->getPluginId() . '_payment_object_metadata', ['order_id' => $order->get_id(), 'order_number' => $order->get_order_number()]), 'orderNumber' => $order->get_order_number(), 'payment' => ['customerId' => $customerId]];
        $context = 'order';
        return $this->middlewareHandler->handle($requestData, $order, $context);
    }
}

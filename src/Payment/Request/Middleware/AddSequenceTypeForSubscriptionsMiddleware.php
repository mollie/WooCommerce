<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Payment\Request\Middleware;

use Mollie\Api\Types\SequenceType;
use Mollie\WooCommerce\Shared\Data;
use WC_Order;
/**
 * Middleware to add sequence type for subscription payments.
 */
class AddSequenceTypeForSubscriptionsMiddleware implements \Mollie\WooCommerce\Payment\Request\Middleware\RequestMiddlewareInterface
{
    /**
     * @var Data Helper class for data operations.
     */
    private Data $dataHelper;
    /**
     * @var string Plugin ID.
     */
    private string $pluginId;
    /**
     * Constructor.
     *
     * @param Data $dataHelper Helper class for data operations.
     * @param string $pluginId Plugin ID.
     */
    public function __construct($dataHelper, $pluginId)
    {
        $this->dataHelper = $dataHelper;
        $this->pluginId = $pluginId;
    }
    /**
     * Invoke the middleware.
     *
     * @param array $requestData The request data.
     * @param WC_Order $order The WooCommerce order object.
     * @param string $context The context of the request.
     * @param callable $next The next middleware to call.
     * @return array The modified request data.
     */
    public function __invoke(array $requestData, WC_Order $order, $context, callable $next): array
    {
        $gateway = wc_get_payment_gateway_by_order($order);
        if ($gateway) {
            $requestData = $this->addSequenceTypeForSubscriptionsFirstPayments($order->get_id(), $gateway, $requestData, $context);
        }
        return $next($requestData, $order, $context);
    }
    /**
     * Add sequence type for the first payments of subscriptions.
     *
     * @param int $orderId The order ID.
     * @param WC_Payment_Gateway $gateway The payment gateway.
     * @param array $requestData The request data.
     * @param string $context The context of the request.
     * @return array The modified request data.
     */
    private function addSequenceTypeForSubscriptionsFirstPayments($orderId, $gateway, $requestData, $context): array
    {
        if ($this->dataHelper->isSubscription($orderId) || $this->dataHelper->isWcSubscription($orderId)) {
            $disable_automatic_payments = apply_filters($this->pluginId . '_is_automatic_payment_disabled', \false);
            $supports_subscriptions = $gateway->supports('subscriptions');
            if ($supports_subscriptions == \true && $disable_automatic_payments == \false) {
                $requestData = $this->addSequenceTypeFirst($requestData, $context);
            }
        }
        return $requestData;
    }
    /**
     * Add the sequence type 'first' to the request data.
     *
     * @param array $requestData The request data.
     * @param string $context The context of the request.
     * @return array The modified request data.
     */
    private function addSequenceTypeFirst($requestData, $context)
    {
        if ($context === 'order') {
            $requestData['payment']['sequenceType'] = SequenceType::SEQUENCETYPE_FIRST;
        } elseif ($context === 'payment') {
            $requestData['sequenceType'] = SequenceType::SEQUENCETYPE_FIRST;
            if (isset($requestData['captureMode'])) {
                unset($requestData['captureMode']);
            }
            if (isset($requestData['captureDelay'])) {
                unset($requestData['captureDelay']);
            }
        }
        return $requestData;
    }
}

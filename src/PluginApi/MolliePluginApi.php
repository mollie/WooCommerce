<?php

namespace Mollie\WooCommerce\PluginApi;

use Mollie\WooCommerce\MerchantCapture\Capture\Action\CapturePayment;
use Mollie\WooCommerce\MerchantCapture\Capture\Action\VoidPayment;
use Mollie\WooCommerce\Payment\MollieObject;

class MolliePluginApi {
    private static $instance = null;
    private CapturePayment $capturePayment;
    private VoidPayment $voidPayment;

    private MollieObject $mollieObject;

    private function __construct(
        CapturePayment $capturePayment,
        VoidPayment $voidPayment,
        MollieObject $mollieObject
    ) {
        $this->capturePayment = $capturePayment;
        $this->voidPayment = $voidPayment;
        $this->mollieObject = $mollieObject;
    }

    /**
     * Initializes the MolliePluginApi with necessary dependencies.
     */
    public static function init(
        CapturePayment $capturePayment,
        VoidPayment $voidPayment,
        MollieObject $mollieObject
    ): void {
        if (self::$instance === null) {
            self::$instance = new self(
                $capturePayment,
                $voidPayment,
                $mollieObject
            );
        }
    }

    /**
     * Returns the singleton instance of MolliePluginApi.
     *
     * @throws \LogicException If the API has not been initialized.
     */
    public static function getInstance(): self {
        if (self::$instance === null) {
            throw new \LogicException('MolliePluginApi has not been initialized.');
        }
        return self::$instance;
    }

    /**
     * Captures the Mollie order for the given WooCommerce order.
     * Logs the result of the operation.
     *
     * @param \WC_Order $wcOrder The WooCommerce order.
     */
    public function captureOrder(\WC_Order $wcOrder): void {
        $this->capturePayment($wcOrder->get_id());
    }

    /**
     * Refunds the Mollie order for the given WooCommerce order.
     *
     * @param \WC_Order $wcOrder The WooCommerce order.
     * @param float $amount The refund amount.
     * @param string $reason The reason for the refund.
     * @return \WP_Error|bool The result of the refund operation.
     */
    public function refundOrder(\WC_Order $wcOrder, float $amount, string $reason = '') {
        return $this->mollieObject->processRefund($wcOrder->get_id(), $amount , $reason);
    }

    /**
     * Voids the authorization for the given WooCommerce order.
     * Logs the result of the operation.
     *
     * @param \WC_Order $wcOrder The WooCommerce order.
     */
    public function voidOrder(\WC_Order $wcOrder): void {
        $this->voidPayment($wcOrder->get_id());
    }

    /**
     * Cancels the Order at Mollie and also in WooCommerce if was not already done.
     * Logs the result of the operation.
     *
     * @param \WC_Order $wcOrder The WooCommerce order.
     */
    public function cancelOrder(string $orderId): void {
        $this->mollieObject->cancelOrderAtMollie($orderId);
    }

    /**
     * Ship all order lines and capture an order at Mollie.
     * Logs the result of the operation.
     *
     * @param string $orderId The WooCommerce order ID.
     */
    public function shipOrderAndCapture(string $orderId): void
    {
        $this->mollieObject->shipAndCaptureOrderAtMollie($orderId);
    }
}

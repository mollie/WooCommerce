<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\MerchantCapture\Capture\Type;

use Mollie\WooCommerce\MerchantCapture\Capture\Action\CapturePayment;
use Mollie\WooCommerce\Vendor\Psr\Container\ContainerInterface;

class ManualCapture
{
    /**
     * @var ContainerInterface $container
     */
    protected $container;
    protected const MOLLIE_MANUAL_CAPTURE_ACTION = 'mollie_capture_authorized_payment';

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        add_action('woocommerce_order_actions', [$this, 'enableOrderCaptureButton'], 10, 2);
        add_action('woocommerce_order_action_' . self::MOLLIE_MANUAL_CAPTURE_ACTION, [$this, 'manualCapture']);
        add_filter('woocommerce_mollie_wc_gateway_creditcard_args', [$this, 'sendManualCaptureMode']);
    }

    public function enableOrderCaptureButton(array $actions, \WC_Order $order): array
    {
        if (!$this->container->get('merchant.manual_capture.can_capture_the_order')($order)) {
            return $actions;
        }
        $actions[self::MOLLIE_MANUAL_CAPTURE_ACTION] = __(
            'Capture authorized Mollie payment',
            'mollie-payments-for-woocommerce'
        );
        return $actions;
    }

    public function sendManualCaptureMode(array $paymentData): array
    {
        if (
            $this->container->get('merchant.manual_capture.enabled') &&
            $this->container->get('merchant.manual_capture.cart_can_be_captured')
        ) {
            $paymentData['captureMode'] = 'manual';
        }
        return $paymentData;
    }

    public function manualCapture(\WC_Order $order)
    {

        ($this->container->get(CapturePayment::class))($order->get_id());
    }
}

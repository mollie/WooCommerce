<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\MerchantCapture\Capture\Type;

use Mollie\WooCommerce\MerchantCapture\Capture\Action\CapturePayment;
use Mollie\WooCommerce\MerchantCapture\Capture\Action\VoidPayment;
use Mollie\WooCommerce\Shared\SharedDataDictionary;
use Mollie\WooCommerce\Vendor\Psr\Container\ContainerInterface;

class StateChangeCapture
{
    /**
     * @var ContainerInterface $container
     */
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $pluginId = $container->get('shared.plugin_id');

        add_action('woocommerce_order_status_changed', [$this, "orderStatusChange"], 10, 3);

        /** When the webhook process is activated we don't need automatic status change. Status change is handled
         *  by the webhook logic.
         */
        add_action($pluginId . '_before_webhook_payment_action', function () {
            remove_action('woocommerce_order_status_changed', [$this, "orderStatusChange"]);
        });
    }

    public function orderStatusChange(int $orderId, string $oldStatus, string $newStatus)
    {
        $stateChangeCaptureEnabled = $this->container->get('merchant.manual_capture.on_status_change_enabled');
        if (empty($stateChangeCaptureEnabled) || $stateChangeCaptureEnabled === 'no') {
            return;
        }

        if (!in_array($oldStatus, $this->container->get('merchant.manual_capture.void_statuses'))) {
            return;
        }

        if (in_array($newStatus, [SharedDataDictionary::STATUS_PROCESSING, SharedDataDictionary::STATUS_COMPLETED])) {
            $this->capturePayment($orderId);
            return;
        }

        if ($newStatus === SharedDataDictionary::STATUS_CANCELLED) {
            $this->voidPayment($orderId);
        }
    }

    protected function capturePayment(int $orderId)
    {
        ($this->container->get(CapturePayment::class))($orderId);
    }

    protected function voidPayment(int $orderId)
    {
        ($this->container->get(VoidPayment::class))($orderId);
    }
}

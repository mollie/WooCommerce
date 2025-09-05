<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\ReturnPage\mollie;

use Mollie\WooCommerce\Payment\MollieOrderService;
use Mollie\WooCommerce\ReturnPage\framework\StatusUpdaterInterface;
use Psr\Log\LoggerInterface;

/**
 * Mollie-specific Status Updater - uses the existing checkPaymentForUnpaidOrder method
 */
class MollieStatusUpdater implements StatusUpdaterInterface
{
    private MollieOrderService $orderService;
    private LoggerInterface $logger;

    public function __construct(
        MollieOrderService $orderService,
        LoggerInterface $logger
    ) {
        $this->logger = $logger;
        $this->orderService = $orderService;
    }

    public function updateStatus(\WC_Order $order): bool
    {
        try {
            $this->logger->info("Triggering Mollie payment check for order {order_id}", [
                'order_id' => $order->get_id()
            ]);

// Use the existing method that we know works
            return $this->orderService->checkPaymentForUnpaidOrder($order);
        } catch (\Exception $e) {
            $this->logger->error("Failed to update Mollie payment status for order {order_id}: {error}", [
                'order_id' => $order->get_id(),
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}


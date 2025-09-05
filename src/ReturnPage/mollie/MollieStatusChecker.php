<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\ReturnPage\mollie;

use Mollie\WooCommerce\ReturnPage\framework\{
    StatusCheckerInterface,
    StatusUpdaterInterface,
    IncidentLoggerInterface,
    AdaptiveConfigInterface,
    MessageRendererInterface,
    StatusActionInterface,
    ReturnPageStatus,
    ReturnPageConfig,
    ReturnPageManager
};
use Mollie\WooCommerce\Payment\MollieOrderService;
use Psr\Log\LoggerInterface;

/**
 * Mollie-specific Status Checker - knows how to check Mollie payments
 */
class MollieStatusChecker implements StatusCheckerInterface
{
    private MollieOrderService $orderService;

    public function __construct(
        MollieOrderService $orderService
    ) {
        $this->orderService = $orderService;
    }

    public function checkStatus(\WC_Order $order): ReturnPageStatus
    {
        // First check WooCommerce status
        if (!$order->needs_payment()) {
            return ReturnPageStatus::SUCCESS();
        }

        // Check if it's a Mollie payment
        if (strpos($order->get_payment_method(), 'mollie_wc_gateway_') === false) {
            return ReturnPageStatus::PENDING();
        }

        switch ($order->get_status()) {
            case 'processing':
            case 'completed':
                return ReturnPageStatus::SUCCESS();
            case 'failed':
                return ReturnPageStatus::FAILED();
            case 'cancelled':
                return ReturnPageStatus::CANCELLED();
            default:
                return ReturnPageStatus::PENDING();
        }
    }
}


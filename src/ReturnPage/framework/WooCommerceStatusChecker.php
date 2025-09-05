<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\ReturnPage\framework;

/**
 * Simple WooCommerce status checker
 */
class WooCommerceStatusChecker implements StatusCheckerInterface
{
    public function checkStatus(\WC_Order $order): ReturnPageStatus
    {
        if (!$order->needs_payment()) {
            return ReturnPageStatus::SUCCESS();
        }

        switch ($order->get_status()) {
            case 'pending':
                return ReturnPageStatus::PENDING();
            case 'failed':
                return ReturnPageStatus::FAILED();
            case 'cancelled':
                return ReturnPageStatus::CANCELLED();
            default:
                return ReturnPageStatus::PENDING();
        }
    }
}

<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\ReturnPage\mollie;

use Mollie\WooCommerce\ReturnPage\framework\MessageRendererInterface;
use Mollie\WooCommerce\ReturnPage\framework\ReturnPageStatus;

/**
 * Beautiful Mollie-branded Message Renderer
 */
class MollieMessageRenderer implements MessageRendererInterface
{
    public function render(string $message, ReturnPageStatus $status): string
    {
        $iconSvg = $this->getStatusIcon($status);
        $statusClass = 'mollie-return-status-' . $status->value;

        return sprintf(
            '<div class="mollie-return-page-message %s">
    <div class="mollie-return-content">
        <div class="mollie-status-icon">%s</div>
        <div class="mollie-status-text">
            <strong>%s</strong>
            <p>%s</p>
        </div>
    </div>
</div>',
            esc_attr($statusClass),
            $iconSvg,
            esc_html($this->getStatusTitle($status)),
            esc_html($message)
        );
    }

    private function getStatusIcon(ReturnPageStatus $status): string
    {
        if ($status->equals(ReturnPageStatus::SUCCESS())) {
            return '<svg class="mollie-icon-success" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>';
        }

        if ($status->equals(ReturnPageStatus::FAILED())) {
            return '<svg class="mollie-icon-error" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm5 11H7v-2h10v2z"/></svg>';
        }

        if ($status->equals(ReturnPageStatus::PENDING())) {
            return '<svg class="mollie-icon-pending" viewBox="0 0 24 24"><path fill="currentColor" d="M12 6v3l4-4-4-4v3c-4.42 0-8 3.58-8 8 0 1.57.46 3.03 1.24 4.26L6.7 14.8c-.45-.83-.7-1.79-.7-2.8 0-3.31 2.69-6 6-6zm6.76 1.74L17.3 9.2c.44.84.7 1.79.7 2.8 0 3.31-2.69 6-6 6v-3l-4 4 4 4v-3c4.42 0 8-3.58 8-8 0-1.57-.46-3.03-1.24-4.26z"/></svg>';
        }

        return '<svg class="mollie-icon-spinner" viewBox="0 0 24 24"><path fill="currentColor" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 15c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5z"/></svg>';
    }

    private function getStatusTitle(ReturnPageStatus $status): string
    {
        if ($status->equals(ReturnPageStatus::SUCCESS())) {
            return __('Payment Confirmed', 'mollie-payments-for-woocommerce');
        }

        if ($status->equals(ReturnPageStatus::FAILED())) {
            return __('Payment Failed', 'mollie-payments-for-woocommerce');
        }

        if ($status->equals(ReturnPageStatus::CANCELLED())) {
            return __('Payment Cancelled', 'mollie-payments-for-woocommerce');
        }

        if ($status->equals(ReturnPageStatus::TIMEOUT())) {
            return __('Verifying Payment', 'mollie-payments-for-woocommerce');
        }

        if ($status->equals(ReturnPageStatus::ERROR())) {
            return __('Payment Status Unknown', 'mollie-payments-for-woocommerce');
        }

        return __('Processing Payment', 'mollie-payments-for-woocommerce');
    }
}

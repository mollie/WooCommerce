<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\ReturnPage\framework;

/**
 * Default HTML message renderer
 */
class DefaultMessageRenderer implements MessageRendererInterface
{
    public function render(string $message, ReturnPageStatus $status): string
    {
        $statusClass = 'return-page-status-' . $status->value;

        return sprintf(
            '<div class="return-page-message %s">
                <div class="return-page-content">
                    <span class="return-page-spinner"></span>
                    <span class="return-page-text">%s</span>
                </div>
            </div>',
            esc_attr($statusClass),
            esc_html($message)
        );
    }
}

<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Notice;

class AdminNotice implements \Mollie\WooCommerce\Notice\NoticeInterface
{
    public function addNotice($level, $message): void
    {
        add_action('admin_notices', function () use ($level, $message) {
            echo $this->renderNotice((string) $level, (string) $message);
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in renderNotice()
        });
    }
    /**
     * Error-level notices are marked with a data-mollie-error attribute so tests
     * can tell Mollie errors apart from other notice-error containers.
     */
    public function renderNotice(string $level, string $message): string
    {
        $classes = preg_split('/\s+/', trim($level)) ?: [];
        $errorAttribute = in_array('notice-error', $classes, \true) ? ' data-mollie-error' : '';
        return '<div class="notice ' . esc_attr($level) . '"' . $errorAttribute . ' style="padding:12px 12px">' . wp_kses_post($message) . '</div>';
    }
}

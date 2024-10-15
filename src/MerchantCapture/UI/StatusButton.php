<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\MerchantCapture\UI;

class StatusButton
{
    public function __invoke(string $text, string $status)
    {
        ?>
        <mark class="order-status status-<?php echo esc_html($status); ?>"><span><?php echo esc_html($text); ?></span></mark>
        <?php
    }
}

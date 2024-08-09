<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Notice;

class AdminNotice implements NoticeInterface
{
    public function addNotice($level, $message): void
    {
        add_action(
            'admin_notices',
            static function () use ($level, $message) {
                ?>
                    <div class="notice <?php echo esc_attr($level) ?>" style="padding:12px 12px">
                        <?php echo wp_kses_post($message) ?>
                    </div>
                    <?php
            }
        );
    }
}

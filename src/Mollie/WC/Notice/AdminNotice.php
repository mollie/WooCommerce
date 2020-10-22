<?php

class Mollie_WC_Notice_AdminNotice
{
    public function addAdminNotice($level, $message)
    {
        add_action(
                'admin_notices',
                function () use ($level, $message) {
                    ?>
                    <div class="notice <?= esc_attr($level) ?>" style="padding:12px 12px">
                        <?= wp_kses_post($message) ?>
                    </div>
                    <?php
                }
        );
    }
}

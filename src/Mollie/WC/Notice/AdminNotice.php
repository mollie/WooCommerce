<?php

class Mollie_WC_Notice_AdminNotice
{
    public function addAdminNotice($level, $message)
    {
        add_action(
                'admin_notices',
                function () use ($level, $message) {
                    ?>
                    <div class="notice <?= esc_attr($level) ?>">
                        <?= wp_kses_post($message) ?>
                    </div>
                    <?php
                }
        );
    }
}

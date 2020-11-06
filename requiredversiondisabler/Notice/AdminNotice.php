<?php

namespace RequiredVersionDisabler\Notice;

/**
 * Class AdminNotice
 *
 * @package RequiredVersionDisabler\Notice
 */
class AdminNotice
{

    /**
     * @param string $level notice-warning
     * @param string $message
     */
    public function addAdminNotice($level, $message)
    {
        add_action(
            'admin_notices',
            function() use ($level, $message) {
                ?>
                <div class="notice <?= esc_attr($level) ?>">
                    <?= wp_kses_post($message) ?>
                </div>
                <?php
            }
        );
    }

}

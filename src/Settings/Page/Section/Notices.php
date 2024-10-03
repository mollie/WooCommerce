<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Settings\Page\Section;

class Notices extends AbstractSection
{
    public function config(): array
    {
        return [
                [
                    'id' => $this->settings->getSettingId('notices'),
                    'type' => 'mollie_content',
                    'value' => $this->content(),
                ],
        ];
    }

    protected function content(): string
    {
        ob_start();
        ?>
        NOTICES
        <?php
        return ob_get_clean();
    }
}

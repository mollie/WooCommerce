<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Notice;

class FrontendNotice implements \Mollie\WooCommerce\Notice\NoticeInterface
{
    public function addNotice($level, $message): void
    {
        wc_add_notice($message, $level);
    }
}

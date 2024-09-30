<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Settings\Page;

class PageAdvancedSettings extends AbstractPage {

    public function isTab(): bool
    {
        // TODO: Implement isTab() method.
    }

    public function slug(): string
    {
        return 'mollie_advanced';
    }
}

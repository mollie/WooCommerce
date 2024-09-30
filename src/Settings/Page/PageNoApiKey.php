<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Settings\Page;

use Mollie\WooCommerce\Settings\Page\Section\ConnectionFields;
use Mollie\WooCommerce\Settings\Page\Section\Header;
use Mollie\WooCommerce\Settings\Page\Section\Instructions;
use Mollie\WooCommerce\Settings\Page\Section\Notices;

class PageNoApiKey extends AbstractPage {
    public function isTab(): bool
    {
        // TODO: Implement isTab() method.
    }

    public function slug(): string
    {
        return 'mollie_no_api_key';
    }

    public function sections(): array
    {
        return [
            Header::class,
            Notices::class,
            Instructions::class,
            ConnectionFields::class
        ];
    }
}

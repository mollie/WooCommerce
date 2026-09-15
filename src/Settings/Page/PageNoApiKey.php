<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Settings\Page;

use Mollie\WooCommerce\Settings\Page\Section\ConnectionFields;
use Mollie\WooCommerce\Settings\Page\Section\Header;
use Mollie\WooCommerce\Settings\Page\Section\InstructionsNotConnected;
use Mollie\WooCommerce\Settings\Page\Section\Notices;
class PageNoApiKey extends \Mollie\WooCommerce\Settings\Page\AbstractPage
{
    public static function isTab(): bool
    {
        return \false;
    }
    public static function slug(): string
    {
        return 'mollie_no_api_key';
    }
    public function sections(): array
    {
        return [Header::class, Notices::class, InstructionsNotConnected::class, ConnectionFields::class];
    }
}

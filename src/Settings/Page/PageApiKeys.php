<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Settings\Page;

use Mollie\WooCommerce\Settings\Page\Section\ConnectionFields;
use Mollie\WooCommerce\Settings\Page\Section\Header;
use Mollie\WooCommerce\Settings\Page\Section\InstructionsNotConnected;
use Mollie\WooCommerce\Settings\Page\Section\InstructionsConnected;
use Mollie\WooCommerce\Settings\Page\Section\Notices;
use Mollie\WooCommerce\Settings\Page\Section\Tabs;
class PageApiKeys extends \Mollie\WooCommerce\Settings\Page\AbstractPage
{
    public static function isTab(): bool
    {
        return \true;
    }
    public static function slug(): string
    {
        return 'mollie_api_keys';
    }
    public static function tabName(): string
    {
        return __('API keys', 'mollie-payments-for-woocommerce');
    }
    public function sections(): array
    {
        return [Header::class, Notices::class, Tabs::class, InstructionsConnected::class, ConnectionFields::class];
    }
}

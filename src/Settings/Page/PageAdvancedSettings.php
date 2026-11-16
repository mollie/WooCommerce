<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Settings\Page;

use Mollie\WooCommerce\Settings\Page\Section\Advanced;
use Mollie\WooCommerce\Settings\Page\Section\ConnectionFields;
use Mollie\WooCommerce\Settings\Page\Section\Header;
use Mollie\WooCommerce\Settings\Page\Section\Notices;
use Mollie\WooCommerce\Settings\Page\Section\Tabs;
class PageAdvancedSettings extends \Mollie\WooCommerce\Settings\Page\AbstractPage
{
    public static function isTab(): bool
    {
        return \true;
    }
    public static function tabName(): string
    {
        return __('Advanced settings', 'mollie-payments-for-woocommerce');
    }
    public static function slug(): string
    {
        return 'mollie_advanced';
    }
    public function sections(): array
    {
        return [Header::class, Notices::class, Tabs::class, Advanced::class];
    }
}

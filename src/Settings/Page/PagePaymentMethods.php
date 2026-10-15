<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Settings\Page;

use Mollie\WooCommerce\Settings\Page\Section\ConnectionStatusFields;
use Mollie\WooCommerce\Settings\Page\Section\Header;
use Mollie\WooCommerce\Settings\Page\Section\Notices;
use Mollie\WooCommerce\Settings\Page\Section\PaymentMethods;
use Mollie\WooCommerce\Settings\Page\Section\Tabs;
class PagePaymentMethods extends \Mollie\WooCommerce\Settings\Page\AbstractPage
{
    public static function isTab(): bool
    {
        return \true;
    }
    public static function tabName(): string
    {
        return __('Payment methods', 'mollie-payments-for-woocommerce');
    }
    public static function slug(): string
    {
        return 'mollie_payment_methods';
    }
    public function sections(): array
    {
        return [Header::class, Notices::class, Tabs::class, ConnectionStatusFields::class, PaymentMethods::class];
    }
}

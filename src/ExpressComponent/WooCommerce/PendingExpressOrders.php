<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\ExpressComponent\WooCommerce;

use Mollie\WooCommerce\ExpressComponent\Rules\StartOrderDecision;
/**
 * An option, because the unpaid-orders schedule asks on every init.
 */
class PendingExpressOrders
{
    public const OPTION = 'mollie_express_orders_pending';
    public function mayExist(): bool
    {
        return get_option(self::OPTION, 'no') === 'yes';
    }
    public function remember(): void
    {
        if (!$this->mayExist()) {
            update_option(self::OPTION, 'yes', \true);
        }
    }
    public function recount(): void
    {
        $left = wc_get_orders(['limit' => 1, 'return' => 'ids', 'type' => 'shop_order', 'status' => ['pending'], 'created_via' => StartOrderDecision::CREATED_VIA]);
        if ($left === []) {
            delete_option(self::OPTION);
            return;
        }
        $this->remember();
    }
}

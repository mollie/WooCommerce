<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Payment\LineItems;

use WC_Order;
interface LineItemProvider
{
    public function order_lines(WC_Order $order): array;
}

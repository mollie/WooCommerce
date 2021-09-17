<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\PaymentMethods\PaymentFieldsStrategies;

use Mollie\WooCommerce\Payment\MollieObject;
use WC_Order;

interface PaymentRedirectStrategyI
{

    public function execute($gateway, WC_Order $order, MollieObject $paymentObject);
}

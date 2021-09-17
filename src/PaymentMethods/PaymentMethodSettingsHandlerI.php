<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\PaymentMethods;

interface PaymentMethodSettingsHandlerI
{
    public function getSettings(PaymentMethodI $paymentMethod);
}

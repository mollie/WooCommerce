<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Gateway\Paysafecard;

use Mollie\Api\Types\PaymentMethod;
use Mollie\WooCommerce\Gateway\AbstractGateway;

class Mollie_WC_Gateway_Paysafecard extends AbstractGateway
{
    /**
     * @return string
     */
    public function getMollieMethodId()
    {
        return PaymentMethod::PAYSAFECARD;
    }

    /**
     * @return string
     */
    public function getDefaultTitle()
    {
        return __('paysafecard', 'mollie-payments-for-woocommerce');
    }

    /**
     * @return string
     */
    protected function getSettingsDescription()
    {
        return '';
    }

    /**
     * @return string
     */
    protected function getDefaultDescription()
    {
        return '';
    }
}

<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\PaymentMethods\PaymentFieldsStrategies;

use Mollie\WooCommerce\Plugin;

class KbcFieldsStrategy implements PaymentFieldsStrategyI
{
    use IssuersDropdownBehavior;

    public function execute($gateway, $dataHelper)
    {
        if ($gateway->paymentMethod->getProperty('issuers_dropdown_shown') !== 'yes') {
            return;
        }

        $issuers = $this->getIssuers($gateway, $dataHelper);

        $selectedIssuer = $gateway->getSelectedIssuer();

        $this->renderIssuers($gateway, $issuers, $selectedIssuer);
    }
}

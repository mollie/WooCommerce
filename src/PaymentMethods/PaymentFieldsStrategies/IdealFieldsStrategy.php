<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\PaymentMethods\PaymentFieldsStrategies;

class IdealFieldsStrategy implements PaymentFieldsStrategyI
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
    public function getFieldMarkup($gateway, $dataHelper)
    {
        if ($gateway->paymentMethod->getProperty('issuers_dropdown_shown') !== 'yes') {
            return "";
        }
        $issuers = $this->getIssuers($gateway, $dataHelper);
        $selectedIssuer = $gateway->getSelectedIssuer();
        $markup = $this->issuersDropdownMarkup($gateway, $issuers, $selectedIssuer);
        return $markup;
    }
}

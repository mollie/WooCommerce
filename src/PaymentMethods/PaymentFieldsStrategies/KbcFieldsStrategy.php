<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\PaymentMethods\PaymentFieldsStrategies;

class KbcFieldsStrategy implements PaymentFieldsStrategyI
{
    use IssuersDropdownBehavior;

    public function execute($gateway, $dataHelper): string
    {
        if (!$this->dropDownEnabled($gateway)) {
            return '';
        }

        $issuers = $this->getIssuers($gateway, $dataHelper);

        $selectedIssuer = $gateway->paymentMethod()->getSelectedIssuer();

        return $this->renderIssuers($gateway, $issuers, $selectedIssuer);
    }

    public function getFieldMarkup($gateway, $dataHelper)
    {
        if (!$this->dropDownEnabled($gateway)) {
            return "";
        }
        $issuers = $this->getIssuers($gateway, $dataHelper);
        $selectedIssuer = $gateway->paymentMethod()->getSelectedIssuer();
        $markup = $this->dropdownOptions($gateway, $issuers, $selectedIssuer);
        return $markup;
    }
}

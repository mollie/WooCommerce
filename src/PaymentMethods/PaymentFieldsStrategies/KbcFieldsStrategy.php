<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\PaymentMethods\PaymentFieldsStrategies;

class KbcFieldsStrategy implements PaymentFieldsStrategyI
{
    use IssuersDropdownBehavior;

    public function execute($deprecatedHelperGateway, $gatewayDescription, $dataHelper): string
    {
        if (!$this->dropDownEnabled($deprecatedHelperGateway)) {
            return '';
        }

        $issuers = $this->getIssuers($deprecatedHelperGateway, $dataHelper);

        $selectedIssuer = $deprecatedHelperGateway->paymentMethod()->getSelectedIssuer();

        return $this->renderIssuers($deprecatedHelperGateway, $issuers, $selectedIssuer);
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

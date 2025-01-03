<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\PaymentMethods\PaymentFieldsStrategies;

use Inpsyde\PaymentGateway\PaymentFieldsRendererInterface;

class KbcFieldsStrategy extends AbstractPaymentFieldsRenderer implements PaymentFieldsRendererInterface
{
    use IssuersDropdownBehavior;

    public function renderFields(): string
    {
        if (!$this->dropDownEnabled($this->deprecatedHelperGateway)) {
            return '';
        }

        $issuers = $this->getIssuers($this->deprecatedHelperGateway, $this->dataHelper);

        $selectedIssuer = $this->deprecatedHelperGateway->paymentMethod()->getSelectedIssuer();

        return $this->renderIssuers($this->deprecatedHelperGateway, $issuers, $selectedIssuer);
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

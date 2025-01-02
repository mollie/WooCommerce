<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\PaymentMethods\PaymentFieldsStrategies;

class GiftcardFieldsStrategy implements PaymentFieldsStrategyI
{
    use IssuersDropdownBehavior;

    public function execute($deprecatedHelperGateway, $gatewayDescription, $dataHelper): string
    {
        if (!$this->dropDownEnabled($deprecatedHelperGateway)) {
            return $gatewayDescription;
        }

        $issuers = $this->getIssuers($deprecatedHelperGateway, $dataHelper);
        if (empty($issuers)) {
            return $gatewayDescription;
        }
        $selectedIssuer = $deprecatedHelperGateway->paymentMethod()->getSelectedIssuer();

        $html = '';

        // If only one gift card issuers is available, show it without a dropdown
        if (count($issuers) === 1) {
            $issuer = $issuers[0];
            $issuerImageSvg = $this->checkSvgIssuers($issuers);
            if ($issuerImageSvg && isset($issuer->name)) {
                $issuerImageSvg = esc_url($issuerImageSvg);
                $issuerName = esc_html($issuer->name);
                $html .= '<img src="' . $issuerImageSvg . '" style="vertical-align:middle" />' . $issuerName;
            }
            //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            return wpautop(wptexturize($html));
        }

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

    /**
     * @param $issuers
     */
    protected function checkSvgIssuers($issuers): string
    {
        if (!isset($issuers[0]) || ! is_object($issuers[0])) {
            return '';
        }
        $image = property_exists($issuers[0], 'image') && $issuers[0]->image !== null ? $issuers[0]->image : null;
        if (!$image) {
            return '';
        }
        return property_exists($image, 'svg') && $image->svg !== null && is_string($image->svg) ? $image->svg : '';
    }
}

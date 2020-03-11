<?php

use Mollie\Api\Types\PaymentMethod;

class Mollie_WC_Helper_PaymentMethodsIconUrl
{
    /**
     * @var array
     */
    private $paymentMethodImages;

    /**
     * PaymentMethodIconUrl constructor.
     * @param array $paymentMethodImages
     */
    public function __construct(array $paymentMethodImages)
    {
        $this->paymentMethodImages = $paymentMethodImages;
    }

    /**
     * @param string $paymentMethodName
     * @return mixed
     */
    public function svgUrlForPaymentMethod($paymentMethodName)
    {
        return isset($this->paymentMethodImages[$paymentMethodName]->svg)
            ? $this->paymentMethodImages[$paymentMethodName]->svg
            : $this->fallToAssets($paymentMethodName);
    }

    /**
     * @param string $paymentMethodName
     * @return string
     */
    protected function fallToAssets($paymentMethodName)
    {
        if ( $paymentMethodName == PaymentMethod::CREDITCARD  && !is_admin()) {
            return Mollie_WC_Plugin::getPluginUrl('public/images/' . $paymentMethodName . 's.svg');
        }

        return Mollie_WC_Plugin::getPluginUrl('public/images/' . $paymentMethodName . '.svg');
    }
}


<?php

use Mollie\Api\Types\PaymentMethod;

class Mollie_WC_Helper_PaymentMethodsIconUrl
{
    const MOLLIE_CREDITCARD_ICONS = 'mollie_creditcard_icons_';
    const AVAILABLE_CREDITCARD_ICONS = [
            'amex',
            'cartasi',
            'cartebancaire',
            'maestro',
            'mastercard',
            'visa',
            'vpay'
        ];
    const SVG_FILE_EXTENSION = '.svg';
    const CREDIT_CARD_ICON_WIDTH = 33;
    const MOLLIE_CREDITCARD_ICONS_ENABLER = 'mollie_creditcard_icons_enabler';
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
     * Method that returns the url to the svg icon url
     * In case of credit cards, if the settings is enabled, the svg has to be
     * composed
     *
     * @param string $paymentMethodName
     *
     * @return mixed
     */
    public function svgUrlForPaymentMethod($paymentMethodName)
    {
        return isset($this->paymentMethodImages[$paymentMethodName]->svg)
            ? $this->getSvgImageFromUrl($paymentMethodName)
            : $this->fallToAssets($paymentMethodName);
    }

    /**
     * Method to retrieve the Svg image from the url given and add the style
     *
     * @param $paymentMethodName
     *
     * @return string
     */
    protected function getSvgImageFromUrl($paymentMethodName)
    {
        $request = wp_safe_remote_get($this->paymentMethodImages[$paymentMethodName]->svg);
        if(is_wp_error($request)){
            return $this->fallToAssets($paymentMethodName);
        }
        $svgString = wp_remote_retrieve_body($request);
        $svgString = $this->styleSvgImage($svgString);

        return $svgString;
    }

    /**
     * @param string $paymentMethodName
     * @return string
     */
    protected function fallToAssets($paymentMethodName)
    {
        if ($paymentMethodName == PaymentMethod::CREDITCARD && !is_admin()) {
            return Mollie_WC_Plugin::getPluginUrl(
                "public/images/{$paymentMethodName}s.svg"
            );
        }
        $svgUrl = Mollie_WC_Plugin::getPluginUrl(
            "public/images/{$paymentMethodName}" . self::SVG_FILE_EXTENSION
        );

        return '<img src="' . esc_attr($svgUrl)
            . '" style="width: 25px; vertical-align: bottom;" />';
    }

    /**
     * @param string $resource
     *
     * @return string
     */
    protected function styleSvgImage($resource)
    {
        if (!is_string($resource)) {
            return '';
        }
        return substr_replace($resource, " style=\"float:right\" ", 4, 0);
    }
}


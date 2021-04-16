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
     * PaymentMethodIconUrl constructor.
     *
     */
    public function __construct()
    {
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
        if ($paymentMethodName == PaymentMethod::CREDITCARD && !is_admin()) {
            return Mollie_WC_Plugin::getPluginUrl(
                "public/images/{$paymentMethodName}s.svg"
            );
        }

        $svgPath = false;
        $gatewaySettings = get_option("mollie_wc_gateway_{$paymentMethodName}_settings", false);

        if($gatewaySettings){
            $svgPath = isset($gatewaySettings["iconFilePath"])?$gatewaySettings["iconFilePath"]:false;
            $svgUrl =  isset($gatewaySettings["iconFileUrl"])?$gatewaySettings["iconFileUrl"]:false;
        }

        if(! file_exists( $svgPath ) || !$svgUrl){
            $svgUrl = Mollie_WC_Plugin::getPluginUrl(
                "public/images/{$paymentMethodName}" . self::SVG_FILE_EXTENSION
            );
        }

        return '<img src="' . esc_attr($svgUrl)
            . '" class="mollie-gateway-icon" />';
    }
}


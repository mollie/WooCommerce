<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Utils;

use Mollie\Api\Types\PaymentMethod;
use Mollie\WooCommerce\Plugin;

class PaymentMethodsIconUrl
{
    const MOLLIE_CREDITCARD_ICONS = 'mollie_creditcard_icons_';
    const AVAILABLE_CREDITCARD_ICONS = [
            'amex',
            'cartasi',
            'cartebancaire',
            'maestro',
            'mastercard',
            'visa',
            'vpay',
        ];
    const SVG_FILE_EXTENSION = '.svg';
    const CREDIT_CARD_ICON_WIDTH = 33;
    const MOLLIE_CREDITCARD_ICONS_ENABLER = 'mollie_creditcard_icons_enabler';
    /**
     * @var string
     */
    protected $pluginUrl;

    /**
     * PaymentMethodIconUrl constructor.
     *
     */
    public function __construct(string $pluginUrl)
    {
        $this->pluginUrl = $pluginUrl;
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
            return $this->pluginUrl . '/' . "public/images/{$paymentMethodName}s.svg";
        }

        $svgPath = false;
        $svgUrl = false;
        $gatewaySettings = get_option("mollie_wc_gateway_{$paymentMethodName}_settings", false);

        if ($gatewaySettings) {
            $svgPath = isset($gatewaySettings["iconFilePath"])?$gatewaySettings["iconFilePath"]:false;
            $svgUrl =  isset($gatewaySettings["iconFileUrl"])?$gatewaySettings["iconFileUrl"]:false;
        }

        if ($svgPath && !file_exists($svgPath) || !$svgUrl) {
            $svgUrl = $this->pluginUrl . '/' . "public/images/{$paymentMethodName}" . self::SVG_FILE_EXTENSION;
        }

        return '<img src="' . esc_attr($svgUrl)
            . '" class="mollie-gateway-icon" />';
    }
}

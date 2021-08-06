<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Utils;

use Mollie\Api\Types\PaymentMethod;
use Mollie\WooCommerce\Plugin;

class PaymentMethodsIconUrl
{
    /**
     * @var string
     */
    const MOLLIE_CREDITCARD_ICONS = 'mollie_creditcard_icons_';
    /**
     * @var string[]
     */
    const AVAILABLE_CREDITCARD_ICONS = [
            'amex',
            'cartasi',
            'cartebancaire',
            'maestro',
            'mastercard',
            'visa',
            'vpay',
        ];
    /**
     * @var string
     */
    const SVG_FILE_EXTENSION = '.svg';
    /**
     * @var int
     */
    const CREDIT_CARD_ICON_WIDTH = 33;
    /**
     * @var string
     */
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
            return $this->pluginUrl . '/' . sprintf('public/images/%ss.svg', $paymentMethodName);
        }

        $svgPath = false;
        $svgUrl = false;
        $gatewaySettings = get_option(sprintf('mollie_wc_gateway_%s_settings', $paymentMethodName), false);

        if ($gatewaySettings) {
            $svgPath = isset($gatewaySettings["iconFilePath"])?$gatewaySettings["iconFilePath"]:false;
            $svgUrl =  isset($gatewaySettings["iconFileUrl"])?$gatewaySettings["iconFileUrl"]:false;
        }

        if ($svgPath && !file_exists($svgPath) || !$svgUrl) {
            $svgUrl = $this->pluginUrl . '/' . sprintf('public/images/%s', $paymentMethodName) . self::SVG_FILE_EXTENSION;
        }

        return '<img src="' . esc_attr($svgUrl)
            . '" class="mollie-gateway-icon" />';
    }
}

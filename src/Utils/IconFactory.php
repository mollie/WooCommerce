<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Utils;

class IconFactory
{

    public $id;

    public function initIcon($gateway, $displayLogo, string $pluginUrl, string $pluginPath)
    {
        if ($displayLogo) {
            $default_icon = $this->getIconUrl($gateway->paymentMethod->getProperty('id'), $pluginUrl, $pluginPath);
            $gateway->icon = apply_filters($gateway->id . '_icon_url', $default_icon);
        }
    }

    /**
     * @return string
     */
    public function getIconUrl($gatewayId, $pluginUrl, $pluginPath)
    {
        return $this->iconFactory($pluginUrl, $pluginPath)->svgUrlForPaymentMethod($gatewayId);
    }

    /**
     * Singleton of the class that handles icons (API/fallback)
     * @return PaymentMethodsIconUrl|null
     */
    public function iconFactory(string $pluginUrl, string $pluginPath)
    {
        static $factory = null;
        if ($factory === null) {
            $factory = new PaymentMethodsIconUrl($pluginUrl, $pluginPath);
        }

        return $factory;
    }


}

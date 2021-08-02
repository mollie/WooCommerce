<?php


namespace Mollie\WooCommerce\Utils;


class IconFactory
{

	/**
	 * IconFactory constructor.
	 */
	public function __construct()
	{
	}

    public function initIcon ($gateway, $displayLogo)
    {
        if ($displayLogo)
        {
            $default_icon = $this->getIconUrl($gateway->getMollieMethodId());
            $gateway->icon   = apply_filters($gateway->id . '_icon_url', $default_icon);
        }
    }

    /**
     * @return string
     */
    public function getIconUrl($gatewayId)
    {
        return $this->iconFactory()->svgUrlForPaymentMethod($gatewayId);
    }

    /**
     * Singleton of the class that handles icons (API/fallback)
     * @return PaymentMethodsIconUrl|null
     */
    public function iconFactory()
    {
        static $factory = null;
        if ($factory === null){
            $factory = new PaymentMethodsIconUrl();
        }

        return $factory;
    }
}

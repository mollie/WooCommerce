<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\PaymentMethods;

class IconFactory
{
    /** @var mixed */
    protected $pluginUrl;
    /** @var mixed */
    protected $pluginPath;
    /**
     * IconFactory constructor.
     *
     * @param mixed $pluginUrl
     * @param mixed $pluginPath
     */
    public function __construct($pluginUrl, $pluginPath)
    {
        $this->pluginUrl = $pluginUrl;
        $this->pluginPath = $pluginPath;
    }
    /**
     * @return array
     */
    public function getIconUrl($paymentMethodName): array
    {
        return $this->iconFactory()->svgUrlForPaymentMethod($paymentMethodName);
    }
    /**
     * Singleton of the class that handles icons (API/fallback)
     * @return PaymentMethodsIconUrl|null
     */
    public function iconFactory(): ?\Mollie\WooCommerce\PaymentMethods\PaymentMethodsIconUrl
    {
        static $factory = null;
        if ($factory === null) {
            $factory = new \Mollie\WooCommerce\PaymentMethods\PaymentMethodsIconUrl($this->pluginUrl, $this->pluginPath);
        }
        return $factory;
    }
}

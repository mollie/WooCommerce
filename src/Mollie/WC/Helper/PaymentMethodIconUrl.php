<?php

use Mollie\Api\Types\PaymentMethod;

class Mollie_WC_Helper_PaymentMethodIconUrl
{
    /**
     * @var null
     */
    private static $instance = null;
    /**
     * @var array
     */
    private $paymentMethodImages;

    /**
     * PaymentMethodIconUrl constructor.
     * @param array $paymentMethodImages
     */
    private function __construct(array $paymentMethodImages)
    {
        $this->paymentMethodImages = $paymentMethodImages;
    }

    /**
     * @param $paymentMethodImages
     * @return Mollie_WC_Helper_PaymentMethodIconUrl|null
     */
    public static function getInstance($paymentMethodImages)
    {
        if (self::$instance == null)
        {
            self::$instance = new Mollie_WC_Helper_PaymentMethodIconUrl($paymentMethodImages);
        }

        return self::$instance;
    }

    /**
     * @param string $paymentMethodName
     * @return mixed
     */
    public function svgUrlForPaymentMethod(string $paymentMethodName)
    {
        return $this->paymentMethodImages? $this->paymentMethodImages[$paymentMethodName]['images']['svg']: $this->fallToAssets($paymentMethodName);
    }

    /**
     * @param string $paymentMethodName
     * @return string
     */
    public function fallToAssets(string $paymentMethodName)
    {
        if ( $paymentMethodName == PaymentMethod::CREDITCARD  && !is_admin()) {
            return Mollie_WC_Plugin::getPluginUrl('assets/images/' . $paymentMethodName . 's.svg');
        }
        return Mollie_WC_Plugin::getPluginUrl('assets/images/' . $paymentMethodName . '.svg');
    }
}


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
        //If is creditcard and we have the options we create the composite, then we return the url of the created image
        //if we dont have selected options we take the fallToAssets creditcard
        if ( $paymentMethodName == PaymentMethod::CREDITCARD  && !is_admin()) {
            return $this->enabledCreditcardOptions() ? $this->composeSvgImage(
                $this->enabledCreditcards()
            ) : $this->fallToAssets($paymentMethodName);
        }
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

    /**
     * @return string
     */
    protected function composeSvgImage($enabledCreditCards)
    {
        $this->writeToSvgFile();

        $actual = $this->buildSvgComposed($enabledCreditCards);

        return  $this->writeToSvgFile($actual);
    }

    private function enabledCreditcardOptions()
    {
        return true;
    }

    private function enabledCreditcards()
    {
        return [
            'visa.svg','maestro.svg','amex.svg'
        ];
    }

    /**
     * Method to write the svg file with the composed svg
     * Can be used to reset the file to empty
     * @param string $content svg content to compose the image if empty will
     *                        reset the file
     *
     * @return string the url to the written file
     */
    protected function writeToSvgFile($content = '')
    {
        $uploads = wp_upload_dir();
        file_put_contents(
            $uploads['basedir'] . '/compositeCards.svg',
            $content
        );
        return $uploads['baseurl'].'/compositeCards.svg';
    }

    /**
     * @param array $enabledCreditCards Array of the selected cards to compose
     *                                  the image with
     *
     * @return string Newly composed svg string
     */
    protected function buildSvgComposed($enabledCreditCards)
    {
        $assetsImagesPath = '/app/public/wp-content/plugins/WooCommerce-Mollie/public/images/Creditcard_issuers/';
        $cardWidth = 33;
        $cardsNumber = count($enabledCreditCards);
        $cardsWidth = $cardWidth * $cardsNumber;
        $cardPositionX = 0;
        $actual
            = "<svg width=\"{$cardsWidth}\" height=\"24\" xmlns=\"http://www.w3.org/2000/svg\" xmlns:xlink=\"http://www.w3.org/1999/xlink\">";
        foreach ($enabledCreditCards as $creditCard) {
            $actual .= $this->positionSvgOnX($cardPositionX, file_get_contents($assetsImagesPath . $creditCard));
            $cardPositionX += $cardWidth;
        }
        $actual .= "</svg>";

        return $actual;
    }
    protected function positionSvgOnX($xPosition, $svgString)
    {
        $positionString = " x=\"{$xPosition}\"";
        $positionAfterSvgWord = 4;

        return substr_replace($svgString, $positionString, $positionAfterSvgWord, 0);
    }
}


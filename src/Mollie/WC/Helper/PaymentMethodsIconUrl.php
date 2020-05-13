<?php

use Mollie\Api\Types\PaymentMethod;

class Mollie_WC_Helper_PaymentMethodsIconUrl
{
    const MOLLIE_CREDITCARD_ICONS = 'mollie_creditcard_icons_';
    const AVAILABLE_CREDITCARD_ICONS
        = [
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
        if ( $paymentMethodName == PaymentMethod::CREDITCARD  && !is_admin()) {
            if($this->enabledCreditcardOptions()){
                return $this->composeSvgImage($this->enabledCreditcards());
            }
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

        return Mollie_WC_Plugin::getPluginUrl('public/images/' . $paymentMethodName . self::SVG_FILE_EXTENSION
        );
    }

    /**
     * Method that handles the composition of the new composed svg file
     * and returns its url
     *
     * @param array $enabledCreditCards Array with the enabled creditcards
     *
     * @return string the url to the composed svg
     */
    protected function composeSvgImage($enabledCreditCards)
    {
        $this->writeToSvgFile();

        $actual = $this->buildSvgComposed($enabledCreditCards);

        return  $this->writeToSvgFile($actual);
    }

    /**
     * Is the customization of credit card icons enabled?
     *
     * @return bool
     */
    private function enabledCreditcardOptions()
    {
        $creditcardSettings = get_option(
            'mollie_wc_gateway_creditcard_settings'
        );
        if (!isset($creditcardSettings['mollie_creditcard_icons_enabler'])) {
            return false;
        }
        return wc_string_to_bool(
            $creditcardSettings['mollie_creditcard_icons_enabler']
        );
    }

    /**
     * @return array Array containing the credit cards names enabled in settings
     *               to make customization of checkout icons
     */
    private function enabledCreditcards()
    {
        $optionLexem = self::MOLLIE_CREDITCARD_ICONS;
        $creditcardsAvailable = self::AVAILABLE_CREDITCARD_ICONS;
        $svgFileName = self::SVG_FILE_EXTENSION;
        $enabledCreditcards = [];

        $creditcardSettings = get_option('mollie_wc_gateway_creditcard_settings');
        foreach ($creditcardsAvailable as $card) {
            if ($creditcardSettings[$optionLexem . $card] === 'yes') {
                $enabledCreditcards[] = $card . $svgFileName;
            }
        }

        return $enabledCreditcards;
    }

    /**
     * Method to write the svg file with the composed svg
     * Can be used to reset the file to empty
     *
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
        return $uploads['baseurl'] . '/compositeCards.svg';
    }

    /**
     * @param array $enabledCreditCards Array of the selected cards to compose
     *                                  the image with
     *
     * @return string Newly composed svg string
     */
    protected function buildSvgComposed($enabledCreditCards)
    {
        $assetsImagesPath
            = '/app/public/wp-content/plugins/WooCommerce-Mollie/public/images/Creditcard_issuers/';
        $cardWidth = self::CREDIT_CARD_ICON_WIDTH;
        $cardsNumber = count($enabledCreditCards);
        $cardsWidth = $cardWidth * $cardsNumber;
        $cardPositionX = 0;
        $actual
            = "<svg width=\"{$cardsWidth}\" height=\"24\" xmlns=\"http://www.w3.org/2000/svg\" xmlns:xlink=\"http://www.w3.org/1999/xlink\">";
        foreach ($enabledCreditCards as $creditCard) {
            $actual .= $this->positionSvgOnX(
                $cardPositionX,
                file_get_contents(
                    $assetsImagesPath . $creditCard
                )
            );
            $cardPositionX += $cardWidth;
        }
        $actual .= "</svg>";

        return $actual;
    }

    /**
     * Method to add the x parameter to the svg string so that the icon can
     * be positioned related to other icons.
     *
     * @param int    $xPosition coordinate to position icon on x axis
     * @param string $svgString svg string to add position to
     *
     * @return string|string[] Modified svg string with the x position added
     */
    protected function positionSvgOnX($xPosition, $svgString)
    {
        $positionString = " x=\"{$xPosition}\"";
        $positionAfterSvgWord = 4;

        return substr_replace($svgString, $positionString, $positionAfterSvgWord, 0);
    }
}


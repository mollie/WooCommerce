<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\PaymentMethods;

use Mollie\Api\Types\PaymentMethod;
use Mollie\WooCommerce\Plugin;

class PaymentMethodsIconUrl
{
    /**
     * @var string
     */
    public const MOLLIE_CREDITCARD_ICONS = 'mollie_creditcard_icons_';
    /**
     * @var string[]
     */
    public const AVAILABLE_CREDITCARD_ICONS = [
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
    public const SVG_FILE_EXTENSION = '.svg';
    /**
     * @var int
     */
    public const CREDIT_CARD_ICON_WIDTH = 33;
    /**
     * @var string
     */
    public const MOLLIE_CREDITCARD_ICONS_ENABLER = 'mollie_creditcard_icons_enabler';
    /**
     * @var string
     */
    protected $pluginUrl;
    /**
     * @var string
     */
    protected $pluginPath;

    /**
     * PaymentMethodIconUrl constructor.
     *
     */
    public function __construct(string $pluginUrl, string $pluginPath)
    {
        $this->pluginUrl = $pluginUrl;
        $this->pluginPath = $pluginPath;
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
        if ($paymentMethodName === PaymentMethod::CREDITCARD && !is_admin()) {
            return $this->getCreditcardIcon();
        }

        $svgPath = false;
        $svgUrl = false;
        $gatewaySettings = get_option(sprintf('mollie_wc_gateway_%s_settings', $paymentMethodName), false);

        if ($gatewaySettings) {
            $svgPath = isset($gatewaySettings["iconFilePath"]) ? $gatewaySettings["iconFilePath"] : false;
            $svgUrl =  isset($gatewaySettings["iconFileUrl"]) ? $gatewaySettings["iconFileUrl"] : false;
        }

        if ($svgPath && !file_exists($svgPath) || !$svgUrl) {
            $svgUrl = $this->pluginUrl . '/' . sprintf('public/images/%s', $paymentMethodName) . self::SVG_FILE_EXTENSION;
        }

        return '<img src="' . esc_url($svgUrl)
            . '" class="mollie-gateway-icon" />';
    }

    public function getCreditcardIcon()
    {
        if (
            $this->enabledCreditcards()
            && !is_admin()
        ) {
            return $this->buildSvgComposed() ?: '';
        }
        $gatewaySettings = get_option('mollie_wc_gateway_creditcard_settings', false);
        if ($this->canShowCustomLogo($gatewaySettings)) {
            $url =  $gatewaySettings["iconFileUrl"];
            return '<img src="' . esc_url($url)
                . '" class="mollie-gateway-icon" />';
        }
        $svgUrl = $this->pluginUrl . sprintf('public/images/%ss.svg', PaymentMethod::CREDITCARD);
        return
            '<img src="' . esc_url($svgUrl)
            . '" class="mollie-gateway-icon" />';
    }

    protected function canShowCustomLogo($gatewaySettings): bool
    {
        if (!$gatewaySettings) {
            return false;
        }
        if (
            !isset($gatewaySettings['enable_custom_logo'])
            || $gatewaySettings['enable_custom_logo'] !== 'yes'
        ) {
            return false;
        }
        if (
            !isset($gatewaySettings['iconFileUrl'])
            && !is_string(
                $gatewaySettings['iconFileUrl']
            )
        ) {
            return false;
        }
        if (!isset($gatewaySettings["iconFilePath"])) {
            return false;
        }
        $svgPath = $gatewaySettings["iconFilePath"];
        return file_exists($svgPath);
    }
    /**
     * @return array Array containing the credit cards names enabled in settings
     *               to make customization of checkout icons
     */
    protected function enabledCreditcards(): array
    {
        $optionLexem = PaymentMethodsIconUrl::MOLLIE_CREDITCARD_ICONS;
        $creditcardsAvailable = PaymentMethodsIconUrl::AVAILABLE_CREDITCARD_ICONS;
        $svgFileName = PaymentMethodsIconUrl::SVG_FILE_EXTENSION;
        $iconEnabledOption = PaymentMethodsIconUrl::MOLLIE_CREDITCARD_ICONS_ENABLER;
        $creditCardSettings = get_option('mollie_wc_gateway_creditcard_settings', false) ?: [];
        $enabled = isset($creditCardSettings[$iconEnabledOption])
            ? mollieWooCommerceStringToBoolOption($creditCardSettings[$iconEnabledOption])
            : false;

        if (!$enabled) {
            return [];
        }

        $enabledCreditcards = [];

        $creditcardSettings = get_option('mollie_wc_gateway_creditcard_settings', []);
        foreach ($creditcardsAvailable as $card) {
            if (isset($creditcardSettings[$optionLexem . $card]) && mollieWooCommerceStringToBoolOption($creditcardSettings[$optionLexem . $card])) {
                $enabledCreditcards[] = $card . $svgFileName;
            }
        }

        return $enabledCreditcards;
    }
    /**
     *
     * @return string Newly composed svg string
     */
    public function buildSvgComposed()
    {
        $enabledCreditCards = $this->enabledCreditcards();

        $assetsImagesPath = $this->pluginPath . '/' . 'public/images/';
        $cardWidth = PaymentMethodsIconUrl::CREDIT_CARD_ICON_WIDTH;
        $cardsNumber = count($enabledCreditCards);
        $cardsWidth = $cardWidth * $cardsNumber;
        $cardPositionX = 0;
        $actual = get_transient('svg_creditcards_string');
        if (!$actual) {
            $actual = sprintf(
                '<svg width="%s" height="24" class="mollie-gateway-icon" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">',
                $cardsWidth
            );
            foreach ($enabledCreditCards as $creditCard) {
                $svgString = file_get_contents(
                    $assetsImagesPath . $creditCard
                );
                if ($svgString) {
                    $actual .= $this->positionSvgOnX(
                        $cardPositionX,
                        $svgString
                    );
                    $cardPositionX += $cardWidth;
                }
            }
            $actual .= "</svg>";
            set_transient('svg_creditcards_string', $actual, DAY_IN_SECONDS);
        }
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
        $positionString = sprintf(' x="%s"', $xPosition);
        $positionAfterSvgWord = 4;

        return substr_replace($svgString, $positionString, $positionAfterSvgWord, 0);
    }
}

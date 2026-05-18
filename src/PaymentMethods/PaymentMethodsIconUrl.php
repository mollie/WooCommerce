<?php

declare (strict_types=1);
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
    public const AVAILABLE_CREDITCARD_ICONS = ['amex', 'cartasi', 'cartebancaire', 'maestro', 'mastercard', 'visa', 'vpay'];
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
     * @return array|string[]
     */
    public function svgUrlForPaymentMethod(string $paymentMethodName)
    {
        if ($paymentMethodName === PaymentMethod::CREDITCARD && !is_admin()) {
            return $this->getCreditcardIcon();
        }
        $svgUrl = $this->pluginUrl . sprintf('public/images/%s', $paymentMethodName) . self::SVG_FILE_EXTENSION;
        return [$svgUrl];
    }
    public function getCreditcardIcon(): array
    {
        if ($this->enabledCreditcards() && !is_admin()) {
            return $this->buildSvgComposed() ?: [];
        }
        $gatewaySettings = get_option('mollie_wc_gateway_creditcard_settings', \false);
        if ($this->canShowCustomLogo($gatewaySettings)) {
            $url = $gatewaySettings["iconFileUrl"];
            return [esc_url($url)];
        }
        $svgUrl = $this->pluginUrl . sprintf('public/images/%ss.svg', PaymentMethod::CREDITCARD);
        return [$svgUrl];
    }
    protected function canShowCustomLogo($gatewaySettings): bool
    {
        if (!$gatewaySettings) {
            return \false;
        }
        if (!isset($gatewaySettings['enable_custom_logo']) || $gatewaySettings['enable_custom_logo'] !== 'yes') {
            return \false;
        }
        if (!isset($gatewaySettings['iconFileUrl']) || !is_string($gatewaySettings['iconFileUrl'])) {
            return \false;
        }
        if (!isset($gatewaySettings["iconFilePath"])) {
            return \false;
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
        $optionLexem = \Mollie\WooCommerce\PaymentMethods\PaymentMethodsIconUrl::MOLLIE_CREDITCARD_ICONS;
        $creditcardsAvailable = \Mollie\WooCommerce\PaymentMethods\PaymentMethodsIconUrl::AVAILABLE_CREDITCARD_ICONS;
        $svgFileName = \Mollie\WooCommerce\PaymentMethods\PaymentMethodsIconUrl::SVG_FILE_EXTENSION;
        $iconEnabledOption = \Mollie\WooCommerce\PaymentMethods\PaymentMethodsIconUrl::MOLLIE_CREDITCARD_ICONS_ENABLER;
        $creditCardSettings = get_option('mollie_wc_gateway_creditcard_settings', \false) ?: [];
        $enabled = isset($creditCardSettings[$iconEnabledOption]) ? mollieWooCommerceStringToBoolOption($creditCardSettings[$iconEnabledOption]) : \false;
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
     * @return array Newly composed svg string
     */
    public function buildSvgComposed(): array
    {
        $enabledCreditCards = $this->enabledCreditcards();
        $assetsImagesPath = $this->pluginUrl . '/' . 'public/images/';
        $inconsUrlArray = [];
        foreach ($enabledCreditCards as $creditCard) {
            $inconsUrlArray[] = $assetsImagesPath . $creditCard;
        }
        return $inconsUrlArray;
    }
}

<?php

namespace Mollie\WooCommerce\Gateway\Creditcard;

use Mollie\Api\Resources\Payment;
use Mollie\Api\Types\PaymentMethod;
use Mollie\WooCommerce\Gateway\PaymentService;
use Mollie\WooCommerce\Gateway\SurchargeService;
use Mollie\WooCommerce\Notice\NoticeInterface;
use Mollie\WooCommerce\Payment\MollieOrderService;
use Mollie\WooCommerce\Plugin;
use Mollie\WooCommerce\Subscription\AbstractSubscription;
use Mollie\WooCommerce\Utils\IconFactory;
use Mollie\WooCommerce\Utils\PaymentMethodsIconUrl;
use Psr\Log\LoggerInterface as Logger;
use WC_Order;

class Mollie_WC_Gateway_Creditcard extends AbstractSubscription
{
    public function __construct(
        IconFactory $iconFactory,
        PaymentService $paymentService,
        SurchargeService $surchargeService,
        MollieOrderService $mollieOrderService,
        Logger $logger,
        NoticeInterface $notice
    ) {
        $this->supports = [
            'products',
            'refunds',
        ];

        $this->initSubscriptionSupport();

        $this->hasFieldsIfMollieComponentsIsEnabled();
        parent::__construct(
            $iconFactory,
            $paymentService,
            $surchargeService,
            $mollieOrderService,
            $logger,
            $notice
        );
    }

    public function get_icon()
    {
        $url = Plugin::getPluginUrl(
            "public/images/creditcard.svg"
        );
        $localAsset = '<img src="' . esc_attr($url)
            . '" class="mollie-gateway-icon" />';
        $output = $this->icon ? $localAsset : '';
        if ($this->enabledCreditcards()
            && !is_admin()
        ) {
            $output = $this->buildSvgComposed() ?: '';
        }
        $gatewaySettings = $this->settings;
        if ($this->canShowCustomLogo($gatewaySettings)) {
            $url =  $gatewaySettings["iconFileUrl"];
            $output = '<img src="' . esc_attr($url)
                . '" class="mollie-gateway-icon" />';
        }

        return apply_filters('woocommerce_gateway_icon', $output, $this->id);
    }

    protected function canShowCustomLogo($gatewaySettings)
    {
        if (!$gatewaySettings) {
            return false;
        }
        if (!isset($gatewaySettings['enable_custom_logo'])
            || $gatewaySettings['enable_custom_logo'] !== 'yes'
        ) {
            return false;
        }
        if (!isset($gatewaySettings['iconFileUrl'])
            && !is_string(
                $gatewaySettings['iconFileUrl']
            )
        ) {
            return false;
        }
        if(!isset($gatewaySettings["iconFilePath"])){
            return false;
        }
        $svgPath = $gatewaySettings["iconFilePath"];
        if(! file_exists( $svgPath )){
           return false;
        }
        return true;
    }

    /**
     * @inheritDoc
     */
    public function getMollieMethodId()
    {
        return PaymentMethod::CREDITCARD;
    }

    /**
     * @inheritDoc
     */
    public function getDefaultTitle()
    {
        return __('Credit card', 'mollie-payments-for-woocommerce');
    }

    /**
     * @inheritDoc
     */
    public function init_form_fields()
    {
        parent::init_form_fields();

        $this->includeMollieComponentsFields();
        $this->includeCreditCardIconSelector();
    }

    /**
     * @inheritDoc
     */
    public function payment_fields()
    {
        parent::payment_fields();

        $this->mollieComponentsFields();
    }

    /**
     * @inheritDoc
     */
    protected function getSettingsDescription()
    {
        return '';
    }

    /**
     * @inheritDoc
     */
    protected function getDefaultDescription()
    {
        return '';
    }

    /**
     * @inheritDoc
     */
    protected function getInstructions(
        WC_Order $order,
        Payment $payment,
        $admin_instructions,
        $plain_text
    ) {
        if ($payment->isPaid() && $payment->details) {
            return sprintf(
            /* translators: Placeholder 1: card holder */
                __('Payment completed by <strong>%s</strong>', 'mollie-payments-for-woocommerce'),
                $payment->details->cardHolder
            );
        }

        return parent::getInstructions($order, $payment, $admin_instructions, $plain_text);
    }

    /**
     * Include the credit card icon selector customization in the credit card
     * settings page
     */
    protected function includeCreditCardIconSelector()
    {
        $fields = include Plugin::getPluginPath(
            '/inc/settings/mollie_creditcard_icons_selector.php'
        );

        $fields and $this->form_fields = array_merge($this->form_fields, $fields);
    }

    /**
     * @return array Array containing the credit cards names enabled in settings
     *               to make customization of checkout icons
     */
    protected function enabledCreditcards()
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

        $creditcardSettings = get_option('mollie_wc_gateway_creditcard_settings', []) ?: [];
        foreach ($creditcardsAvailable as $card) {
            if (mollieWooCommerceStringToBoolOption($creditcardSettings[$optionLexem . $card])) {
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

        $assetsImagesPath
            = Plugin::getPluginPath('public/images/Creditcard_issuers/');
        $cardWidth = PaymentMethodsIconUrl::CREDIT_CARD_ICON_WIDTH;
        $cardsNumber = count($enabledCreditCards);
        $cardsWidth = $cardWidth * $cardsNumber;
        $cardPositionX = 0;
        $actual = get_transient('svg_creditcards_string');
        if(!$actual){
            $actual
                = "<svg width=\"{$cardsWidth}\" height=\"24\" class=\"mollie-gateway-icon\" xmlns=\"http://www.w3.org/2000/svg\" xmlns:xlink=\"http://www.w3.org/1999/xlink\">";
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
            set_transient( 'svg_creditcards_string', $actual, DAY_IN_SECONDS );
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
        $positionString = " x=\"{$xPosition}\"";
        $positionAfterSvgWord = 4;

        return substr_replace($svgString, $positionString, $positionAfterSvgWord, 0);
    }

    /**
     * Deletes the selector transient when the Admin option changes
     *
     */
    protected function processAdminOptionCreditcardSelector()
    {
        delete_transient('svg_creditcards_string');
    }
}

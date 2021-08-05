<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Gateway\Giftcard;

use Mollie\Api\Types\PaymentMethod;
use Mollie\WooCommerce\Gateway\AbstractGateway;
use Mollie\WooCommerce\Gateway\PaymentService;
use Mollie\WooCommerce\Gateway\SurchargeService;
use Mollie\WooCommerce\Notice\NoticeInterface;
use Mollie\WooCommerce\Payment\MollieOrderService;
use Mollie\WooCommerce\Plugin;
use Mollie\WooCommerce\Utils\IconFactory;
use Psr\Log\LoggerInterface as Logger;

class Mollie_WC_Gateway_Giftcard extends AbstractGateway
{
    /**
     *
     */
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
        ];

        /* Has issuers dropdown */
        $this->has_fields = mollieWooCommerceIsDropdownEnabled('mollie_wc_gateway_giftcard_settings');

        parent::__construct(
            $iconFactory,
            $paymentService,
            $surchargeService,
            $mollieOrderService,
            $logger,
            $notice
        );
    }

    /**
     * Initialise Gateway Settings Form Fields
     */
    public function init_form_fields()
    {
        parent::init_form_fields();

        $this->form_fields = array_merge($this->form_fields, [
            'issuers_dropdown_shown' => [
                'title' => __('Show gift cards dropdown', 'mollie-payments-for-woocommerce'),
                'type' => 'checkbox',
                'description' => sprintf(__('If you disable this, a dropdown with various gift cards will not be shown in the WooCommerce checkout, so users will select a gift card on the Mollie payment page after checkout.', 'mollie-payments-for-woocommerce'), $this->getDefaultTitle()),
                'default' => 'yes',
            ],
            'issuers_empty_option' => [
                'title' => __('Issuers empty option', 'mollie-payments-for-woocommerce'),
                'type' => 'text',
                'description' => sprintf(__('This text will be displayed as the first option in the gift card dropdown, but only if the above \'Show gift cards dropdown\' is enabled.', 'mollie-payments-for-woocommerce'), $this->getDefaultTitle()),
                'default' => '',
            ],
        ]);
    }

    /**
     * @return string
     */
    public function getMollieMethodId()
    {
        return PaymentMethod::GIFTCARD;
    }

    /**
     * @return string
     */
    public function getDefaultTitle()
    {
        return __('Gift cards', 'mollie-payments-for-woocommerce');
    }

    /**
     * @return string
     */
    protected function getSettingsDescription()
    {
        return '';
    }

    /**
     * @return string
     */
    protected function getDefaultDescription()
    {
        /* translators: Default gift card dropdown description, displayed above issuer drop down */
        return __('Select your gift card', 'mollie-payments-for-woocommerce');
    }
    /**
     * Display fields below payment method in checkout
     */
    public function payment_fields()
    {
        // Display description above issuers
        parent::payment_fields();

        if (!mollieWooCommerceIsDropdownEnabled('mollie_wc_gateway_giftcard_settings')) {
            return;
        }

        $test_mode = Plugin::getSettingsHelper()->isTestModeEnabled();

        $issuers = Plugin::getDataHelper()->getMethodIssuers(
            $test_mode,
            $this->getMollieMethodId()
        );

        $selected_issuer = $this->getSelectedIssuer();

        $html = '';

        // If only one gift card issuers is available, show it without a dropdown
        if (count($issuers) === 1) {
            $issuerImageSvg = $this->checkSvgIssuers($issuers);
            $issuerImageSvg and $html .= '<img src="' . $issuerImageSvg . '" style="vertical-align:middle" />';
            $html .= $issuers->description;
            echo wpautop(wptexturize($html));

            return;
        }

        // If multiple gift card issuers are available, show them in a dropdown
        $html .= '<select name="' . Plugin::PLUGIN_ID . '_issuer_' . $this->id . '">';
        $html .= '<option value="">' . esc_html(__($this->get_option('issuers_empty_option', ''), 'mollie-payments-for-woocommerce')) . '</option>';
        foreach ($issuers as $issuer) {
            $html .= '<option value="' . esc_attr($issuer->id) . '"' . ( $selected_issuer == $issuer->id ? ' selected=""' : '' ) . '>' . esc_html($issuer->name) . '</option>';
        }
        $html .= '</select>';

        echo wpautop(wptexturize($html));
    }

    /**
     * @param $issuers
     * @return string
     */
    protected function checkSvgIssuers($issuers)
    {
        if (!isset($issuers[0]) || ! is_object($issuers[0])) {
            return '';
        }
        $image = isset($issuers[0]->image) ? $issuers[0]->image : null;
        if (!$image) {
            return '';
        }
        return isset($image->svg) && is_string($image->svg) ? $image->svg : '';
    }
}

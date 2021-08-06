<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Gateway\Ideal;

use Mollie\Api\Resources\Payment;
use Mollie\Api\Types\PaymentMethod;
use Mollie\WooCommerce\Gateway\PaymentService;
use Mollie\WooCommerce\Gateway\SurchargeService;
use Mollie\WooCommerce\Notice\NoticeInterface;
use Mollie\WooCommerce\Payment\MollieOrderService;
use Mollie\WooCommerce\Plugin;
use Mollie\WooCommerce\SDK\HttpResponse;
use Mollie\WooCommerce\Subscription\AbstractSepaRecurring;
use Mollie\WooCommerce\Utils\IconFactory;
use Psr\Log\LoggerInterface as Logger;
use WC_Order;

class Mollie_WC_Gateway_Ideal extends AbstractSepaRecurring
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
        NoticeInterface $notice,
        HttpResponse $httpResponse,
        string $pluginUrl,
        string $pluginPath
    ) {

        $this->supports = [
            'products',
            'refunds',
        ];
        $this->id = 'mollie_wc_gateway_ideal';

        /* Has issuers dropdown */
        $this->has_fields = mollieWooCommerceIsDropdownEnabled('mollie_wc_gateway_ideal_settings');

        parent::__construct(
            $iconFactory,
            $paymentService,
            $surchargeService,
            $mollieOrderService,
            $logger,
            $notice,
            $httpResponse,
            $pluginUrl,
            $pluginPath
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
                'title' => __('Show iDEAL banks dropdown', 'mollie-payments-for-woocommerce'),
                'type' => 'checkbox',
                'description' => sprintf(__('If you disable this, a dropdown with various iDEAL banks will not be shown in the WooCommerce checkout, so users will select a iDEAL bank on the Mollie payment page after checkout.', 'mollie-payments-for-woocommerce'), $this->getDefaultTitle()),
                'default' => 'yes',
            ],
            'issuers_empty_option' => [
                'title' => __('Issuers empty option', 'mollie-payments-for-woocommerce'),
                'type' => 'text',
                'description' => sprintf(__('This text will be displayed as the first option in the iDEAL issuers drop down, if nothing is entered, "Select your bank" will be shown. Only if the above \'Show iDEAL banks dropdown\' is enabled.', 'mollie-payments-for-woocommerce'), $this->getDefaultTitle()),
                'default' => 'Select your bank',
            ],
        ]);
    }

    /**
     * @return string
     */
    public function getMollieMethodId()
    {
        return PaymentMethod::IDEAL;
    }

    /**
     * @return string
     */
    public function getDefaultTitle()
    {
        return __('iDEAL', 'mollie-payments-for-woocommerce');
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
        /* translators: Default iDEAL description, displayed above issuer drop down */
        return __('Select your bank', 'mollie-payments-for-woocommerce');
    }

    /**
     * Display fields below payment method in checkout
     */
    public function payment_fields()
    {
        // Display description above issuers
        parent::payment_fields();

        if (!mollieWooCommerceIsDropdownEnabled('mollie_wc_gateway_ideal_settings')) {
            return;
        }

        $test_mode = Plugin::getSettingsHelper()->isTestModeEnabled();

        $issuers = Plugin::getDataHelper()->getMethodIssuers(
            $test_mode,
            $this->getMollieMethodId()
        );

        $selected_issuer = $this->getSelectedIssuer();

        $html = '<select name="' . Plugin::PLUGIN_ID . '_issuer_' . $this->id . '">';
        $html .= '<option value="">' . esc_html(__($this->get_option('issuers_empty_option', $this->getDefaultDescription()), 'mollie-payments-for-woocommerce')) . '</option>';
        foreach ($issuers as $issuer) {
            $html .= '<option value="' . esc_attr($issuer->id) . '"' . ($selected_issuer == $issuer->id ? ' selected=""' : '') . '>' . esc_html($issuer->name) . '</option>';
        }
        $html .= '</select>';

        echo wpautop(wptexturize($html));
    }

    /**
     * @param WC_Order                  $order
     * @param Payment $payment
     * @param bool                      $admin_instructions
     * @param bool                      $plain_text
     * @return string|null
     */
    protected function getInstructions(WC_Order $order, Payment $payment, $admin_instructions, $plain_text)
    {
        if ($payment->isPaid() && $payment->details) {
            return sprintf(
                /* translators: Placeholder 1: consumer name, placeholder 2: consumer IBAN, placeholder 3: consumer BIC */
                __('Payment completed by <strong>%1$s</strong> (IBAN (last 4 digits): %2$s, BIC: %3$s)', 'mollie-payments-for-woocommerce'),
                $payment->details->consumerName,
                substr($payment->details->consumerAccount, -4),
                $payment->details->consumerBic
            );
        }

        return parent::getInstructions($order, $payment, $admin_instructions, $plain_text);
    }
}

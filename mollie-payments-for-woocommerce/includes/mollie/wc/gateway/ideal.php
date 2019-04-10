<?php

use Mollie\Api\Types\PaymentMethod;

class Mollie_WC_Gateway_Ideal extends Mollie_WC_Gateway_AbstractSepaRecurring
{
    /**
     *
     */
    public function __construct ()
    {
        $this->supports = array(
            'products',
            'refunds',
        );

        /* Has issuers dropdown */
        $this->has_fields = TRUE;

        parent::__construct();
    }

	/**
	 * Initialise Gateway Settings Form Fields
	 */
	public function init_form_fields()
	{
		parent::init_form_fields();

		$this->form_fields = array_merge($this->form_fields, array(
			'issuers_dropdown_shown' => array(
				'title'       => __('Show iDEAL banks dropdown', 'mollie-payments-for-woocommerce'),
				'type'        => 'checkbox',
				'description' => sprintf(__('If you disable this, a dropdown with various iDEAL banks will not be shown in the WooCommerce checkout, so users will select a iDEAL bank on the Mollie payment page after checkout.', 'mollie-payments-for-woocommerce'), $this->getDefaultTitle()),
				'default'     => 'yes',
				'desc_tip'    => true,
			),
			'issuers_empty_option' => array(
				'title'       => __('Issuers empty option', 'mollie-payments-for-woocommerce'),
				'type'        => 'text',
				'description' => sprintf(__('This text will be displayed as the first option in the iDEAL issuers drop down, if nothing is entered, "Select your bank" will be shown. Only if the above \'Show iDEAL banks dropdown\' is enabled.', 'mollie-payments-for-woocommerce'), $this->getDefaultTitle()),
				'default'     => 'Select your bank',
				'desc_tip'    => true,
			),
		));
	}

    /**
     * @return string
     */
    public function getMollieMethodId ()
    {
        return PaymentMethod::IDEAL;
    }

    /**
     * @return string
     */
    public function getDefaultTitle ()
    {
        return __('iDEAL', 'mollie-payments-for-woocommerce');
    }

	/**
	 * @return string
	 */
	protected function getSettingsDescription() {
		return '';
	}

    /**
     * @return string
     */
    protected function getDefaultDescription ()
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

        $test_mode = Mollie_WC_Plugin::getSettingsHelper()->isTestModeEnabled();

        $issuers = Mollie_WC_Plugin::getDataHelper()->getMethodIssuers(
            $test_mode,
            $this->getMollieMethodId()
        );

        $selected_issuer = $this->getSelectedIssuer();

        $html  = '<select name="' . Mollie_WC_Plugin::PLUGIN_ID . '_issuer_' . $this->id . '">';
        $html .= '<option value="">' . esc_html(__($this->get_option('issuers_empty_option',  $this->getDefaultDescription()), 'mollie-payments-for-woocommerce')) . '</option>';
        foreach ($issuers as $issuer)
        {
            $html .= '<option value="' . esc_attr($issuer->id) . '"' . ($selected_issuer == $issuer->id ? ' selected=""' : '') . '>' . esc_html($issuer->name) . '</option>';
        }
        $html .= '</select>';

        echo wpautop(wptexturize($html));
    }

    /**
     * @param WC_Order                  $order
     * @param Mollie\Api\Resources\Payment $payment
     * @param bool                      $admin_instructions
     * @param bool                      $plain_text
     * @return string|null
     */
    protected function getInstructions (WC_Order $order, Mollie\Api\Resources\Payment $payment, $admin_instructions, $plain_text)
    {
        if ($payment->isPaid() && $payment->details)
        {
            return sprintf(
                /* translators: Placeholder 1: consumer name, placeholder 2: consumer IBAN, placeholder 3: consumer BIC */
                __('Payment completed by <strong>%s</strong> (IBAN (last 4 digits): %s, BIC: %s)', 'mollie-payments-for-woocommerce'),
                $payment->details->consumerName,
	            substr($payment->details->consumerAccount, -4),
                $payment->details->consumerBic
            );
        }

        return parent::getInstructions($order, $payment, $admin_instructions, $plain_text);
    }
}

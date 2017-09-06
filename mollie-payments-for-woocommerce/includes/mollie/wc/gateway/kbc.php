<?php
class Mollie_WC_Gateway_Kbc extends Mollie_WC_Gateway_Abstract
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
			'issuers_empty_option' => array(
				'title'       => __('Issuers empty option', 'mollie-payments-for-woocommerce'),
				'type'        => 'text',
				'description' => sprintf(__('This text will be displayed as the first option in the KBC/CBC issuers drop down', 'mollie-payments-for-woocommerce'), $this->getDefaultTitle()),
				'default'     => '',
				'desc_tip'    => true,
			),
		));
	}

    /**
     * @return string
     */
    public function getMollieMethodId ()
    {
        return Mollie_API_Object_Method::KBC;
    }

    /**
     * @return string
     */
    public function getDefaultTitle ()
    {
        return __('KBC/CBC Payment Button', 'mollie-payments-for-woocommerce');
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
	    /* translators: Default KBC/CBC dropdown description, displayed above issuer drop down */
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
		$html .= '<option value="">' . esc_html(__($this->get_option('issuers_empty_option', ''), 'mollie-payments-for-woocommerce')) . '</option>';
		foreach ($issuers->issuers as $issuer)
		{
			$html .= '<option value="' . esc_attr($issuer->id) . '"' . ($selected_issuer == $issuer->id ? ' selected=""' : '') . '>' . esc_html($issuer->name) . '</option>';
		}
		$html .= '</select>';

		echo wpautop(wptexturize($html));
	}

}

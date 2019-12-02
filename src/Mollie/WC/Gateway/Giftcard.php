<?php

use Mollie\Api\Types\PaymentMethod;

class Mollie_WC_Gateway_Giftcard extends Mollie_WC_Gateway_Abstract
{
    /**
     *
     */
    public function __construct ()
    {
        $this->supports = array(
            'products',
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
				'title'       => __('Show gift cards dropdown', 'mollie-payments-for-woocommerce'),
				'type'        => 'checkbox',
				'description' => sprintf(__('If you disable this, a dropdown with various gift cards will not be shown in the WooCommerce checkout, so users will select a gift card on the Mollie payment page after checkout.', 'mollie-payments-for-woocommerce'), $this->getDefaultTitle()),
				'default'     => 'yes',
				'desc_tip'    => true,
			),
			'issuers_empty_option' => array(
				'title'       => __('Issuers empty option', 'mollie-payments-for-woocommerce'),
				'type'        => 'text',
				'description' => sprintf(__('This text will be displayed as the first option in the gift card dropdown, but only if the above \'Show gift cards dropdown\' is enabled.', 'mollie-payments-for-woocommerce'), $this->getDefaultTitle()),
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
        return PaymentMethod::GIFTCARD;
    }

    /**
     * @return string
     */
    public function getDefaultTitle ()
    {
        return __('Gift cards', 'mollie-payments-for-woocommerce');
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

		$test_mode = Mollie_WC_Plugin::getSettingsHelper()->isTestModeEnabled();

		$issuers = Mollie_WC_Plugin::getDataHelper()->getMethodIssuers(
			$test_mode,
			$this->getMollieMethodId()
		);

		$selected_issuer = $this->getSelectedIssuer();

		$html = '';

		// If only one gift card issuers is available, show it without a dropdown
		if ( count( $issuers ) === 1 ) {
            $issuerImageSvg = $this->checkSvgIssuers($issuers);
            $issuerImageSvg and $html .= '<img src="' . $issuerImageSvg . '" style="vertical-align:middle" />';
			$html .= $issuers->description;
			echo wpautop( wptexturize( $html ) );

			return;
		}

		// If multiple gift card issuers are available, show them in a dropdown
		$html .= '<select name="' . Mollie_WC_Plugin::PLUGIN_ID . '_issuer_' . $this->id . '">';
		$html .= '<option value="">' . esc_html( __( $this->get_option( 'issuers_empty_option', '' ), 'mollie-payments-for-woocommerce' ) ) . '</option>';
		foreach ( $issuers as $issuer ) {
			$html .= '<option value="' . esc_attr( $issuer->id ) . '"' . ( $selected_issuer == $issuer->id ? ' selected=""' : '' ) . '>' . esc_html( $issuer->name ) . '</option>';
		}
		$html .= '</select>';

		echo wpautop( wptexturize( $html ) );

	}

    /**
     * @param $issuers
     * @return string
     */
    protected function checkSvgIssuers($issuers)
    {
        if(!isset($issuers[0]) || ! is_object($issuers[0])) {
            return '';
        }
        $image = isset($issuers[0]->image) ? $issuers[0]->image : null;
        if(!$image) {
            return '';
        }
        return isset($image->svg) && is_string($image->svg) ? $image->svg : '';
    }

}

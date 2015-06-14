<?php
class WC_Mollie_Gateway_Ideal extends WC_Mollie_Gateway_Abstract
{
    /**
     *
     */
    public function __construct ()
    {
        $this->id         = 'mollie_ideal';
        $this->supports   = array(
            'products',
            'refunds',
        );

        /* Has issuers dropdown */
        $this->has_fields = TRUE;

        parent::__construct();
    }

    /**
     * @return string
     */
    public function getMollieMethodId ()
    {
        return Mollie_API_Object_Method::IDEAL;
    }

    /**
     * @return string
     */
    protected function getDefaultTitle ()
    {
        return __('iDEAL', 'woocommerce-mollie-payments');
    }

    /**
     * @return string
     */
    protected function getDefaultDescription ()
    {
        return __('Select your bank', 'woocommerce-mollie-payments');
    }

    /**
     * Display fields below payment method in checkout
     */
    public function payment_fields()
    {
        // Display description above issuers
        parent::payment_fields();

        $ideal_issuers = WC_Mollie::getDataHelper()->getIssuers(
            $this->getMollieMethodId()
        );

        $selected_issuer = $this->getSelectedIssuer();

        $html  = '<select name="' . WC_Mollie::PLUGIN_ID . '_issuer_' . $this->id . '">';
        $html .= '<option value=""></option>';
        foreach ($ideal_issuers as $issuer)
        {
            $html .= '<option value="' . esc_attr($issuer->id) . '"' . ($selected_issuer == $issuer->id ? ' selected=""' : '') . '>' . esc_html($issuer->name) . '</option>';
        }
        $html .= '</select>';

        echo wpautop(wptexturize($html));
    }

    /**
     * @param WC_Order                  $order
     * @param Mollie_API_Object_Payment $payment
     * @param bool                      $admin_instructions
     * @param bool                      $plain_text
     * @return string|null
     */
    protected function getInstructions (WC_Order $order, Mollie_API_Object_Payment $payment, $admin_instructions, $plain_text)
    {
        if ($payment->isPaid() && $payment->details)
        {
            return sprintf(
                __('Payment completed by <strong>%s</strong> (IBAN: %s, BIC: %s)', 'woocommerce-mollie-payments'),
                $payment->details->consumerName,
                implode(' ', str_split($payment->details->consumerAccount, 4)),
                $payment->details->consumerBic
            );
        }

        return parent::getInstructions($order, $payment, $admin_instructions, $plain_text);
    }
}

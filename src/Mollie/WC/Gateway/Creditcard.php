<?php

use Mollie\Api\Types\PaymentMethod;

class Mollie_WC_Gateway_Creditcard extends Mollie_WC_Gateway_AbstractSubscription
{
    public function __construct()
    {
        parent::__construct();

        $this->supports = [
            'products',
            'refunds',
        ];

        $this->initSubscriptionSupport();

        $this->hasFieldsIfMollieComponentsIsEnabled();
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
        Mollie\Api\Resources\Payment $payment,
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

}

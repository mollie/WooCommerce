<?php

use Mollie\Api\Types\PaymentMethod;

class Mollie_WC_Gateway_Creditcard extends Mollie_WC_Gateway_AbstractSubscription
{
    const FILTER_COMPONENTS_SETTINGS = 'components_settings';

    public function __construct()
    {
        $this->supports = [
            'products',
            'refunds',
        ];

        $this->has_fields = true;

        $this->initSubscriptionSupport();

        parent::__construct();
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

        $componentsSettings = $this->componentsSettings();

        /**
         * Filter Component Settings
         *
         * @param array $componentSettings Default components settings for the Credit Card Gateway
         */
        $componentsSettings = apply_filters(self::FILTER_COMPONENTS_SETTINGS, $componentsSettings);

        $this->form_fields = array_merge($this->form_fields, $componentsSettings);
    }

    /**
     * @inheritDoc
     */
    public function payment_fields()
    {
        // Display description above issuers
        parent::payment_fields();

        // TODO allow more than one components wrapper to add components to other payment methods
        ?>
        <div class="mollie-components"></div>
        <p>
            <?php
            echo $this->lockIcon();
            esc_html_e('Secure payments provided by ');
            // TODO Check if possible to make svg accessible so we can show `mollie` text
            echo $this->mollieLogo();
            ?>
        </p>
        <?php
    }

    protected function lockIcon()
    {
        return file_get_contents(
            Mollie_WC_Plugin::getPluginPath('assets/images/lock-icon.svg')
        );
    }

    protected function mollieLogo()
    {
        return file_get_contents(
            Mollie_WC_Plugin::getPluginPath('assets/images/mollie-logo.svg')
        );
    }

    protected function componentsSettings()
    {
        $componentSettingsFilePath = $this->componentsFilePath();

        if (!file_exists($componentSettingsFilePath)) {
            return [];
        }

        $components = include $componentSettingsFilePath;

        if (!is_array($components)) {
            $components = [];
        }

        return $components;
    }

    protected function componentsFilePath()
    {
        return Mollie_WC_Plugin::getPluginPath(
            '/inc/gateway_settings/mollie_components.php'
        );
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

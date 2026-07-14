<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Settings\Page\Section;

use Mollie\WooCommerce\Shared\SharedDataDictionary;
use Mollie\WooCommerce\Payment\PaymentProcessor;
class Advanced extends \Mollie\WooCommerce\Settings\Page\Section\AbstractSection
{
    public function config(): array
    {
        $this->cleanDbIfRequested();
        $config = [['id' => $this->settings->getSettingId('title'), 'title' => __('Mollie advanced settings', 'mollie-payments-for-woocommerce'), 'type' => 'title', 'desc' => '<p>' . __('The following options are required to use the plugin and are used by all Mollie payment methods', 'mollie-payments-for-woocommerce') . '</p>'], ['id' => $this->settings->getSettingId('debug'), 'title' => __('Debug Log', 'mollie-payments-for-woocommerce'), 'type' => 'checkbox', 'desc' => sprintf(__("Log plugin events. <a href='%s'>View logs</a>", 'mollie-payments-for-woocommerce'), $this->settings->getLogsUrl()), 'default' => 'yes'], ['id' => $this->settings->getSettingId('webhook_test'), 'title' => __('Webhook Test', 'mollie-payments-for-woocommerce'), 'type' => 'mollie_custom_input', 'value' => $this->webhookTestButtonHtml(), 'desc' => __('Test if Mollie webhooks can reach your site. This helps diagnose connection issues.', 'mollie-payments-for-woocommerce')], ['id' => $this->settings->getSettingId('order_status_cancelled_payments'), 'title' => __('Order status after cancelled payment', 'mollie-payments-for-woocommerce'), 'type' => 'select', 'options' => ['pending' => __('Pending', 'woocommerce'), 'cancelled' => __('Cancelled', 'woocommerce')], 'desc' => __('Status for orders when a payment (not a Mollie order via the Orders API) is cancelled. Default: pending. Orders with status Pending can be paid with another payment method, customers can try again. Cancelled orders are final. Set this to Cancelled if you only have one payment method or don\'t want customers to re-try paying with a different payment method. This doesn\'t apply to payments for orders via the new Orders API and Klarna payments.', 'mollie-payments-for-woocommerce'), 'default' => 'pending'], ['id' => $this->settings->getSettingId(SharedDataDictionary::SETTING_NAME_PAYMENT_LOCALE), 'title' => __('Payment screen language', 'mollie-payments-for-woocommerce'), 'type' => 'select', 'options' => [SharedDataDictionary::SETTING_LOCALE_WP_LANGUAGE => __('Automatically send WordPress language', 'mollie-payments-for-woocommerce') . ' (' . __('default', 'mollie-payments-for-woocommerce') . ')', SharedDataDictionary::SETTING_LOCALE_DETECT_BY_BROWSER => __('Detect using browser language', 'mollie-payments-for-woocommerce'), 'en_US' => __('English', 'mollie-payments-for-woocommerce'), 'nl_NL' => __('Dutch', 'mollie-payments-for-woocommerce'), 'nl_BE' => __('Flemish (Belgium)', 'mollie-payments-for-woocommerce'), 'fr_FR' => __('French', 'mollie-payments-for-woocommerce'), 'fr_BE' => __('French (Belgium)', 'mollie-payments-for-woocommerce'), 'de_DE' => __('German', 'mollie-payments-for-woocommerce'), 'de_AT' => __('Austrian German', 'mollie-payments-for-woocommerce'), 'de_CH' => __('Swiss German', 'mollie-payments-for-woocommerce'), 'es_ES' => __('Spanish', 'mollie-payments-for-woocommerce'), 'ca_ES' => __('Catalan', 'mollie-payments-for-woocommerce'), 'pt_PT' => __('Portuguese', 'mollie-payments-for-woocommerce'), 'it_IT' => __('Italian', 'mollie-payments-for-woocommerce'), 'nb_NO' => __('Norwegian', 'mollie-payments-for-woocommerce'), 'sv_SE' => __('Swedish', 'mollie-payments-for-woocommerce'), 'fi_FI' => __('Finnish', 'mollie-payments-for-woocommerce'), 'da_DK' => __('Danish', 'mollie-payments-for-woocommerce'), 'is_IS' => __('Icelandic', 'mollie-payments-for-woocommerce'), 'hu_HU' => __('Hungarian', 'mollie-payments-for-woocommerce'), 'pl_PL' => __('Polish', 'mollie-payments-for-woocommerce'), 'lv_LV' => __('Latvian', 'mollie-payments-for-woocommerce'), 'lt_LT' => __('Lithuanian', 'mollie-payments-for-woocommerce')], 'desc' => sprintf(
            /* translators: Placeholder 1: link tag Placeholder 2: closing tag */
            __('Sending a language (or locale) is required. The option \'Automatically send WordPress language\' will try to get the customer\'s language in WordPress (and respects multilanguage plugins) and convert it to a format Mollie understands. If this fails, or if the language is not supported, it will fall back to American English. You can also select one of the locales currently supported by Mollie, that will then be used for all customers.', 'mollie-payments-for-woocommerce'),
            '<a href="https://www.mollie.com/nl/docs/reference/payments/create" target="_blank">',
            '</a>'
        ), 'default' => SharedDataDictionary::SETTING_LOCALE_WP_LANGUAGE], ['id' => $this->settings->getSettingId('customer_details'), 'title' => __('Store customer details at Mollie', 'mollie-payments-for-woocommerce'), 'desc' => sprintf(
            /* translators: Placeholder 1: enabled or disabled Placeholder 2: translated string */
            __('Should Mollie store customers name and email address for Single Click Payments? Default <code>%1$s</code>. Required if WooCommerce Subscriptions is being used! Read more about <a href=\'https://help.mollie.com/hc/en-us/articles/115000671249-What-are-single-click-payments-and-how-does-it-work-\'>%2$s</a> and how it improves your conversion.', 'mollie-payments-for-woocommerce'),
            strtolower(__('Enabled', 'mollie-payments-for-woocommerce')),
            __('Single Click Payments', 'mollie-payments-for-woocommerce')
        ), 'type' => 'checkbox', 'default' => 'yes'], ['id' => $this->settings->getSettingId('api_switch'), 'title' => __('Select API Method', 'mollie-payments-for-woocommerce'), 'type' => 'select', 'options' => [PaymentProcessor::PAYMENT_METHOD_TYPE_ORDER => ucfirst(PaymentProcessor::PAYMENT_METHOD_TYPE_ORDER), PaymentProcessor::PAYMENT_METHOD_TYPE_PAYMENT => ucfirst(PaymentProcessor::PAYMENT_METHOD_TYPE_PAYMENT) . ' (' . __('default', 'mollie-payments-for-woocommerce') . ')'], 'default' => PaymentProcessor::PAYMENT_METHOD_TYPE_PAYMENT, 'desc' => sprintf(
            /* translators: Placeholder 1: opening link tag, placeholder 2: closing link tag */
            __('Payments API is the recommended option since Orders API will be deprecated. Click %1$shere%2$s to read more about the differences between the Payments and Orders API', 'mollie-payments-for-woocommerce'),
            '<a href="https://docs.mollie.com/reference/payments-api" target="_blank">',
            '</a>'
        )], ['id' => $this->settings->getSettingId('api_payment_description'), 'title' => __('API Payment Description', 'mollie-payments-for-woocommerce'), 'type' => 'text', 'default' => '{orderNumber}', 'desc' => sprintf('</p>
            <div class="available-payment-description-labels hide-if-no-js">
                <p>%1$s:</p>
                <ul role="list">
                    %2$s
                </ul>
            </div>
            <br style="clear: both;" />
            <p class="description">%3$s', _x('Available variables', 'Payment description options', 'mollie-payments-for-woocommerce'), implode('', array_map(static function ($label, $labelDescription) {
            return sprintf('<li style="float: left; margin-right: 5px;">
                            <button type="button"
                                class="mollie-settings-advanced-payment-desc-label button button-secondary button-small"
                                data-tag="%1$s"
                                aria-label="%2$s"
                                title="%3$s"
                            >
                                %1$s
                            </button>
                        </li>', $label, substr($label, 1, -1), $labelDescription);
        }, array_keys($this->paymentDescriptionLabels()), $this->paymentDescriptionLabels())), sprintf(
            /* translators: Placeholder 1: Opening paragraph tag, placeholder 2: Closing paragraph tag */
            __('Select among the available variables the description to be used for this transaction.%1$s(Note: this only works when the method is set to Payments API)%2$s', 'mollie-payments-for-woocommerce'),
            '<p>',
            '</p>'
        ))], ['id' => $this->settings->getSettingId('gatewayFeeLabel'), 'title' => __('Surcharge gateway fee label', 'mollie-payments-for-woocommerce'), 'type' => 'text', 'custom_attributes' => ['maxlength' => '30'], 'default' => __('Gateway Fee', 'mollie-payments-for-woocommerce'), 'desc' => __('This is the label will appear in frontend when the surcharge applies', 'mollie-payments-for-woocommerce')], ['id' => $this->settings->getSettingId('removeOptionsAndTransients'), 'title' => __('Remove Mollie data from Database on uninstall', 'mollie-payments-for-woocommerce'), 'type' => 'checkbox', 'default' => 'no', 'desc' => __("Remove options and scheduled actions from database when uninstalling the plugin.", "mollie-payments-for-woocommerce") . ' (<a href="' . esc_url($this->cleanDbUrl()) . '">' . strtolower(__('Clear now', 'mollie-payments-for-woocommerce')) . '</a>)'], ['id' => $this->settings->getSettingId('sectionend'), 'type' => 'sectionend']];
        return apply_filters('inpsyde.mollie-advanced-settings', $config, $this->settings->getPluginId());
    }
    protected function paymentDescriptionLabels(): array
    {
        return ['{orderNumber}' => _x('Order number', 'Label {orderNumber} description for payment description options', 'mollie-payments-for-woocommerce'), '{storeName}' => _x('Site Title', 'Label {storeName} description for payment description options', 'mollie-payments-for-woocommerce'), '{customer.firstname}' => _x('Customer\'s first name', 'Label {customer.firstname} description for payment description options', 'mollie-payments-for-woocommerce'), '{customer.lastname}' => _x('Customer\'s last name', 'Label {customer.lastname} description for payment description options', 'mollie-payments-for-woocommerce'), '{customer.company}' => _x('Customer\'s company name', 'Label {customer.company} description for payment description options', 'mollie-payments-for-woocommerce')];
    }
    protected function cleanDbUrl(): string
    {
        return add_query_arg(['cleanDB-mollie' => 1, 'nonce_mollie_cleanDb' => wp_create_nonce('nonce_mollie_cleanDb')]);
    }
    protected function content(): string
    {
        ob_start();
        ?>
        <?php 
        return ob_get_clean();
    }
    protected function cleanDbIfRequested()
    {
        if (isset($_GET['cleanDB-mollie']) && wp_verify_nonce(filter_input(\INPUT_GET, 'nonce_mollie_cleanDb', \FILTER_SANITIZE_SPECIAL_CHARS), 'nonce_mollie_cleanDb')) {
            $paymentMethods = $this->container->get('gateway.paymentMethods');
            $cleaner = $this->settings->cleanDb();
            $cleaner->cleanAll();
            //set default settings
            foreach ($paymentMethods as $paymentMethod) {
                $paymentMethod->getSettings();
            }
        }
    }
    /**
     * Generate HTML for webhook test button
     *
     * @return string HTML markup for the webhook test button
     */
    protected function webhookTestButtonHtml(): string
    {
        $nonce = wp_create_nonce('mollie_webhook_test_nonce');
        ob_start();
        ?>
        <div class="mollie-webhook-test-container">
            <button
                type="button"
                id="mollie-webhook-test-button"
                class="button button-secondary"
                data-nonce="<?php 
        echo esc_attr($nonce);
        ?>"
            >
                <?php 
        esc_html_e('Test Webhook Connection', 'mollie-payments-for-woocommerce');
        ?>
            </button>
            <span class="spinner" style="float: none; margin: 0 0 0 8px; visibility: hidden;"></span>

            <div
                id="mollie-webhook-test-result"
                class="mollie-webhook-test-result"
                style="margin-top: 10px; display: none;"
            ></div>
        </div>
        <?php 
        return ob_get_clean();
    }
}

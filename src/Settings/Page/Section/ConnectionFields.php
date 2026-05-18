<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Settings\Page\Section;

class ConnectionFields extends \Mollie\WooCommerce\Settings\Page\Section\AbstractSection
{
    use \Mollie\WooCommerce\Settings\Page\Section\ConnectionStatusTrait;
    public function config(): array
    {
        return [['id' => $this->settings->getSettingId('title'), 'title' => '', 'type' => 'title'], $this->connectionStatusField($this->settings, $this->connectionStatus), ['id' => $this->settings->getSettingId('test_mode_enabled'), 'title' => __('Mollie Payment Mode', 'mollie-payments-for-woocommerce'), 'default' => 'no', 'type' => 'select', 'options' => ['no' => 'Live API', 'yes' => 'Test API'], 'desc' => __('Select <strong>Live API</strong> to receive real payments and <strong>Test API</strong> to test transactions without a fee.', 'mollie-payments-for-woocommerce'), 'desc_tip' => __('Enable test mode if you want to test the plugin without using real payments.', 'mollie-payments-for-woocommerce')], ['id' => $this->settings->getSettingId('live_api_key'), 'title' => __('Live API key', 'mollie-payments-for-woocommerce'), 'default' => '', 'type' => 'text', 'desc' => sprintf(__("Use your <a href='%s' target='_blank'>Live API key</a> when you're ready to receive real payments.", 'mollie-payments-for-woocommerce'), 'https://my.mollie.com/dashboard/developers/api-keys?utm_source=woocommerce&utm_medium=plugin&utm_campaign=partner'), 'css' => 'width: 350px', 'placeholder' => __('Live API key should start with live_', 'mollie-payments-for-woocommerce')], ['id' => $this->settings->getSettingId('test_api_key'), 'title' => __('Test API key', 'mollie-payments-for-woocommerce'), 'default' => '', 'type' => 'text', 'desc' => sprintf(__("Use your <a href='%s' target='_blank'>Test APl key</a> to check the connection and test transactions without a fee.", 'mollie-payments-for-woocommerce'), 'https://my.mollie.com/dashboard/developers/api-keys?utm_source=woocommerce&utm_medium=plugin&utm_campaign=partner'), 'css' => 'width: 350px', 'placeholder' => __('Test API key should start with test_', 'mollie-payments-for-woocommerce')], ['id' => $this->settings->getSettingId('sectionend'), 'type' => 'sectionend']];
    }
}

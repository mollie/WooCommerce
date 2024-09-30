<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Settings\Page\Section;

class ConnectionFields extends AbstractSection
{
    public function config(): array
    {
        return [
            [
                'id' => $this->settings->getSettingId('title'),
                'title' => '',
                'type' => 'title'
            ],
            [
                'id' => $this->settings->getSettingId('test_mode_enabled'),
                'title' => __('Mollie Payment Mode', 'mollie-payments-for-woocommerce'),
                'default' => 'no',
                'type' => 'select',
                'options' => [
                    'no' => 'Live API',
                    'yes' => 'Test API'
                ],
                'desc_tip' => __(
                    'Enable test mode if you want to test the plugin without using real payments.',
                    'mollie-payments-for-woocommerce'
                ),
            ],
            [
                'id' => $this->settings->getSettingId('live_api_key'),
                'title' => __('Live API key', 'mollie-payments-for-woocommerce'),
                'default' => '',
                'type' => 'text',
                'desc' => sprintf(
                /* translators: Placeholder 1: API key mode (live or test). The surrounding %s's Will be replaced by a link to the Mollie profile */
                    __(
                        'The API key is used to connect to Mollie. You can find your <strong>%1$s</strong> API key in your %2$sMollie account%3$s',
                        'mollie-payments-for-woocommerce'
                    ),
                    'live',
                    '<a href="https://my.mollie.com/dashboard/developers/api-keys?utm_source=woocommerce&utm_medium=plugin&utm_campaign=partner" target="_blank">',
                    '</a>'
                ),
                'css' => 'width: 350px',
                'placeholder' => __(
                    'Live API key should start with live_',
                    'mollie-payments-for-woocommerce'
                ),
            ],
            [
                'id' => $this->settings->getSettingId('test_api_key'),
                'title' => __('Test API key', 'mollie-payments-for-woocommerce'),
                'default' => '',
                'type' => 'text',
                'desc' => sprintf(
                /* translators: Placeholder 1: API key mode (live or test). The surrounding %s's Will be replaced by a link to the Mollie profile */
                    __(
                        'The API key is used to connect to Mollie. You can find your <strong>%1$s</strong> API key in your %2$sMollie account%3$s',
                        'mollie-payments-for-woocommerce'
                    ),
                    'test',
                    '<a href="https://my.mollie.com/dashboard/developers/api-keys?utm_source=woocommerce&utm_medium=plugin&utm_campaign=partner" target="_blank">',
                    '</a>'
                ),
                'css' => 'width: 350px',
                'placeholder' => __(
                    'Test API key should start with test_',
                    'mollie-payments-for-woocommerce'
                ),
            ],
            [
                'id' => $this->settings->getSettingId('debug'),
                'title' => __('Debug Log', 'mollie-payments-for-woocommerce'),
                'type' => 'checkbox',
                'desc' => __('Log plugin events.', 'mollie-payments-for-woocommerce'),
                'default' => 'yes',
            ],
            [
                'id' => $this->settings->getSettingId('sectionend'),
                'type' => 'sectionend',
            ],
        ];
    }
}

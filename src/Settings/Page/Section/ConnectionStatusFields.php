<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Settings\Page\Section;

class ConnectionStatusFields extends AbstractSection
{
    use ConnectionStatusTrait;

    public function config(): array
    {
        return [
            [
                'id' => $this->settings->getSettingId('title'),
                'title' => '',
                'type' => 'title',
            ],
            $this->connectionStatusField($this->settings, $this->connectionStatus),
            $this->refreshStatusField(),
            [
                'id' => $this->settings->getSettingId('sectionend'),
                'type' => 'sectionend',
            ],
        ];
    }

    public function refreshStatusField(): array
    {
        $refreshNonce = wp_create_nonce('nonce_mollie_refresh_methods');
        $refreshUrl = add_query_arg(
            ['refresh-methods' => 1, 'nonce_mollie_refresh_methods' => $refreshNonce]
        );

        return [
            'id' => $this->settings->getSettingId('refresh_status'),
            'title' => __('Payment method availability', 'mollie-payments-for-woocommerce'),
            'value' => '<a class="button-secondary" href="' . $refreshUrl . '">' . __('Refresh Mollie payment methods', 'mollie-payments-for-woocommerce') . '</a>',
            'desc' => __(
                'Click this button to refresh your payment methods, e.g. if you recently enabled new payment methods in your Mollie profile',
                'mollie-payments-for-woocommerce'
            ),
            'type' => 'mollie_custom_input',
        ];
    }
}

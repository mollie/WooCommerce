<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Settings\Page\Section;

use Mollie\WooCommerce\Settings\Settings;

trait ConnectionStatusTrait
{
    /**
     * @param array{connected?: bool, error_kind?: string, error_code?: int, error_message?: string} $connectionStatus
     */
    protected function connectionStatusField(Settings $settings, array $connectionStatus): array
    {

        return [
            'id' => $settings->getSettingId('connection_status'),
            'title' => __('Mollie Connection Status', 'mollie-payments-for-woocommerce'),
            'value' => $this->connectionStatus($settings, $connectionStatus),
            'type' => 'mollie_custom_input',
        ];
    }

    /**
     * @param array{connected?: bool, error_kind?: string, error_code?: int, error_message?: string} $connectionStatus
     */
    protected function connectionStatus(Settings $settings, array $connectionStatus): ?string
    {
        $testMode = $settings->isTestModeEnabled();
        if (!($connectionStatus['connected'] ?? false)) {
            return $this->connectionErrorMessage($connectionStatus);
        }
        if ($testMode) {
            return __('Successfully connected with <strong>Test API</strong> &#x2713;', 'mollie-payments-for-woocommerce');
        }
        return __('Successfully connected with <strong>Live API</strong> &#x2713;', 'mollie-payments-for-woocommerce');
    }

    /**
     * Describe why the connection failed, so the merchant does not troubleshoot the
     * API keys when the cause is an outage, rate limiting or their own server.
     *
     * @param array{connected?: bool, error_kind?: string, error_code?: int, error_message?: string} $connectionStatus
     */
    protected function connectionErrorMessage(array $connectionStatus): string
    {
        $errorKind = (string) ($connectionStatus['error_kind'] ?? Settings::ERROR_KIND_API_KEY);
        $errorCode = (int) ($connectionStatus['error_code'] ?? 0);
        $errorMessage = (string) ($connectionStatus['error_message'] ?? '');

        if ($errorKind === Settings::ERROR_KIND_INCOMPATIBLE) {
            return $errorMessage !== ''
                ? sprintf(
                    /* translators: Placeholder 1: the compatibility problems found on this installation. */
                    __(
                        'This installation cannot connect to Mollie: %1$s &#x2716;',
                        'mollie-payments-for-woocommerce'
                    ),
                    $errorMessage
                )
                : __(
                    'This installation does not meet the requirements to connect to Mollie &#x2716;',
                    'mollie-payments-for-woocommerce'
                );
        }

        if ($errorKind === Settings::ERROR_KIND_API_KEY) {
            return $errorMessage !== ''
                ? $errorMessage . ' &#x2716;'
                : __(
                    'Failed to connect to Mollie API - check your API keys &#x2716;',
                    'mollie-payments-for-woocommerce'
                );
        }

        if ($errorCode === 401 || $errorCode === 403) {
            return __(
                'Failed to connect to Mollie API - check your API keys &#x2716;',
                'mollie-payments-for-woocommerce'
            );
        }

        //see https://status.mollie.com/
        if ($errorCode >= 500) {
            return sprintf(
                /* translators: Placeholder 1: opening link tag to the Mollie status page. Placeholder 2: closing link tag. */
                __(
                    'Mollie is currently experiencing issues, please try again later - check the %1$sMollie status page%2$s &#x2716;',
                    'mollie-payments-for-woocommerce'
                ),
                '<a href="https://status.mollie.com/" target="_blank">',
                '</a>'
            );
        }

        if ($errorCode === 429) {
            return __(
                'Too many requests, please wait and try again &#x2716;',
                'mollie-payments-for-woocommerce'
            );
        }

        if ($errorCode === 0 && $errorMessage !== '') {
            return sprintf(
                /* translators: Placeholder 1: the underlying connection error reported by the server. */
                __(
                    'Could not reach the Mollie API from your server - check your outbound connectivity and SSL configuration: %1$s &#x2716;',
                    'mollie-payments-for-woocommerce'
                ),
                esc_html($errorMessage)
            );
        }

        if ($errorMessage !== '') {
            return sprintf(
                /* translators: Placeholder 1: the error reported by the Mollie API. */
                __(
                    'Communicating with Mollie failed: %1$s &#x2716;',
                    'mollie-payments-for-woocommerce'
                ),
                esc_html($errorMessage)
            );
        }

        return __(
            'Failed to connect to Mollie API - check your API keys &#x2716;',
            'mollie-payments-for-woocommerce'
        );
    }
}

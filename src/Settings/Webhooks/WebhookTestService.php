<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Settings\Webhooks;

use Mollie\WooCommerce\SDK\Api;
use Mollie\WooCommerce\Settings\Settings;
use Mollie\Psr\Log\LoggerInterface as Logger;
use WP_Error;
/**
 * Service to handle webhook connection testing
 */
class WebhookTestService
{
    private const TRANSIENT_PREFIX = 'mollie_webhook_test_';
    private const TRANSIENT_EXPIRATION = 300;
    // 5 minutes to give user time to complete test payment
    /**
     * @var Api
     */
    private $apiHelper;
    /**
     * @var Settings
     */
    private $settingsHelper;
    /**
     * @var Logger
     */
    private $logger;
    /**
     * WebhookTestService constructor.
     *
     * @param Api $apiHelper Mollie API helper
     * @param Settings $settingsHelper Settings helper
     * @param Logger $logger Logger instance
     */
    public function __construct(Api $apiHelper, Settings $settingsHelper, Logger $logger)
    {
        $this->apiHelper = $apiHelper;
        $this->settingsHelper = $settingsHelper;
        $this->logger = $logger;
    }
    /**
     * Register AJAX handlers
     *
     * @return void
     */
    public function registerAjaxHandlers(): void
    {
        add_action('wp_ajax_mollie_webhook_test_initiate', [$this, 'handleInitiateTest']);
        add_action('wp_ajax_mollie_webhook_test_check', [$this, 'handleCheckTest']);
    }
    /**
     * Handle webhook test initiation
     *
     * @return void
     */
    public function handleInitiateTest(): void
    {
        if (!$this->verifyNonce()) {
            wp_send_json_error(['message' => __('Security check failed. Please refresh the page and try again.', 'mollie-payments-for-woocommerce')], 403);
        }
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('You do not have permission to perform this action.', 'mollie-payments-for-woocommerce')], 403);
        }
        try {
            $testId = $this->generateTestId();
            // Initialize test state in transient
            $this->setTestState($testId, ['initiated_at' => time(), 'webhook_received' => \false, 'payment_id' => null, 'checkout_url' => null]);
            // Create test payment with Mollie
            $paymentResult = $this->createTestPayment($testId);
            if (is_wp_error($paymentResult)) {
                $this->logger->debug(__METHOD__ . ': Failed to create test payment - ' . $paymentResult->get_error_message());
                wp_send_json_error(['message' => sprintf(__('Failed to create test payment: %s', 'mollie-payments-for-woocommerce'), $paymentResult->get_error_message())], 500);
            }
            $this->updateTestState($testId, ['payment_id' => $paymentResult['payment_id'], 'checkout_url' => $paymentResult['checkout_url']]);
            $this->logger->debug(__METHOD__ . ": Webhook test initiated with ID: {$testId}, Payment ID: {$paymentResult['payment_id']}");
            wp_send_json_success(['test_id' => $testId, 'payment_id' => $paymentResult['payment_id'], 'checkout_url' => $paymentResult['checkout_url']]);
        } catch (\Exception $e) {
            $this->logger->debug(__METHOD__ . ': Exception during webhook test - ' . $e->getMessage());
            wp_send_json_error(['message' => sprintf(__('An error occurred: %s', 'mollie-payments-for-woocommerce'), $e->getMessage())], 500);
        }
    }
    /**
     * Handle webhook test result check
     *
     * @return void
     */
    public function handleCheckTest(): void
    {
        if (!$this->verifyNonce()) {
            wp_send_json_error(['message' => __('Security check failed.', 'mollie-payments-for-woocommerce')], 403);
        }
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('You do not have permission to perform this action.', 'mollie-payments-for-woocommerce')], 403);
        }
        $testId = filter_input(\INPUT_POST, 'test_id', \FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        if (empty($testId)) {
            wp_send_json_error(['message' => __('Invalid test ID.', 'mollie-payments-for-woocommerce')], 400);
        }
        $testState = $this->getTestState($testId);
        if (!$testState) {
            wp_send_json_error(['message' => __('Test not found or expired.', 'mollie-payments-for-woocommerce')], 404);
        }
        $completed = $testState['webhook_received'];
        $message = $this->getTestResultMessage($testState);
        wp_send_json_success(['completed' => $completed, 'webhook_received' => $testState['webhook_received'], 'message' => $message, 'payment_id' => $testState['payment_id'] ?? null]);
    }
    /**
     * Mark webhook as received for a test
     *
     * @param string $testId Test identifier
     * @return bool Whether the webhook was successfully marked
     */
    public function markWebhookReceived(string $testId): bool
    {
        $this->logger->debug(__METHOD__ . ": Marking webhook received for test ID: {$testId}");
        return $this->updateTestState($testId, ['webhook_received' => \true, 'received_at' => time()]);
    }
    /**
     * Create a test payment with Mollie
     *
     * @param string $testId Test identifier
     * @return array|WP_Error Array with payment_id and checkout_url, or error
     */
    private function createTestPayment(string $testId)
    {
        try {
            // Always use test API key for webhook tests
            $apiKey = $this->settingsHelper->getApiKey(\true);
            if (!$apiKey) {
                return new WP_Error('no_api_key', __('No test API key configured. Please configure your Mollie test API key first.', 'mollie-payments-for-woocommerce'));
            }
            $webhookUrl = $this->getWebhookUrl($testId);
            $returnUrl = admin_url('admin.php?page=wc-settings&tab=mollie_settings&section=mollie_advanced');
            $paymentData = ['amount' => ['currency' => get_woocommerce_currency(), 'value' => '0.01'], 'description' => sprintf(__('Webhook Test - %s', 'mollie-payments-for-woocommerce'), $testId), 'redirectUrl' => $returnUrl, 'webhookUrl' => $webhookUrl, 'metadata' => ['webhook_test' => \true, 'test_id' => $testId]];
            $this->logger->debug(__METHOD__ . ': Creating test payment with data: ' . wp_json_encode($paymentData));
            $payment = $this->apiHelper->getApiClient($apiKey)->payments->create($paymentData);
            $checkoutUrl = $payment->getCheckoutUrl();
            $this->logger->debug(__METHOD__ . ": Test payment created - ID: {$payment->id}, Checkout URL: {$checkoutUrl}");
            return ['payment_id' => $payment->id, 'checkout_url' => $checkoutUrl];
        } catch (\Exception $e) {
            $this->logger->debug(__METHOD__ . ': Failed to create test payment: ' . $e->getMessage());
            return new WP_Error('payment_creation_failed', $e->getMessage());
        }
    }
    /**
     * Get webhook URL for test
     *
     * @param string $testId Test identifier
     * @return string Webhook URL
     */
    private function getWebhookUrl(string $testId): string
    {
        // Use the REST API webhook endpoint
        $webhookUrl = rest_url('mollie/v1/webhook');
        $webhookUrl = add_query_arg(['test_id' => $testId], $webhookUrl);
        // Convert domain to ASCII for international domains
        return $this->asciiDomainName($webhookUrl);
    }
    /**
     * Convert domain in URL to ASCII
     *
     * @param string $url URL to convert
     * @return string URL with ASCII domain
     */
    private function asciiDomainName(string $url): string
    {
        $parsed = wp_parse_url($url);
        $scheme = $parsed['scheme'] ?? 'https';
        $domain = $parsed['host'] ?? '';
        $path = $parsed['path'] ?? '';
        $query = $parsed['query'] ?? '';
        if (empty($domain)) {
            return $url;
        }
        if (function_exists('idn_to_ascii')) {
            if (defined('IDNA_NONTRANSITIONAL_TO_ASCII') && defined('INTL_IDNA_VARIANT_UTS46')) {
                $domain = idn_to_ascii($domain, \IDNA_NONTRANSITIONAL_TO_ASCII, \INTL_IDNA_VARIANT_UTS46) ?: $domain;
            } else {
                $domain = idn_to_ascii($domain) ?: $domain;
            }
        }
        return $scheme . '://' . $domain . $path . ($query ? '?' . $query : '');
    }
    /**
     * Verify AJAX nonce
     *
     * @return bool Whether nonce is valid
     */
    private function verifyNonce(): bool
    {
        $nonce = filter_input(\INPUT_POST, 'nonce', \FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        return (bool) wp_verify_nonce($nonce, 'mollie_webhook_test_nonce');
    }
    /**
     * Generate unique test ID
     *
     * @return string Test ID
     */
    private function generateTestId(): string
    {
        return 'test_' . wp_generate_password(16, \false);
    }
    /**
     * Get transient key for test
     *
     * @param string $testId Test identifier
     * @return string Transient key
     */
    private function getTransientKey(string $testId): string
    {
        return self::TRANSIENT_PREFIX . $testId;
    }
    /**
     * Set test state in transient
     *
     * @param string $testId Test identifier
     * @param array $state State data
     * @return bool Whether state was set successfully
     */
    private function setTestState(string $testId, array $state): bool
    {
        return set_transient($this->getTransientKey($testId), $state, self::TRANSIENT_EXPIRATION);
    }
    /**
     * Get test state from transient
     *
     * @param string $testId Test identifier
     * @return array|false Test state or false if not found
     */
    private function getTestState(string $testId)
    {
        return get_transient($this->getTransientKey($testId));
    }
    /**
     * Update test state in transient
     *
     * @param string $testId Test identifier
     * @param array $updates State updates
     * @return bool Whether state was updated successfully
     */
    private function updateTestState(string $testId, array $updates): bool
    {
        $currentState = $this->getTestState($testId);
        if ($currentState === \false) {
            return \false;
        }
        $newState = array_merge($currentState, $updates);
        return set_transient($this->getTransientKey($testId), $newState, self::TRANSIENT_EXPIRATION);
    }
    /**
     * Get human-readable test result message
     *
     * @param array $testState Test state data
     * @return string Result message
     */
    private function getTestResultMessage(array $testState): string
    {
        if ($testState['webhook_received']) {
            $duration = isset($testState['received_at']) && isset($testState['initiated_at']) ? $testState['received_at'] - $testState['initiated_at'] : 0;
            return sprintf(__('✓ Webhook received successfully! (Response time: %d seconds)', 'mollie-payments-for-woocommerce'), $duration);
        }
        $elapsed = time() - ($testState['initiated_at'] ?? time());
        if ($elapsed > 30) {
            return __('⚠ Webhook not received. This might indicate a connection issue. Check your firewall settings or contact your hosting provider.', 'mollie-payments-for-woocommerce');
        }
        return __('Waiting for webhook...', 'mollie-payments-for-woocommerce');
    }
}

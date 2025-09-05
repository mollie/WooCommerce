<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\ReturnPage\framework;

use Psr\Container\ContainerInterface;

/**
 * Main Return Page Manager - The Core Engine
 */
class ReturnPageManager
{
    private array $configs = [];
    /**
     * @var mixed
     */
    private $pluginUrl;

    public function __construct(ContainerInterface $container) {
        $this->pluginUrl = $container->get('shared.plugin_url');
    }

    public function registerPaymentMethod(ReturnPageConfig $config): void
    {
        $this->configs[$config->getPaymentMethodId()] = $config;
    }

    public function init(): void
    {
        add_action('rest_api_init', [$this, 'registerRestRoutes']);
        add_action('woocommerce_thankyou', [$this, 'handleReturnPage'], 5);
    }

    /**
     * Register REST API endpoints for all payment methods
     */
    public function registerRestRoutes(): void
    {
        register_rest_route('wc-return-page/v1', '/status/(?P<order_id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'getOrderStatusApi'],
            'permission_callback' => [$this, 'validateOrderAccess'],
            'args' => [
                'order_id' => ['validate_callback' => fn($param) => is_numeric($param)],
                'key' => ['required' => true],
                'payment_method' => ['required' => true]
            ]
        ]);

        register_rest_route('wc-return-page/v1', '/trigger/(?P<order_id>\d+)', [
            'methods' => 'POST',
            'callback' => [$this, 'triggerStatusUpdate'],
            'permission_callback' => [$this, 'validateOrderAccess'],
        ]);
    }

    /**
     * Handle the return page for supported payment methods
     */
    public function handleReturnPage(int $order_id): void
    {
        $order = wc_get_order($order_id);
        if (!$order instanceof \WC_Order) {
            return;
        }

        $payment_method = $order->get_payment_method();
        $config = $this->configs[$payment_method] ?? null;

        if (!$config || !$config->shouldMonitor($order)) {
            return;
        }
        //here we know is our gateway or we would have bailed
        // Check initial status
        $status = $config->getStatusChecker()->checkStatus($order);

        if ($status === ReturnPageStatus::SUCCESS) {
            return; // No monitoring needed
        }

        $this->enqueueReturnPageAssets($order, $config);
        $this->executeStatusActions($order, $status, $config);
    }

    /**
     * REST API: Get order status
     */
    public function getOrderStatusApi(\WP_REST_Request $request): \WP_REST_Response
    {
        $order_id = (int)$request['order_id'];
        $payment_method = sanitize_text_field($request['payment_method']);

        $order = wc_get_order($order_id);
        $config = $this->configs[$payment_method] ?? null;

        if (!$order || !$config) {
            return new \WP_REST_Response(['error' => 'Order or config not found'], 404);
        }

        $status = $config->getStatusChecker()->checkStatus($order);

        return new \WP_REST_Response([
                                         'order_id' => $order_id,
                                         'status' => $status->value,
                                         'needs_payment' => $order->needs_payment(),
                                         'timestamp' => time()
                                     ]);
    }

    /**
     * REST API: Trigger manual status update
     */
    public function triggerStatusUpdate(\WP_REST_Request $request): \WP_REST_Response
    {
        $order_id = (int)$request['order_id'];
        $payment_method = sanitize_text_field($request['payment_method'] ?? '');

        $order = wc_get_order($order_id);
        $config = $this->configs[$payment_method] ?? null;

        if (!$order || !$config) {
            return new \WP_REST_Response(['error' => 'Order or config not found'], 404);
        }

        // Log the timeout incident
        if ($config->getIncidentLogger()) {
            $config->getIncidentLogger()->logTimeout($order, [
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'ip' => $_SERVER['REMOTE_ADDR'] ?? ''
            ]);
        }

        // Trigger manual update if available
        $updateResult = false;
        if ($config->getStatusUpdater()) {
            $updateResult = $config->getStatusUpdater()->updateStatus($order);
        }

        // Check status again
        $newStatus = $config->getStatusChecker()->checkStatus($order);

        return new \WP_REST_Response([
                                         'order_id' => $order_id,
                                         'update_triggered' => true,
                                         'update_result' => $updateResult,
                                         'new_status' => $newStatus->value,
                                         'needs_payment' => $order->needs_payment(),
                                         'timestamp' => time()
                                     ]);
    }

    /**
     * Validate REST API access
     */
    public function validateOrderAccess(\WP_REST_Request $request): bool
    {
        $order_id = (int)$request['order_id'];
        $key = sanitize_text_field($request['key'] ?? '');

        if (!$order_id || !$key) {
            return false;
        }

        $order = wc_get_order($order_id);
        return $order instanceof \WC_Order && $order->key_is_valid($key);
    }

    /**
     * Enqueue JavaScript and CSS for return page monitoring
     */
    private function enqueueReturnPageAssets(\WC_Order $order, ReturnPageConfig $config): void
    {
        if (!is_order_received_page()) {
            return;
        }
        wp_enqueue_script(
            'wc-return-page-monitor',
            $this->pluginUrl . ltrim('public/js/return-page-monitor.min.js', '/'),
            ['jquery'],
            null,
            true
        );
        wp_enqueue_script(
            'mollie-return-page-monitor',
            $this->pluginUrl . ltrim('public/js/mollie-return-page-monitor.min.js', '/'),
            ['jquery'],
            null,
            true
        );

        wp_enqueue_style(
            'wc-return-page-monitor',
            $this->pluginUrl . ltrim('public/css/return-page-monitor.min.css'),
            [],
            null
        );

        // Localize script with configuration
        wp_localize_script('wc-return-page-monitor', 'WCReturnPageConfig', [
            'order_id' => $order->get_id(),
            'order_key' => $order->get_order_key(),
            'payment_method' => $config->getPaymentMethodId(),
            'rest_url' => rest_url('wc-return-page/v1/'),
            'nonce' => wp_create_nonce('wp_rest'),
            'retry_count' => $config->getRetryCount($order),
            'interval' => $config->getInterval($order),
            'messages' => array_merge([
                                          'loading' => __('Checking payment status...', 'woocommerce'),
                                          'success' => __('Payment confirmed!', 'woocommerce'),
                                          'failed' => __('Payment failed', 'woocommerce'),
                                          'timeout' => __(
                                              'Payment verification is taking longer than expected',
                                              'woocommerce'
                                          ),
                                          'error' => __('Unable to verify payment status', 'woocommerce')
                                      ], $config->getMessages())
        ]);
    }

    /**
     * Execute status-specific actions
     */
    private function executeStatusActions(\WC_Order $order, ReturnPageStatus $status, ReturnPageConfig $config): void
    {
        $statusActions = $config->getStatusActions();
        if (isset($statusActions[$status->value])) {
            $statusActions[$status->value]->execute($order, $status);
        }
    }
}

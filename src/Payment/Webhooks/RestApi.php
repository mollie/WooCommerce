<?php

namespace Mollie\WooCommerce\Payment\Webhooks;

use Mollie\WooCommerce\Payment\MollieOrderService;
use Mollie\WooCommerce\Settings\Webhooks\WebhookTestService;
use Psr\Log\LoggerInterface;
use WP_REST_Request;

class RestApi
{
    public const ROUTE_NAMESPACE = 'mollie/v1';
    public const WEBHOOK_ROUTE = 'webhook';
    private MollieOrderService $mollieOrderService;
    private LoggerInterface $logger;
    private WebhookTestService $webhookTestService;

    /**
     * Constructor method for initializing the class with necessary dependencies.
     *
     * @param MollieOrderService $mollieOrderService Service to handle orders through Mollie.
     * @param LoggerInterface $logger Logger interface for logging purposes.
     *
     * @return void
     */
    public function __construct(MollieOrderService $mollieOrderService, LoggerInterface $logger, WebhookTestService $webhookTestService)
    {
        $this->mollieOrderService = $mollieOrderService;
        $this->logger = $logger;
        $this->webhookTestService = $webhookTestService;
    }

    /**
     * Registers REST API routes for the application.
     *
     * This method defines and registers a specific REST route under the given namespace,
     * along with its callback and permission settings.
     *
     * @return void
     */
    public function registerRoutes()
    {
        register_rest_route(self::ROUTE_NAMESPACE, self::WEBHOOK_ROUTE, [
            [
                'methods' => 'POST',
                'callback' => [$this, 'callback'],
                'permission_callback' => '__return_true',
            ],
        ]);
    }

    /**
     * Handles the callback request from Mollie and processes the payment.
     *
     * @param WP_REST_Request $request The REST request object containing callback parameters.
     *
     * @return \WP_REST_Response A response object with the corresponding status code.
     * - 200: When the request is successfully handled, whether for testing, no results, or successful processing.
     * - 404: When the "id" parameter is not provided in the request.
     */
    public function callback(WP_REST_Request $request)
    {
        $testId = $request->get_param('test_id');
        if ($testId) {
            return $this->handleWebhookTest($request, $testId);
        }

        // Answer Mollie Test request.
        if ($request->get_param('testByMollie') === '') {
            $this->logger->debug(__METHOD__ . ': REST Webhook tested by Mollie.');
            return new \WP_REST_Response(null, 200);
        }

        //check that id in post is set with transaction_id
        $transactionID = $request->get_param('id');
        if (! $transactionID) {
            $this->logger->debug(__METHOD__ . ': No transaction ID provided.');
            return new \WP_REST_Response(null, 404);
        }
        $this->logger->debug(__METHOD__ . ': Received WP-REST-API webhook with transaction ID: ' . $transactionID);

        $orders = wc_get_orders([
            'transaction_id' => $transactionID,
            'limit' => 2,
        ]);

        if (! $orders) {
            $this->logger->debug(__METHOD__ . ': No orders found for transaction ID: ' . $transactionID . ' fall back to search in meta data');
            //Fallback search order in order mollie oder meta
            $orders = wc_get_orders([
                'limit' => 2,
                'meta_key' => substr($transactionID, 0, 4) === 'ord_' ? '_mollie_order_id' : '_mollie_payment_id',
                'meta_compare' => '=',
                'meta_value' => $transactionID,
            ]);
            if (! $orders) {
                $this->logger->debug(__METHOD__ . ': No orders found in mollie meta for transaction ID: ' . $transactionID);
                return new \WP_REST_Response(null, 200);
            }
        }

        if (count($orders) > 1) {
            $this->logger->debug(__METHOD__ . ': More than one order found for transaction ID: ' . $transactionID);
            return new \WP_REST_Response(null, 200);
        }

        $this->mollieOrderService->doPaymentForOrder($orders[0]);

        return new \WP_REST_Response(null, 200);
    }

    /**
     * Handle webhook test request
     *
     * @param WP_REST_Request $request Request object
     * @param string $testId Test identifier
     * @return \WP_REST_Response Response object
     */
    private function handleWebhookTest(WP_REST_Request $request, string $testId): \WP_REST_Response
    {
        $this->logger->debug(__METHOD__ . ": Received webhook test request for test ID: {$testId}");

        // Get transaction ID from request
        $transactionId = $request->get_param('id');

        if (!$transactionId) {
            $this->logger->debug(__METHOD__ . ': Webhook test received but no transaction ID provided.');
            // Still mark as received - the test payment was created successfully
            $this->webhookTestService->markWebhookReceived($testId);
            return new \WP_REST_Response(null, 200);
        }

        // Log the payment ID
        $this->logger->debug(__METHOD__ . ": Webhook test received with payment ID: {$transactionId}");

        // Mark webhook as received
        $marked = $this->webhookTestService->markWebhookReceived($testId);

        if ($marked) {
            $this->logger->debug(__METHOD__ . ": Successfully marked webhook test {$testId} as received.");
        } else {
            $this->logger->debug(__METHOD__ . ": Failed to mark webhook test {$testId} - test may have expired.");
        }

        // Return 200 OK to acknowledge receipt
        return new \WP_REST_Response(null, 200);
    }
}

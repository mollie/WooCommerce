<?php

namespace Mollie\WooCommerce\Payment\Webhooks;

use Mollie\Api\Exceptions\ApiException;
use Mollie\WooCommerce\Payment\MollieOrderService;
use Mollie\WooCommerce\Settings\Webhooks\WebhookTestService;
use Mollie\Psr\Log\LoggerInterface;
use WP_REST_Request;
class RestApi
{
    public const ROUTE_NAMESPACE = 'mollie/v1';
    public const WEBHOOK_ROUTE = 'webhook';
    private MollieOrderService $mollieOrderService;
    private LoggerInterface $logger;
    private WebhookTestService $webhookTestService;
    private \Mollie\WooCommerce\Payment\Webhooks\WebhookSecret $webhookSecret;
    /**
     * Constructor method for initializing the class with necessary dependencies.
     *
     * @param MollieOrderService $mollieOrderService Service to handle orders through Mollie.
     * @param LoggerInterface $logger Logger interface for logging purposes.
     *
     * @return void
     */
    public function __construct(MollieOrderService $mollieOrderService, LoggerInterface $logger, WebhookTestService $webhookTestService, \Mollie\WooCommerce\Payment\Webhooks\WebhookSecret $webhookSecret)
    {
        $this->mollieOrderService = $mollieOrderService;
        $this->logger = $logger;
        $this->webhookTestService = $webhookTestService;
        $this->webhookSecret = $webhookSecret;
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
        register_rest_route(self::ROUTE_NAMESPACE, self::WEBHOOK_ROUTE, [['methods' => 'POST', 'callback' => [$this, 'callback'], 'permission_callback' => function (WP_REST_Request $request) {
            if ($this->isWebhookRequestAuthenticated($request)) {
                return \true;
            }
            return new \WP_Error('rest_forbidden', 'Invalid webhook secret.', ['status' => 401]);
        }]]);
    }
    /**
     * Authenticate an incoming REST webhook request.
     *
     * A request is trusted when it carries EITHER:
     *  - a valid mollie_webhook_secret (used by webhook URLs built after the secret was
     *    introduced), OR
     *  - a transaction id that resolves to an order we already know about. REST webhook URLs
     *    created before the secret existed are bare (only the id is POSTed by Mollie), so this
     *    keeps in-flight payments working after an upgrade without failing transactions.
     *
     * An anonymous caller has neither and is rejected. Note the id-based fallback is weaker
     * than the secret: it authenticates by referencing a known payment rather than proving a
     * shared secret, and it runs one indexed order lookup for well-formed ids.
     */
    private function isWebhookRequestAuthenticated(WP_REST_Request $request): bool
    {
        if ($this->webhookSecret->check($request->get_param('mollie_webhook_secret'))) {
            return \true;
        }
        $transactionId = $request->get_param('id');
        if (!is_string($transactionId) || $transactionId === '') {
            return \false;
        }
        // Only spend a DB lookup on plausibly real Mollie ids.
        if (strpos($transactionId, 'tr_') !== 0 && strpos($transactionId, 'ord_') !== 0) {
            return \false;
        }
        return $this->orderExistsForTransactionId($transactionId);
    }
    /**
     * Whether an order already exists for the given Mollie transaction id, using the same
     * lookup order as callback(): transaction_id first, then the Mollie order/payment meta.
     */
    private function orderExistsForTransactionId(string $transactionId): bool
    {
        $orders = wc_get_orders(['transaction_id' => $transactionId, 'limit' => 1]);
        if ($orders) {
            return \true;
        }
        $orders = wc_get_orders(['limit' => 1, 'meta_key' => substr($transactionId, 0, 4) === 'ord_' ? '_mollie_order_id' : '_mollie_payment_id', 'meta_compare' => '=', 'meta_value' => $transactionId]);
        return (bool) $orders;
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
        if (!$transactionID) {
            $this->logger->debug(__METHOD__ . ': No transaction ID provided.');
            return new \WP_REST_Response(null, 404);
        }
        $this->logger->debug(__METHOD__ . ': Received WP-REST-API webhook with transaction ID: ' . $transactionID);
        $orders = wc_get_orders(['transaction_id' => $transactionID, 'limit' => 2]);
        if (!$orders) {
            $this->logger->debug(__METHOD__ . ': No orders found for transaction ID: ' . $transactionID . ' fall back to search in meta data');
            //Fallback search order in order mollie oder meta
            $orders = wc_get_orders(['limit' => 2, 'meta_key' => substr($transactionID, 0, 4) === 'ord_' ? '_mollie_order_id' : '_mollie_payment_id', 'meta_compare' => '=', 'meta_value' => $transactionID]);
            if (!$orders) {
                $this->logger->debug(__METHOD__ . ': No orders found in mollie meta for transaction ID: ' . $transactionID);
                try {
                    $redirectUrl = $this->mollieOrderService->getRedirectUrlFromPaymentObject($transactionID);
                    $order_id = $this->mollieOrderService->getOrderIdFromRedirectUrl($redirectUrl);
                    $key = $this->mollieOrderService->getKeyFromRedirectUrl($redirectUrl);
                    $this->mollieOrderService->onWebhookActionFallback($order_id, $key, $transactionID);
                    return new \WP_REST_Response(null, 200);
                } catch (ApiException $exception) {
                    $this->logger->debug($exception->getMessage());
                    return new \WP_REST_Response(null, 500);
                }
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

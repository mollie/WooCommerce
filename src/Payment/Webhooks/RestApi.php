<?php

namespace Mollie\WooCommerce\Payment\Webhooks;

use Mollie\Api\Exceptions\ApiException;
use Mollie\WooCommerce\Adapter\WordPress\EventLog;
use Mollie\WooCommerce\Adapter\WordPress\OrderLockTimeout;
use Mollie\WooCommerce\Payment\MollieOrderService;
use Mollie\WooCommerce\Settings\Webhooks\WebhookTestService;
use Mollie\WooCommerce\Workflow\ResolveExpressPayment;
use Psr\Log\LoggerInterface;
use WP_REST_Request;

class RestApi
{
    public const ROUTE_NAMESPACE = 'mollie/v1';
    public const WEBHOOK_ROUTE = 'webhook';
    private MollieOrderService $mollieOrderService;
    private LoggerInterface $logger;
    private WebhookTestService $webhookTestService;
    private WebhookSecret $webhookSecret;
    private EventLog $log;
    private ResolveExpressPayment $resolveExpressPayment;

    /**
     * Constructor method for initializing the class with necessary dependencies.
     *
     * @param MollieOrderService $mollieOrderService Service to handle orders through Mollie.
     * @param LoggerInterface $logger Logger interface for logging purposes.
     * @param EventLog $log Named, allowlisted events for the webhook callback.
     * @param ResolveExpressPayment $resolveExpressPayment The lookup stage for payments the plugin never created.
     *
     * @return void
     */
    public function __construct(
        MollieOrderService $mollieOrderService,
        LoggerInterface $logger,
        WebhookTestService $webhookTestService,
        WebhookSecret $webhookSecret,
        EventLog $log,
        ResolveExpressPayment $resolveExpressPayment
    ) {
        $this->mollieOrderService = $mollieOrderService;
        $this->logger = $logger;
        $this->webhookTestService = $webhookTestService;
        $this->webhookSecret = $webhookSecret;
        $this->log = $log;
        $this->resolveExpressPayment = $resolveExpressPayment;
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
                'permission_callback' => function (WP_REST_Request $request) {
                    if ($this->isWebhookRequestAuthenticated($request)) {
                        return true;
                    }
                    return new \WP_Error('rest_forbidden', 'Invalid webhook secret.', ['status' => 401]);
                },
            ],
        ]);
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
            return true;
        }

        $transactionId = $request->get_param('id');
        if (!is_string($transactionId) || $transactionId === '') {
            return false;
        }
        // Only spend a DB lookup on plausibly real Mollie ids.
        if (strpos($transactionId, 'tr_') !== 0 && strpos($transactionId, 'ord_') !== 0) {
            return false;
        }

        return $this->orderExistsForTransactionId($transactionId);
    }

    /**
     * Whether an order already exists for the given Mollie transaction id, using the same
     * lookup order as callback(): transaction_id first, then the Mollie order/payment meta.
     */
    private function orderExistsForTransactionId(string $transactionId): bool
    {
        $orders = wc_get_orders([
            'transaction_id' => $transactionId,
            'limit' => 1,
        ]);
        if ($orders) {
            return true;
        }

        $orders = wc_get_orders([
            'limit' => 1,
            'meta_key' => substr($transactionId, 0, 4) === 'ord_' ? '_mollie_order_id' : '_mollie_payment_id',
            'meta_compare' => '=',
            'meta_value' => $transactionId,
        ]);

        return (bool) $orders;
    }

    /**
     * Handles the callback request from Mollie and processes the payment.
     *
     * The order is looked up by transaction id, then by the Mollie order/payment meta, then — for a
     * payment the plugin never created — by the express_ref in the payment's metadata, and last by
     * the order id and key in the payment's redirectUrl.
     *
     * @param WP_REST_Request $request The REST request object containing callback parameters.
     *
     * @return \WP_REST_Response A response object with the corresponding status code.
     * - 200: When the request is successfully handled, whether for testing, no results, or successful processing.
     * - 404: When the "id" parameter is not provided in the request.
     * - 503: When the order's lock could not be taken; nothing was written and Mollie retries.
     */
    public function callback(WP_REST_Request $request)
    {
        $testId = $request->get_param('test_id');
        if ($testId) {
            return $this->handleWebhookTest($request, $testId);
        }

        // Answer Mollie Test request.
        if ($request->get_param('testByMollie') === '') {
            $this->log->info('webhook.probe');
            return new \WP_REST_Response(null, 200);
        }

        //check that id in post is set with transaction_id
        $transactionID = $request->get_param('id');
        if (! $transactionID) {
            $this->log->info('webhook.refused', ['reason' => 'no_id']);
            return new \WP_REST_Response(null, 404);
        }
        $this->log->info('webhook.received', ['mollie_id' => (string) $transactionID]);

        $orders = $this->findOrders((string) $transactionID);

        if (! $orders) {
            try {
                $expressOrder = $this->resolveExpressPayment->resolve((string) $transactionID);
            } catch (OrderLockTimeout $timeout) {
                return new \WP_REST_Response(null, 503);
            }
            if ($expressOrder !== null) {
                $orders = [$expressOrder];
            }
        }

        if (! $orders) {
            $this->log->info('webhook.fallback', ['mollie_id' => (string) $transactionID]);
            try {
                $redirectUrl = $this->mollieOrderService->getRedirectUrlFromPaymentObject($transactionID);
                $order_id = $this->mollieOrderService->getOrderIdFromRedirectUrl($redirectUrl);
                $key = $this->mollieOrderService->getKeyFromRedirectUrl($redirectUrl);
                $this->mollieOrderService->onWebhookActionFallback($order_id, $key, $transactionID);
                return new \WP_REST_Response(null, 200);
            } catch (ApiException $exception) {
                // The exception text carries Mollie's response body; it is never logged (S-09).
                $this->log->warning('webhook.failed', ['mollie_id' => (string) $transactionID, 'kind' => 'outage']);
                return new \WP_REST_Response(null, 500);
            }
        }

        if (count($orders) > 1) {
            $this->log->warning('webhook.ambiguous', ['mollie_id' => (string) $transactionID]);
            return new \WP_REST_Response(null, 200);
        }

        $this->mollieOrderService->doPaymentForOrder($orders[0]);

        return new \WP_REST_Response(null, 200);
    }

    /**
     * The indexed lookups, in order: transaction_id, then the Mollie order or payment meta. At most
     * two orders, so an ambiguous id can be told apart from a unique one.
     *
     * @return array<int, \WC_Order>
     */
    private function findOrders(string $transactionId): array
    {
        $orders = wc_get_orders([
            'transaction_id' => $transactionId,
            'limit' => 2,
        ]);
        if ($orders) {
            return $orders;
        }

        return wc_get_orders([
            'limit' => 2,
            'meta_key' => substr($transactionId, 0, 4) === 'ord_' ? '_mollie_order_id' : '_mollie_payment_id',
            'meta_compare' => '=',
            'meta_value' => $transactionId,
        ]);
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

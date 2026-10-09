<?php

namespace Mollie\WooCommerceTests\Integration\spec\webhooks;

use Mockery;
use Mollie\WooCommerce\Payment\MollieOrderService;
use Mollie\WooCommerce\Payment\PaymentFactory;
use Mollie\WooCommerce\Payment\Webhooks\RestApi;
use Mollie\WooCommerce\Payment\Webhooks\WebhookHandler;
use Mollie\WooCommerce\Payment\Webhooks\WebhookSecret;
use Mollie\WooCommerce\SDK\HttpResponse;
use Mollie\WooCommerceTests\Integration\API\Traits\APIMockTrait;
use Mollie\WooCommerceTests\Integration\IntegrationMockedTestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface as Logger;
use WC_Order;
use WP_REST_Request;

/**
 * A webhook may only change an order with a payment Mollie created for that order.
 *
 * The legacy WC-API endpoint authenticates the caller with the order id and key found in the
 * request, and the payment id arrives in the POST body. Those two must not be enough to make an
 * unrelated payment of the same Mollie profile pay the order: the payment's own record at Mollie
 * (its metadata and redirect URL, written by the plugin at creation) decides which order it is for.
 *
 * @group integration
 * @group Webhooks
 */
class ForeignPaymentWebhookTest extends IntegrationMockedTestCase
{
    use APIMockTrait;

    private const CANCELLED_PAYMENTS_STATUS_OPTION = 'mollie-payments-for-woocommerce_order_status_cancelled_payments';

    /** @var int[] HTTP status codes the code under test set during the request */
    private array $statusCodes = [];

    public function setUp(): void
    {
        parent::setUp();
        $this->initializeApiMock();
        $this->statusCodes = [];
        unset($_GET['order_id'], $_GET['key'], $_POST['id']);
    }

    public function tearDown(): void
    {
        unset($_GET['order_id'], $_GET['key'], $_GET['mollie_webhook_secret'], $_POST['id']);
        delete_option(self::CANCELLED_PAYMENTS_STATUS_OPTION);
        parent::tearDown();
    }

    /**
     * @test
     * @scenario A paid payment of another order cannot pay this one through the legacy endpoint
     *   Given a pending order linked to its own open attempt
     *   And a paid payment on the same Mollie profile that was created for another order
     *   When the legacy webhook is called with this order's id and key and that payment's id
     *   Then the order stays pending, keeps its own attempt and gets no "completed" note
     *   And the request ends without a PHP error
     */
    public function foreign_paid_payment_does_not_pay_the_order_through_the_legacy_endpoint(): void
    {
        $own = 'tr_' . uniqid();
        $foreign = 'tr_' . uniqid();
        $order = $this->pendingOrderLinkedTo($own);
        $this->mockSuccessfulPaymentGet($foreign, 'paid', $this->createdForOrder(999999));
        $container = $this->bootstrap();

        $this->legacyWebhookRequest($order, $order->get_order_key());
        $this->legacyWebhookService($container, $foreign)->onWebhookAction();

        $order = wc_get_order($order->get_id());
        $this->assertSame('pending', $order->get_status());
        $this->assertSame($own, $order->get_transaction_id());
        $this->assertSame($own, $order->get_meta('_mollie_payment_id'));
        $this->assertCount(0, $this->completedNotes($order));
        $this->assertNotContains(500, $this->statusCodes);
    }

    /**
     * @test
     * @scenario An authorized payment of another order cannot put this one on hold
     */
    public function foreign_authorized_payment_does_not_touch_the_order(): void
    {
        $own = 'tr_' . uniqid();
        $foreign = 'tr_' . uniqid();
        $order = $this->pendingOrderLinkedTo($own);
        $this->mockSuccessfulPaymentGet($foreign, 'authorized', $this->createdForOrder(999999));
        $container = $this->bootstrap();

        $container->get(MollieOrderService::class)->doPaymentForOrder($order, $foreign);

        $order = wc_get_order($order->get_id());
        $this->assertSame('pending', $order->get_status());
        $this->assertSame($own, $order->get_transaction_id());
    }

    /**
     * @test
     * @scenario A wrong order key is refused by the fallback with 401 and the request ends cleanly
     *   Given a pending order and an unlinked paid payment created for it
     *   When the legacy webhook is called with a valid webhook secret, the order id, a wrong key
     *     and that payment's id (so the authentication gate passes and the fallback decides)
     *   Then the response code is 401, nothing else, the order is untouched
     *   And no PHP error follows the refusal
     */
    public function wrong_order_key_is_refused_with_401_and_the_request_ends_cleanly(): void
    {
        $own = 'tr_' . uniqid();
        $foreign = 'tr_' . uniqid();
        $order = $this->pendingOrderLinkedTo($own);
        $this->mockSuccessfulPaymentGet($foreign, 'paid', $this->createdForOrder($order->get_id()));
        $container = $this->bootstrap();

        $this->legacyWebhookRequest($order, 'wc_order_wrongkey');
        $_GET['mollie_webhook_secret'] = $container->get(WebhookSecret::class)->getOrCreate();
        $this->legacyWebhookService($container, $foreign)->onWebhookAction();

        $this->assertSame([401], $this->statusCodes);
        $order = wc_get_order($order->get_id());
        $this->assertSame('pending', $order->get_status());
        $this->assertSame($own, $order->get_transaction_id());
    }

    /**
     * @test
     * @scenario A superseded attempt of this same order that gets paid still pays the order
     *   Given a pending order whose customer switched to a second attempt (tr_NEW)
     *   When Mollie reports the first attempt (tr_OLD), created for this order, as paid
     *   Then the order is paid: the money arrived for this order
     */
    public function superseded_attempt_of_the_same_order_still_pays_it_when_paid(): void
    {
        $new = 'tr_' . uniqid();
        $old = 'tr_' . uniqid();
        $order = $this->pendingOrderLinkedTo($new);
        $this->mockSuccessfulPaymentGet($old, 'paid', $this->createdForOrder($order->get_id()));
        $container = $this->bootstrap();

        $this->legacyWebhookRequest($order, $order->get_order_key());
        $this->legacyWebhookService($container, $old)->onWebhookAction();

        $order = wc_get_order($order->get_id());
        $this->assertSame('processing', $order->get_status());
        $this->assertSame($old, $order->get_transaction_id());
        $this->assertCount(1, $this->completedNotes($order));
    }

    /**
     * @test
     * @scenario A superseded attempt of this same order that is canceled does not cancel the order
     *   Given the shop cancels orders whose payment is cancelled
     *   And a pending order whose customer switched to a second attempt (tr_NEW)
     *   When Mollie reports the first attempt (tr_OLD), created for this order, as canceled
     *   Then the order stays pending on tr_NEW and tr_OLD is not recorded as its cancelled payment
     */
    public function superseded_attempt_of_the_same_order_that_is_canceled_leaves_the_order_pending(): void
    {
        $new = 'tr_' . uniqid();
        $old = 'tr_' . uniqid();
        $order = $this->pendingOrderLinkedTo($new);
        $this->mockSuccessfulPaymentGet($old, 'canceled', $this->createdForOrder($order->get_id()));
        update_option(self::CANCELLED_PAYMENTS_STATUS_OPTION, 'cancelled');
        $container = $this->bootstrap();

        $container->get(MollieOrderService::class)->doPaymentForOrder($order, $old);

        $order = wc_get_order($order->get_id());
        $this->assertSame('pending', $order->get_status());
        $this->assertSame($new, $order->get_transaction_id());
        $this->assertSame('', (string) $order->get_meta('_mollie_cancelled_payment_id'));
    }

    /**
     * @test
     * @scenario The REST endpoint resolves an unlinked payment from the payment itself, never from the caller
     *   Given order A whose superseded attempt (created for A, redirect URL of A) is paid
     *   And a pending order B the caller names in the request
     *   When the REST webhook receives that payment's id
     *   Then A is paid and B is untouched
     */
    public function rest_endpoint_pays_the_order_the_payment_was_created_for_not_the_one_the_caller_names(): void
    {
        $orderA = $this->pendingOrderLinkedTo('tr_' . uniqid());
        $orderB = $this->pendingOrderLinkedTo('tr_' . uniqid());
        $old = 'tr_' . uniqid();
        $this->mockSuccessfulPaymentGet($old, 'paid', $this->createdForOrder($orderA->get_id()) + [
            'redirectUrl' => add_query_arg(
                ['order_id' => $orderA->get_id(), 'key' => $orderA->get_order_key()],
                'https://example.com/return'
            ),
        ]);
        $container = $this->bootstrap();

        $this->legacyWebhookRequest($orderB, $orderB->get_order_key());
        $request = new WP_REST_Request('POST', '/mollie/v1/webhook');
        $request->set_param('id', $old);
        $request->set_param('order_id', (string) $orderB->get_id());
        $request->set_param('key', $orderB->get_order_key());
        $response = $container->get(RestApi::class)->callback($request);

        $this->assertSame(200, $response->get_status());
        $this->assertSame('processing', wc_get_order($orderA->get_id())->get_status());
        $this->assertSame('pending', wc_get_order($orderB->get_id())->get_status());
        $this->assertCount(0, $this->completedNotes(wc_get_order($orderB->get_id())));
    }

    private function pendingOrderLinkedTo(string $paymentId): WC_Order
    {
        $order = $this->getConfiguredOrder(1, 'mollie_wc_gateway_ideal', ['simple'], [], false, $paymentId);
        $order->update_meta_data('_mollie_payment_id', $paymentId);
        $order->update_meta_data('_mollie_payment_method', 'mollie_wc_gateway_ideal');
        $order->update_status('pending');
        $order->save();

        return $order;
    }

    /** What Mollie returns for a payment the plugin created for the given order. */
    private function createdForOrder(int $orderId): array
    {
        return ['metadata' => ['order_id' => $orderId], 'method' => 'ideal', 'mode' => 'test'];
    }

    private function bootstrap(): ContainerInterface
    {
        $codes = &$this->statusCodes;
        $httpResponse = new class ($codes) extends HttpResponse {
            private $codes;

            public function __construct(array &$codes)
            {
                $this->codes = &$codes;
            }

            public function setHttpResponseCode($statusCode): void
            {
                $this->codes[] = (int) $statusCode;
            }
        };

        return $this->bootstrapModule($this->getMockedApiServices() + [
            'SDK.HttpResponse' => static function () use ($httpResponse) {
                return $httpResponse;
            },
        ]);
    }

    /** The query string Mollie sends to the legacy endpoint: order id and key of the webhook URL. */
    private function legacyWebhookRequest(WC_Order $order, string $key): void
    {
        $_GET['order_id'] = (string) $order->get_id();
        $_GET['key'] = $key;
        $_SERVER['REQUEST_METHOD'] = 'POST';
    }

    /**
     * The real service with only the POST body read replaced: filter_input() cannot see a
     * $_POST set from a test, so the posted payment id is handed in directly.
     */
    private function legacyWebhookService(ContainerInterface $container, string $postedPaymentId): MollieOrderService
    {
        $service = Mockery::mock(MollieOrderService::class, [
            $container->get('SDK.HttpResponse'),
            $container->get(Logger::class),
            $container->get(PaymentFactory::class),
            $container->get('settings.data_helper'),
            $container->get('shared.plugin_id'),
            $container,
            $container->get(WebhookHandler::class),
        ])->makePartial()->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('getPaymentIdFromRequest')->andReturn($postedPaymentId);

        return $service;
    }

    private function completedNotes(WC_Order $order): array
    {
        return array_values(array_filter(
            wc_get_order_notes(['order_id' => $order->get_id()]),
            static function ($note): bool {
                return strpos($note->content, 'Order completed') !== false;
            }
        ));
    }
}

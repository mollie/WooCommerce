<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Integration\spec\webhooks;

use Mockery;
use Mollie\WooCommerce\SDK\HttpResponse;
use Mollie\WooCommerceTests\Integration\Common\Doubles\CanaryData;
use Mollie\WooCommerceTests\Integration\Common\ExpressFlowTestCase;
use Mollie\WooCommerceTests\Integration\Common\Traits\ExpressCheckoutFixtures;
use Mollie\WooCommerceTests\Integration\Common\Traits\WebhookOrderFixtures;
use WC_Order;

/**
 * The order status a Mollie payment status leads to, per payment method, for an order paid through
 * the Payments API at the ordinary checkout.
 *
 * It is the table the e2e suite walks in tests/qa/tests/05-transaction (one browser checkout per
 * row, against Mollie's test mode), entered here at the point where Mollie calls the webhook. Each
 * row is named after the e2e case it mirrors; tests/Integration/e2e-equivalence.md has the list.
 *
 * @group integration
 * @group Webhooks
 * @group LegacyWebhookStatusMatrix
 */
class LegacyWebhookStatusMatrixTest extends ExpressFlowTestCase
{
    use ExpressCheckoutFixtures;
    use WebhookOrderFixtures;

    /**
     * Payment status => the e2e case of each method that covers it.
     * Source: tests/qa/tests/05-transaction/_test-data/eur-checkout.data.ts and non-eur-checkout.data.ts.
     */
    private const E2E_CASES = [
        'mbway' => ['paid' => 'C4237595', 'failed' => 'C4237596', 'canceled' => 'C4237597', 'expired' => 'C4237598'],
        'multibanco' => ['paid' => 'C4237591', 'failed' => 'C4237592', 'canceled' => 'C4237593', 'expired' => 'C4237594'],
        'in3' => ['paid' => 'C420219', 'failed' => 'C420220', 'canceled' => 'C420221', 'expired' => 'C420222'],
        'bancontact' => ['paid' => 'C420230', 'open' => 'C420231', 'failed' => 'C420232', 'canceled' => 'C420233', 'expired' => 'C420234'],
        'przelewy24' => ['paid' => 'C420235', 'failed' => 'C420236', 'canceled' => 'C420237', 'expired' => 'C420238'],
        'ideal' => ['paid' => 'C420244', 'open' => 'C420245', 'failed' => 'C420246', 'canceled' => 'C420248', 'expired' => 'C420247'],
        'paypal' => ['paid' => 'C420253', 'pending' => 'C420254', 'failed' => 'C420255', 'canceled' => 'C420256', 'expired' => 'C420257'],
        'eps' => ['paid' => 'C420260', 'failed' => 'C420261', 'canceled' => 'C420262', 'expired' => 'C420263'],
        'kbc' => ['paid' => 'C420267', 'failed' => 'C420264', 'canceled' => 'C420265', 'expired' => 'C420266'],
        'creditcard' => ['open' => 'C420274', 'paid' => 'C420273', 'failed' => 'C420275', 'expired' => 'C420276'],
        'banktransfer' => ['paid' => 'C420284', 'open' => 'C420283', 'expired' => 'C420285'],
        'mybank' => ['paid' => 'C420286', 'failed' => 'C420287', 'canceled' => 'C420288', 'expired' => 'C420289'],
        'belfius' => ['paid' => 'C420298', 'failed' => 'C420299', 'canceled' => 'C420300', 'expired' => 'C420301'],
        'billie' => ['authorized' => 'C420302', 'failed' => 'C420303', 'canceled' => 'C420304', 'expired' => 'C420305'],
        'paysafecard' => ['paid' => 'C420306', 'canceled' => 'C420307', 'expired' => 'C420308'],
        'klarna' => ['authorized' => 'C3007259', 'failed' => 'C3007260', 'canceled' => 'C3007261', 'expired' => 'C3007262'],
        'bancomatpay' => ['paid' => 'C3007263', 'failed' => 'C3007264', 'canceled' => 'C3007265', 'expired' => 'C3007266'],
        'alma' => ['paid' => 'C3241643', 'failed' => 'C3241644', 'canceled' => 'C3241645', 'expired' => 'C3241646'],
        'riverty' => ['authorized' => 'C3622417', 'failed' => 'C3622418', 'canceled' => 'C3622419', 'expired' => 'C3622420'],
        'satispay' => ['paid' => 'C3757247', 'failed' => 'C3757248', 'canceled' => 'C3757249', 'expired' => 'C3757250'],
        'bizum' => ['paid' => 'C4257959'],
        'twint' => ['paid' => 'C3007271', 'failed' => 'C3007272', 'canceled' => 'C3007273', 'expired' => 'C3007274'],
        'blik' => ['paid' => 'C3007275', 'failed' => 'C3007276', 'canceled' => 'C3007277', 'expired' => 'C3007278'],
        'paybybank' => ['open' => 'C4567537', 'paid' => 'C4237599', 'failed' => 'C4237600', 'canceled' => 'C4237601', 'expired' => 'C4237602'],
        'swish' => ['paid' => 'C4237603', 'failed' => 'C4237604', 'canceled' => 'C4237605', 'expired' => 'C4237606'],
        'vipps' => ['paid' => 'C4268795', 'failed' => 'C4268796', 'canceled' => 'C4268797', 'expired' => 'C4268798'],
        'mobilepay' => ['paid' => 'C4268807', 'failed' => 'C4268808', 'canceled' => 'C4268809', 'expired' => 'C4268810'],
    ];

    /**
     * The currency the e2e case pays in, for the methods that do not take euros.
     */
    private const CURRENCIES = [
        'twint' => 'CHF',
        'blik' => 'PLN',
        'paybybank' => 'GBP',
        'swish' => 'SEK',
        'vipps' => 'NOK',
        'mobilepay' => 'DKK',
    ];

    /**
     * What the e2e suite expects for the Payments API (getOrderStatusFromMollieStatus()).
     */
    private const ORDER_STATUS = [
        'paid' => 'processing',
        'authorized' => 'processing',
        'canceled' => 'pending',
        'expired' => 'cancelled',
        'failed' => 'failed',
        'open' => 'pending',
        'pending' => 'pending',
    ];

    public function setUp(): void
    {
        parent::setUp();

        $this->setUpExpressCheckout();
        $this->recordWebhookHooks();
        // Bizum sits behind a feature flag; the e2e suite switches it on with a plugin of its own.
        $this->addTestFilter('inpsyde.feature-flags.mollie-woocommerce.bizum_enabled', '__return_true');
        // Every method of the table is active at Mollie, so its gateway registers.
        $this->fakeMollie()->setMethods(array_keys(self::E2E_CASES));
    }

    public function tearDown(): void
    {
        unset($_GET['mollie_webhook_secret'], $_GET['order_id'], $_GET['key']);
        $this->container = null;
        $this->tearDownExpressCheckout();
        Mockery::close();

        parent::tearDown();
    }

    /**
     * Scenario: the webhook moves the order to the status the payment calls for
     *   Given an order placed at the checkout with a Mollie method, waiting for its payment
     *   And the payment reached a status at Mollie
     *   When Mollie calls the REST webhook
     *   Then the order has the status that payment status stands for
     *   And a paid payment is recorded once, with the note that names the payment
     *
     * @test
     * @dataProvider paymentsApiCases
     * @covers \Mollie\WooCommerce\Payment\MollieOrderService::doPaymentForOrder
     */
    public function it_moves_the_order_to_the_status_the_payment_calls_for(
        string $method,
        string $paymentStatus,
        string $expectedStatus
    ): void {
        [$order, $payment] = $this->checkoutOrderWithPayment($method, $paymentStatus);

        $this->assertSame(200, $this->deliverWebhook($payment['id']));

        $this->assertOutcome($order, $payment, $paymentStatus, $expectedStatus);
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public function paymentsApiCases(): array
    {
        $cases = [];
        foreach (self::E2E_CASES as $method => $statuses) {
            foreach ($statuses as $paymentStatus => $e2eCase) {
                $cases["{$e2eCase} {$method} {$paymentStatus}"] = [
                    $method,
                    $paymentStatus,
                    self::expectedOrderStatus($method, $paymentStatus),
                ];
            }
        }

        return $cases;
    }

    /**
     * Scenario: the older WC-API webhook URL leads to the same order status
     *   Given an order whose payment was created with the WC-API webhook URL
     *   When Mollie calls that URL
     *   Then the order has the same status the REST webhook gives it
     *
     * @test
     * @dataProvider wcApiCases
     * @covers \Mollie\WooCommerce\Payment\MollieOrderService::onWebhookAction
     */
    public function it_moves_the_order_to_the_same_status_on_the_wc_api_webhook(
        string $method,
        string $paymentStatus,
        string $expectedStatus
    ): void {
        [$order, $payment] = $this->checkoutOrderWithPayment($method, $paymentStatus);
        $_GET['mollie_webhook_secret'] = CanaryData::WEBHOOK_SECRET;
        $_GET['order_id'] = (string) $order->get_id();
        $_GET['key'] = $order->get_order_key();

        $this->legacyWebhookService($payment['id'], Mockery::spy(HttpResponse::class))->onWebhookAction();

        $this->assertOutcome($order, $payment, $paymentStatus, $expectedStatus);
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public function wcApiCases(): array
    {
        $cases = [];
        foreach (['ideal', 'paypal', 'klarna', 'banktransfer'] as $method) {
            foreach (array_keys(self::E2E_CASES[$method]) as $paymentStatus) {
                $cases["{$method} {$paymentStatus}"] = [$method, $paymentStatus, self::expectedOrderStatus($method, $paymentStatus)];
            }
        }

        return $cases;
    }

    private static function expectedOrderStatus(string $method, string $paymentStatus): string
    {
        // A bank transfer waits on-hold for the money (e2e case C420283).
        if ($method === 'banktransfer' && $paymentStatus === 'open') {
            return 'on-hold';
        }
        // Its expiry cancels the unpaid on-hold order, as for every method since "Consider on-hold
        // status" (WebhooksIntegrationTest pins the same). The e2e case C420285 still names on-hold.

        return self::ORDER_STATUS[$paymentStatus];
    }

    /**
     * The order as the checkout leaves it: linked to its payment, pending, or on-hold for a bank
     * transfer.
     *
     * @return array{0: WC_Order, 1: array<string, mixed>}
     */
    private function checkoutOrderWithPayment(string $method, string $paymentStatus): array
    {
        $this->boot();
        $order = $this->pendingOrder('mollie_wc_gateway_' . $method);
        $order->set_currency(self::CURRENCIES[$method] ?? 'EUR');
        $payment = $this->fakeMollie()->seedPayment([
            'amount' => ['currency' => $order->get_currency(), 'value' => $this->formattedTotal($order)],
            'method' => $method,
            'status' => $paymentStatus,
            'description' => 'Order ' . $order->get_id(),
            'metadata' => ['order_id' => $order->get_id()],
        ]);
        $order->update_meta_data('_mollie_payment_id', $payment['id']);
        $order->set_transaction_id($payment['id']);
        if ($method === 'banktransfer') {
            $order->set_status('on-hold');
        }
        $order->save();
        $this->logger()->reset();
        $this->hooksFired = [];

        return [$this->fresh($order), $payment];
    }

    /**
     * @param array<string, mixed> $payment
     */
    private function assertOutcome(WC_Order $order, array $payment, string $paymentStatus, string $expectedStatus): void
    {
        $after = $this->fresh($order);
        $this->assertSame($expectedStatus, $after->get_status());
        $this->assertSame($payment['id'], $after->get_transaction_id(), 'The order stays linked to its payment.');
        $this->assertGreaterThan(0, $this->paymentFetches($payment['id']), 'The webhook asked Mollie about the payment.');

        // What a payment that took, or reserved, the money leaves on the order; nothing otherwise.
        $recorded = [
            'paid' => ['note' => 'Order completed using Mollie', 'meta' => ['_mollie_paid_and_processed']],
            'authorized' => ['note' => 'Order authorized using Mollie', 'meta' => ['_mollie_authorized', '_mollie_paid_and_processed']],
        ][$paymentStatus] ?? ['note' => null, 'meta' => []];

        $this->assertSame($recorded['note'] === null ? 0 : 1, $this->fired('woocommerce_payment_complete'));
        foreach (['Order completed using Mollie', 'Order authorized using Mollie'] as $note) {
            $written = $this->notesContaining($after, $note);
            $this->assertCount($note === $recorded['note'] ? 1 : 0, $written, $note);
            if ($written !== []) {
                $this->assertStringContainsString($payment['id'], $written[0]);
            }
        }
        foreach (['_mollie_paid_and_processed', '_mollie_authorized'] as $metaKey) {
            $this->assertSame(in_array($metaKey, $recorded['meta'], true) ? '1' : '', (string) $after->get_meta($metaKey), $metaKey);
        }
    }
}

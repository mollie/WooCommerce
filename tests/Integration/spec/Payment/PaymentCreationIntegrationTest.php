<?php

namespace Mollie\WooCommerceTests\Integration\spec\Payment;

use Mollie\WooCommerceTests\Integration\Common\PaymentFlowTestCase;
use WC_Order;
use WC_Product_Simple;

/**
 * The payment-creation half of the critical path: what PaymentProcessor::processPayment() sends to
 * Mollie and which of the two Mollie APIs it sends it to.
 *
 * The webhook half is covered by WebhooksIntegrationTest; this class deliberately stops at the
 * redirect to Mollie's checkout. The duplicate-payment and retry cases live in
 * PaymentRetryIntegrationTest.
 *
 * The API choice is the thing worth testing at this level. The plugin talks to either the Orders API
 * (orders->create, an 'ord_' resource carrying line items) or the Payments API (payments->create, a
 * 'tr_' resource), and the choice is made by four independent rules scattered across the processor —
 * a global setting, the gateway, the cart contents and the capture mode — with a runtime fallback
 * from the first API to the second on top. A unit test can assert which private method ran; only an
 * integration test can assert which endpoint was actually called with which payload, and that is the
 * behaviour a merchant experiences.
 */
class PaymentCreationIntegrationTest extends PaymentFlowTestCase
{
    // ──────────────────────────────────────────────────────────────────────────
    // Scenarios
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Scenario: The Orders API happy path stores the Mollie references and redirects to checkout
     *   Given a pending iDEAL order with one physical product
     *   And the plugin is configured to use the Orders API
     *   When the customer starts the payment
     *   Then a Mollie order is created carrying the order total and its line items
     *   And the order records the 'ord_' id as both _mollie_order_id and its transaction id
     *   And the customer is sent to the Mollie checkout URL of that order
     *
     * @test
     * @group integration
     * @group PaymentCreation
     * @covers \Mollie\WooCommerce\Payment\PaymentProcessor::processPayment
     * @covers \Mollie\WooCommerce\Payment\PaymentProcessor::processAsMollieOrder
     */
    public function it_creates_a_mollie_order_and_redirects_to_the_mollie_checkout()
    {
        $this->useOrdersApi();
        $order = $this->pendingOrder('mollie_wc_gateway_ideal');

        $this->recordOrdersCreate(function (): object {
            return $this->createdMollieOrder('ord_happypath', 'ideal');
        });
        $this->recordPaymentsCreate(function (): object {
            return $this->createdMolliePayment('tr_unexpected', 'ideal');
        });

        $result = $this->processPayment($order, 'mollie_wc_gateway_ideal');

        $this->assertSame('success', $result['result'], 'A Mollie order that was created must yield a successful checkout.');
        $this->assertSame(
            'https://www.mollie.com/checkout/ord_happypath',
            $result['redirect'],
            'The customer must be redirected to the checkout URL of the Mollie order just created.'
        );

        $this->assertCount(1, $this->ordersCreateCalls, 'Exactly one Mollie order must be created.');
        $this->assertCount(0, $this->paymentsCreateCalls, 'The Payments API must not be touched when the Orders API succeeds.');

        $request = $this->ordersCreateCalls[0];
        $this->assertSame(
            $this->formattedTotal($order),
            $request['amount']['value'],
            'The Mollie order must be created for the WooCommerce order total.'
        );
        $this->assertNotEmpty($request['lines'], 'An Orders API request must carry the order line items.');
        $this->assertSame('ideal', $request['method'], 'The Mollie order must name the gateway the customer chose.');

        $order = wc_get_order($order->get_id());
        $this->assertSame('ord_happypath', $order->get_meta('_mollie_order_id'), 'The Mollie order id must be stored on the order.');
        $this->assertSame('ord_happypath', $order->get_transaction_id(), 'The Mollie order id must become the order transaction id.');
        $this->assertOrderHasNoteContaining($order, 'ideal payment started (ord_happypath');
    }

    /**
     * Scenario: A rejected Mollie order falls back to the Payments API
     *   Given a pending iDEAL order and the plugin configured to use the Orders API
     *   When orders->create fails with a validation error Mollie does not classify
     *     (not an outage, not fraud, not an invalid phone number, no customerId field)
     *   Then the plugin creates a Mollie payment instead and the checkout still succeeds
     *   And the order records the 'tr_' id, never an _mollie_order_id
     *
     * @test
     * @group integration
     * @group PaymentCreation
     * @covers \Mollie\WooCommerce\Payment\PaymentProcessor::processAsMollieOrder
     * @covers \Mollie\WooCommerce\Payment\PaymentProcessor::processPaymentForMollie
     */
    public function it_falls_back_to_the_payments_api_when_the_orders_api_rejects_the_order()
    {
        $this->useOrdersApi();
        $order = $this->pendingOrder('mollie_wc_gateway_ideal');

        $this->recordOrdersCreate(function (): object {
            throw $this->unclassifiedApiException();
        });
        $this->recordPaymentsCreate(function (): object {
            return $this->createdMolliePayment('tr_fallback', 'ideal');
        });

        $result = $this->processPayment($order, 'mollie_wc_gateway_ideal');

        $this->assertSame('success', $result['result'], 'A failed Mollie order must not fail the checkout while the Payments API is still an option.');
        $this->assertSame(
            'https://www.mollie.com/checkout/tr_fallback',
            $result['redirect'],
            'The customer must be redirected to the checkout URL of the fallback Mollie payment.'
        );

        $this->assertCount(1, $this->ordersCreateCalls, 'The Orders API must be tried exactly once.');
        $this->assertCount(1, $this->paymentsCreateCalls, 'The rejected Mollie order must be retried as a Mollie payment.');

        $order = wc_get_order($order->get_id());
        $this->assertSame('tr_fallback', $order->get_meta('_mollie_payment_id'), 'The fallback payment id must be stored on the order.');
        $this->assertSame('tr_fallback', $order->get_transaction_id(), 'The fallback payment id must become the order transaction id.');

        // MollieObject::setActiveMolliePaymentForOrders() copies the created resource id into
        // _mollie_order_id whatever its type, so that key is not empty here — it holds the 'tr_' id.
        // What must not happen is an 'ord_' reference being left behind for a Mollie order that was
        // never created: the webhook entry point picks which meta key to search on that prefix, so a
        // stale 'ord_' would send every later lookup down the Orders API path.
        $this->assertStringStartsNotWith(
            'ord_',
            (string) $order->get_meta('_mollie_order_id'),
            'No Mollie order exists, so no Mollie order reference may be stored.'
        );
    }

    /**
     * Scenario: An order rejected on payment.customerId is retried without the customer
     *   Given a pending iDEAL order for a customer carrying a Mollie customer id
     *   And the plugin configured to use the Orders API
     *   When orders->create fails naming the field 'payment.customerId'
     *   Then the plugin retries orders->create with that customer id removed
     *   And the retry succeeds without ever falling back to the Payments API
     *
     * @test
     * @group integration
     * @group PaymentCreation
     * @covers \Mollie\WooCommerce\Payment\PaymentProcessor::processAsMollieOrder
     */
    public function it_retries_the_mollie_order_without_the_customer_id_when_mollie_rejects_it()
    {
        $this->useOrdersApi();
        $order = $this->pendingOrder('mollie_wc_gateway_ideal');

        $this->recordOrdersCreate(function (array $data, int $attempt): object {
            if ($attempt === 1) {
                throw $this->unclassifiedApiException('payment.customerId');
            }
            return $this->createdMollieOrder('ord_retried', 'ideal');
        });
        $this->recordPaymentsCreate(function (): object {
            return $this->createdMolliePayment('tr_unexpected', 'ideal');
        });

        $result = $this->processPayment($order, 'mollie_wc_gateway_ideal');

        $this->assertSame('success', $result['result'], 'The retry without a customer id must produce a usable checkout.');
        $this->assertCount(2, $this->ordersCreateCalls, 'The Mollie order must be attempted twice: once with, once without the customer id.');
        $this->assertCount(0, $this->paymentsCreateCalls, 'A successful retry must not also fall back to the Payments API.');

        $this->assertSame(
            self::CUSTOMER_ID,
            $this->ordersCreateCalls[0]['payment']['customerId'] ?? null,
            'The first attempt is the one Mollie rejected, so it must have carried the customer id.'
        );
        $this->assertArrayNotHasKey(
            'customerId',
            $this->ordersCreateCalls[1]['payment'] ?? [],
            'The retry must drop the customer id Mollie rejected, otherwise it repeats the same failing request.'
        );

        $order = wc_get_order($order->get_id());
        $this->assertSame('ord_retried', $order->get_meta('_mollie_order_id'), 'The retried Mollie order id must be stored on the order.');
    }

    /**
     * Scenario: Klarna never falls back to the Payments API
     *   Given a pending Klarna Pay Later order and the plugin configured to use the Orders API
     *   When orders->create fails
     *   Then the plugin stops instead of retrying as a Mollie payment — Klarna is an Orders-API-only
     *     method, so a fallback payment would be created for a method that cannot settle it
     *   And the checkout fails leaving no Mollie reference on the order
     *
     * @test
     * @group integration
     * @group PaymentCreation
     * @covers \Mollie\WooCommerce\Payment\PaymentProcessor::processAsMollieOrder
     */
    public function it_does_not_fall_back_to_the_payments_api_for_klarna()
    {
        $this->useOrdersApi();
        $this->registerAdditionalGateways(['klarnapaylater']);
        $order = $this->pendingOrder('mollie_wc_gateway_klarnapaylater');

        $this->recordOrdersCreate(function (): object {
            throw $this->unclassifiedApiException();
        });
        $this->recordPaymentsCreate(function (): object {
            return $this->createdMolliePayment('tr_unexpected', 'klarna');
        });

        $result = $this->processPayment($order, 'mollie_wc_gateway_klarnapaylater');

        $this->assertSame('failure', $result['result'], 'A Klarna order Mollie rejected must fail the checkout, not silently become a payment.');
        $this->assertCount(1, $this->ordersCreateCalls, 'The Orders API must be tried exactly once.');
        $this->assertCount(0, $this->paymentsCreateCalls, 'Klarna must never fall back to the Payments API.');

        $order = wc_get_order($order->get_id());
        $this->assertEmpty($order->get_meta('_mollie_order_id'), 'A failed Klarna order must leave no Mollie order id behind.');
        $this->assertEmpty($order->get_meta('_mollie_payment_id'), 'A failed Klarna order must leave no Mollie payment id behind.');
        $this->assertEmpty($order->get_transaction_id(), 'A failed Klarna order must leave no transaction id behind.');
    }

    /**
     * Scenario: A line item whose product no longer exists forces the Payments API
     *   Given a pending iDEAL order containing a product that has since been deleted
     *     (the shape WooCommerce Events Manager and friends produce with virtual items)
     *   And the plugin configured to use the Orders API
     *   When the customer starts the payment
     *   Then the plugin skips the Orders API entirely — its line items cannot be built for a product
     *     that is gone — and creates a Mollie payment instead
     *
     * @test
     * @group integration
     * @group PaymentCreation
     * @covers \Mollie\WooCommerce\Payment\PaymentProcessor::paymentTypeBasedOnProducts
     */
    public function it_creates_a_payment_when_a_line_item_product_no_longer_exists()
    {
        $this->useOrdersApi();
        $order = $this->pendingOrder('mollie_wc_gateway_ideal');
        $this->addDeletedProductLineTo($order);

        $this->recordOrdersCreate(function (): object {
            return $this->createdMollieOrder('ord_unexpected', 'ideal');
        });
        $this->recordPaymentsCreate(function (): object {
            return $this->createdMolliePayment('tr_noproduct', 'ideal');
        });

        $result = $this->processPayment($order, 'mollie_wc_gateway_ideal');

        $this->assertSame('success', $result['result'], 'A deleted product must not fail the checkout.');
        $this->assertCount(0, $this->ordersCreateCalls, 'The Orders API must not be called for an order whose product is gone.');
        $this->assertCount(1, $this->paymentsCreateCalls, 'The order must be created through the Payments API instead.');

        $order = wc_get_order($order->get_id());
        $this->assertSame('tr_noproduct', $order->get_meta('_mollie_payment_id'), 'The Mollie payment id must be stored on the order.');
    }

    /**
     * Scenario: Bank transfer with an expiry date is created as a payment and put on hold
     *   Given the Bank Transfer gateway has its expiry-date setting enabled and an on-hold initial status
     *   And the plugin configured to use the Orders API
     *   When a customer pays a pending order with Bank Transfer
     *   Then the plugin uses the Payments API regardless — only a Mollie payment carries a dueDate —
     *     and sends that dueDate with the request
     *   And the order goes on hold awaiting confirmation instead of staying pending
     *
     * @test
     * @group integration
     * @group PaymentCreation
     * @covers \Mollie\WooCommerce\Payment\PaymentProcessor::paymentTypeBasedOnGateway
     * @covers \Mollie\WooCommerce\Payment\PaymentProcessor::updatePaymentStatusForDelayedMethods
     */
    public function it_creates_a_payment_and_holds_the_order_for_bank_transfer_with_an_expiry_date()
    {
        $this->useOrdersApi();
        $this->setGatewaySettingsForTest('banktransfer', [
            'activate_expiry_days_setting' => 'yes',
            'initial_order_status' => 'on-hold',
            'order_dueDate' => 12,
            'skip_mollie_payment_screen' => 'no',
        ]);
        $order = $this->pendingOrder('mollie_wc_gateway_banktransfer');

        $this->recordOrdersCreate(function (): object {
            return $this->createdMollieOrder('ord_unexpected', 'banktransfer');
        });
        $this->recordPaymentsCreate(function (): object {
            return $this->createdMolliePayment('tr_banktransfer', 'banktransfer');
        });

        $result = $this->processPayment($order, 'mollie_wc_gateway_banktransfer');

        $this->assertSame('success', $result['result'], 'A bank transfer payment must produce a usable checkout.');
        $this->assertCount(0, $this->ordersCreateCalls, 'Bank transfer with an expiry date must not use the Orders API.');
        $this->assertCount(1, $this->paymentsCreateCalls, 'Bank transfer with an expiry date must use the Payments API.');
        $this->assertNotEmpty(
            $this->paymentsCreateCalls[0]['dueDate'] ?? null,
            'The expiry-date setting is the reason for the Payments API, so the request must carry a dueDate.'
        );

        $order = wc_get_order($order->get_id());
        $this->assertSame(
            'on-hold',
            $order->get_status(),
            'A confirmation-delayed method must move the order to its initial status, not leave it pending.'
        );
        $this->assertOrderHasNoteContaining($order, 'Awaiting payment confirmation.');
    }

    /**
     * Scenario: Credit card with manual capture is created as a payment, not an order
     *   Given the merchant set captures to "later capture"
     *   And the plugin configured to use the Orders API
     *   When a customer pays a pending order with Credit Card
     *   Then the plugin uses the Payments API — a Mollie order captures on its own, so an order here
     *     would take the money the merchant asked to hold
     *
     * @test
     * @group integration
     * @group PaymentCreation
     * @covers \Mollie\WooCommerce\Payment\PaymentProcessor::processPaymentForMollie
     */
    public function it_creates_a_payment_for_credit_card_when_capture_is_manual()
    {
        $this->useOrdersApi();
        $this->setOptionForTest('mollie-payments-for-woocommerce_place_payment_onhold', 'later_capture');
        $order = $this->pendingOrder('mollie_wc_gateway_creditcard');

        $this->recordOrdersCreate(function (): object {
            return $this->createdMollieOrder('ord_unexpected', 'creditcard');
        });
        $this->recordPaymentsCreate(function (): object {
            return $this->createdMolliePayment('tr_manualcapture', 'creditcard');
        });

        $result = $this->processPayment($order, 'mollie_wc_gateway_creditcard');

        $this->assertSame('success', $result['result'], 'A manually captured credit card payment must produce a usable checkout.');
        $this->assertCount(0, $this->ordersCreateCalls, 'Manual capture must not create a Mollie order, which would capture immediately.');
        $this->assertCount(1, $this->paymentsCreateCalls, 'Manual capture must create a Mollie payment.');

        $order = wc_get_order($order->get_id());
        $this->assertSame('tr_manualcapture', $order->get_meta('_mollie_payment_id'), 'The Mollie payment id must be stored on the order.');
    }

    /**
     * Adds a line item for a product that exists at the time the item is created and is deleted
     * immediately after — the only way to reach the "product is gone" branch, since WooCommerce
     * refuses to store a line item pointing at an id that never existed.
     */
    private function addDeletedProductLineTo(WC_Order $order): void
    {
        $product = new WC_Product_Simple();
        $product->set_name('Deleted before payment');
        $product->set_regular_price('5.00');
        $product->set_status('publish');
        $productId = $product->save();

        $order->add_product($product, 1);
        $order->calculate_totals();
        $order->save();

        wp_delete_post($productId, true);
        wp_cache_flush();
    }
}

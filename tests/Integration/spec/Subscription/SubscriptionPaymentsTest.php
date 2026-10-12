<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Integration\spec\Subscription;

use Mockery;
use Mollie\WooCommerceTests\Integration\Common\ExpressFlowTestCase;
use Mollie\WooCommerceTests\Integration\Common\Traits\ExpressCheckoutFixtures;
use Mollie\WooCommerceTests\Integration\Common\Traits\WebhookOrderFixtures;
use WC_Order;
use WC_Subscription;

/**
 * A subscription paid with Mollie: the first payment at the checkout, which leaves a mandate, and
 * the renewals WooCommerce Subscriptions asks for later, charged on that mandate without the shopper.
 *
 * The e2e suite covers both in tests/qa/tests/07-subscription; tests/Integration/e2e-equivalence.md
 * has the list. Needs WooCommerce Subscriptions on the test site.
 *
 * @group integration
 * @group Subscription
 */
class SubscriptionPaymentsTest extends ExpressFlowTestCase
{
    use ExpressCheckoutFixtures;
    use WebhookOrderFixtures;

    private const METHODS = ['ideal', 'bancontact', 'eps', 'kbc', 'belfius', 'creditcard', 'directdebit'];

    public function setUp(): void
    {
        parent::setUp();
        if (!class_exists(\WC_Subscriptions::class)) {
            $this->markTestSkipped('WooCommerce Subscriptions is not active.');
        }

        $this->setUpExpressCheckout();
        $this->recordWebhookHooks();
        $this->fakeMollie()->setMethods(self::METHODS);
    }

    public function tearDown(): void
    {
        $this->container = null;
        $this->tearDownExpressCheckout();
        Mockery::close();

        parent::tearDown();
    }

    /**
     * Scenario: the first payment of a subscription activates it and keeps the mandate
     *   Given a subscription bought at the checkout, its order waiting for the first payment
     *   When the shopper pays and Mollie calls the webhook
     *   Then the order is being processed and the subscription is active
     *   And the mandate Mollie made from the payment is kept on the order and on the subscription
     *
     * @test
     * @dataProvider firstPaymentMethods
     * @covers \Mollie\WooCommerce\Payment\Webhooks\WebhookHandler::onWebhookPaid
     */
    public function it_activates_the_subscription_and_keeps_the_mandate_of_the_first_payment(string $method, string $mandateMethod): void
    {
        [$subscription, $parent] = $this->subscriptionWaitingForItsFirstPayment($method);
        $customer = $this->fakeMollie()->seedCustomer([$mandateMethod => 'valid']);
        $payment = $this->fakeMollie()->seedPayment([
            'amount' => ['currency' => 'EUR', 'value' => $this->formattedTotal($parent)],
            'method' => $method,
            'status' => 'paid',
            'sequenceType' => 'first',
            'customerId' => $customer['id'],
            'mandateId' => $customer['mandates'][$mandateMethod],
            'metadata' => ['order_id' => $parent->get_id()],
        ]);
        $parent->update_meta_data('_mollie_payment_id', $payment['id']);
        $parent->set_transaction_id($payment['id']);
        $parent->save();

        $this->assertSame(200, $this->deliverWebhook($payment['id']));

        $paid = $this->fresh($parent);
        $this->assertSame('processing', $paid->get_status());
        $this->assertSame($customer['mandates'][$mandateMethod], $paid->get_meta('_mollie_mandate_id'));
        $active = wcs_get_subscription($subscription->get_id());
        $this->assertSame('active', $active->get_status());
        $this->assertSame($customer['mandates'][$mandateMethod], $active->get_meta('_mollie_mandate_id'));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public function firstPaymentMethods(): array
    {
        return [
            'ideal, which leaves a direct debit mandate' => ['ideal', 'directdebit'],
            'creditcard' => ['creditcard', 'creditcard'],
        ];
    }

    /**
     * Scenario: a renewal is charged on the mandate, without the shopper
     *   Given an active subscription whose first payment left a valid mandate
     *   When WooCommerce Subscriptions asks the gateway to pay the renewal order
     *   Then Mollie is asked once for a recurring payment of the renewal total, for that customer and mandate
     *   And the renewal order is linked to that payment and waits for it
     *   When Mollie confirms the payment through the webhook
     *   Then the renewal order is being processed and the subscription is active
     *
     * @test
     * @dataProvider renewalMethods
     * @covers \Mollie\WooCommerce\Subscription\MollieSubscriptionGatewayHandler::scheduled_subscription_payment
     */
    public function it_charges_a_renewal_on_the_mandate_and_completes_it_when_mollie_confirms(
        string $method,
        string $mandateMethod,
        string $renewalGateway,
        string $waitingStatus
    ): void {
        [$subscription, $renewal, $customer] = $this->activeSubscriptionDueForRenewal($method, [$mandateMethod => 'valid']);

        do_action('woocommerce_scheduled_subscription_payment_mollie_wc_gateway_' . $method, (float) $renewal->get_total(), $renewal);

        $created = $this->fakeMollie()->requests('POST', 'payments');
        $this->assertCount(1, $created);
        $sent = $created[0]['body'];
        $this->assertSame('recurring', $sent['sequenceType']);
        $this->assertSame($customer['id'], $sent['customerId']);
        $this->assertSame($customer['mandates'][$mandateMethod], $sent['mandateId']);
        $this->assertSame($mandateMethod, $sent['method']);
        $this->assertSame(['currency' => 'EUR', 'value' => $this->formattedTotal($renewal)], $sent['amount']);
        $this->assertSame($renewal->get_id(), (int) $sent['metadata']['order_id']);

        $payments = $this->fakeMollie()->payments();
        $paymentId = (string) array_key_last($payments);
        $waiting = $this->fresh($renewal);
        $this->assertSame($paymentId, $waiting->get_meta('_mollie_payment_id'));
        $this->assertSame($waitingStatus, $waiting->get_status());
        $this->assertSame($renewalGateway, $waiting->get_payment_method());
        $this->assertCount(1, $this->notesContaining($waiting, "payment started ({$paymentId})"));

        $this->fakeMollie()->setPaymentStatus($paymentId, 'paid');
        $this->assertSame(200, $this->deliverWebhook($paymentId));

        $this->assertSame('processing', $this->fresh($renewal)->get_status());
        $this->assertSame('active', wcs_get_subscription($subscription->get_id())->get_status());
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string, 3: string}>
     */
    public function renewalMethods(): array
    {
        return [
            'C4132915 ideal' => ['ideal', 'directdebit', 'mollie_wc_gateway_directdebit', 'on-hold'],
            'C4211824 bancontact' => ['bancontact', 'directdebit', 'mollie_wc_gateway_directdebit', 'on-hold'],
            'C4211825 eps' => ['eps', 'directdebit', 'mollie_wc_gateway_directdebit', 'on-hold'],
            'C4211826 kbc' => ['kbc', 'directdebit', 'mollie_wc_gateway_directdebit', 'on-hold'],
            'C4211827 belfius' => ['belfius', 'directdebit', 'mollie_wc_gateway_directdebit', 'on-hold'],
            'C4132916 creditcard' => ['creditcard', 'creditcard', 'mollie_wc_gateway_creditcard', 'pending'],
        ];
    }

    /**
     * Scenario: a renewal without a usable mandate fails and charges nothing
     *   Given an active subscription whose customer has no valid mandate at Mollie
     *   When WooCommerce Subscriptions asks the gateway to pay the renewal order
     *   Then no payment is created and the renewal order has failed, with a note saying why
     *
     * @test
     * @covers \Mollie\WooCommerce\Subscription\MollieSubscriptionGatewayHandler::scheduled_subscription_payment
     */
    public function it_fails_a_renewal_whose_customer_has_no_valid_mandate(): void
    {
        [, $renewal] = $this->activeSubscriptionDueForRenewal('creditcard', ['creditcard' => 'invalid']);

        do_action('woocommerce_scheduled_subscription_payment_mollie_wc_gateway_creditcard', (float) $renewal->get_total(), $renewal);

        $this->assertSame([], $this->fakeMollie()->requests('POST', 'payments'));
        $failed = $this->fresh($renewal);
        $this->assertSame('failed', $failed->get_status());
        $this->assertCount(1, $this->notesContaining($failed, 'does not have a valid mandate'));
    }

    /**
     * Scenario: a renewal order whose payment is under way is not charged a second time
     *   Given a renewal order already linked to a payment Mollie is still collecting
     *   When WooCommerce Subscriptions asks the gateway to pay that renewal order again
     *   Then no second payment is created
     *
     * @test
     * @covers \Mollie\WooCommerce\Subscription\MollieSubscriptionGatewayHandler::scheduled_subscription_payment
     */
    public function it_does_not_charge_a_renewal_twice_while_its_payment_is_under_way(): void
    {
        [, $renewal] = $this->activeSubscriptionDueForRenewal('ideal', ['directdebit' => 'valid']);
        $hook = 'woocommerce_scheduled_subscription_payment_mollie_wc_gateway_ideal';
        do_action($hook, (float) $renewal->get_total(), $renewal);
        $this->assertCount(1, $this->fakeMollie()->requests('POST', 'payments'), 'The first request must have charged.');

        do_action($hook, (float) $renewal->get_total(), $this->fresh($renewal));

        $this->assertCount(1, $this->fakeMollie()->requests('POST', 'payments'));
    }

    /**
     * The plugin attaches its subscription listeners to the gateways once WooCommerce registered
     * its post types, which on a real request follows the boot; here it is fired for this boot only.
     */
    private function bootWithSubscriptionHooks(): void
    {
        if ($this->container !== null) {
            return;
        }
        $this->removePluginListenersOfEarlierBoots();
        $this->container = $this->bootExpressOwning(['woocommerce_after_register_post_type']);
        do_action('woocommerce_after_register_post_type');
    }

    /**
     * @return array{0: WC_Subscription, 1: WC_Order}
     */
    private function subscriptionWaitingForItsFirstPayment(string $method): array
    {
        $this->bootWithSubscriptionHooks();
        $gatewayId = 'mollie_wc_gateway_' . $method;
        $parent = $this->getConfiguredOrder($this->customer_id, $gatewayId, ['subscription'], [], false);
        $parent->set_transaction_id('');
        $parent->set_status('pending');
        $parent->save();

        $subscription = wcs_create_subscription([
            'order_id' => $parent->get_id(),
            'customer_id' => $this->customer_id,
            'billing_period' => 'day',
            'billing_interval' => 1,
        ]);
        $this->assertInstanceOf(WC_Subscription::class, $subscription);
        foreach ($parent->get_items() as $item) {
            $subscription->add_product($item->get_product(), $item->get_quantity());
        }
        $subscription->set_payment_method($gatewayId);
        $subscription->calculate_totals();
        $subscription->save();

        return [$subscription, wc_get_order($parent->get_id())];
    }

    /**
     * A subscription whose first payment is behind it, with the renewal order WooCommerce
     * Subscriptions makes when the next payment is due.
     *
     * @param array<string, string> $mandates Method => status, as the customer has them at Mollie.
     * @return array{0: WC_Subscription, 1: WC_Order, 2: array{id: string, mandates: array<string, string>}}
     */
    private function activeSubscriptionDueForRenewal(string $method, array $mandates): array
    {
        [$subscription, $parent] = $this->subscriptionWaitingForItsFirstPayment($method);
        $customer = $this->fakeMollie()->seedCustomer($mandates);
        $mandateId = array_values($customer['mandates'])[0];
        $first = $this->fakeMollie()->seedPayment([
            'amount' => ['currency' => 'EUR', 'value' => $this->formattedTotal($parent)],
            'method' => $method,
            'status' => 'paid',
            'sequenceType' => 'first',
            'customerId' => $customer['id'],
            'mandateId' => $mandateId,
            'metadata' => ['order_id' => $parent->get_id()],
        ]);
        foreach ([$parent, $subscription] as $object) {
            $object->update_meta_data('_mollie_payment_id', $first['id']);
            $object->update_meta_data('_mollie_customer_id', $customer['id']);
            $object->update_meta_data('_mollie_mandate_id', $mandateId);
        }
        $parent->set_transaction_id($first['id']);
        $parent->update_meta_data('_mollie_paid_and_processed', '1');
        $parent->set_status('processing');
        $parent->save();
        $subscription->set_status('active');
        $subscription->save();

        $renewal = wcs_create_renewal_order(wcs_get_subscription($subscription->get_id()));
        $this->assertInstanceOf(WC_Order::class, $renewal);
        $renewal->set_payment_method('mollie_wc_gateway_' . $method);
        $renewal->save();
        $this->logger()->reset();

        return [wcs_get_subscription($subscription->get_id()), wc_get_order($renewal->get_id()), $customer];
    }
}

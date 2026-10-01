<?php
// kb-active

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Integration\spec\ExpressComponent;

use Mollie\WooCommerce\Payment\PaymentModule;
use Mollie\WooCommerceTests\Integration\Common\ExpressFlowTestCase;
use Mollie\WooCommerceTests\Integration\Common\Traits\ExpressCheckoutFixtures;

/**
 * Whether the action mollie_woocommerce_cancel_unpaid_orders is scheduled (REQ-E1; AC-27).
 *
 * PaymentModule::handleExpiryDateCancelation() runs on init and schedules the action only while an
 * enabled gateway has activate_expiry_days_setting on; otherwise it unschedules it. The first test
 * pins that behaviour as it is today, before its condition is extracted into a pure predicate. The
 * express cleanup runs on the same action, so it must also stay scheduled while Express is enabled,
 * with no second action name.
 *
 * Driven through the real wiring: the plugin's own init callbacks run, and nothing else's. Express
 * is enabled when a wallet is visible (here PayPal with its checkout button on, in a live HTTPS
 * shop), and off when no wallet's express setting is on.
 *
 * @group integration
 * @group ExpressComponent
 * @group ExpressOrderLifecycle
 */
class CancelUnpaidScheduleTest extends ExpressFlowTestCase
{
    use ExpressCheckoutFixtures;

    private const ACTION = 'mollie_woocommerce_cancel_unpaid_orders';

    /**
     * Whether the site had the action scheduled before the test; put back in tearDown().
     */
    private bool $wasScheduled = false;

    public function setUp(): void
    {
        parent::setUp();

        $this->setUpExpressCheckout();
        $this->wasScheduled = $this->isScheduled();
        as_unschedule_all_actions(self::ACTION);
    }

    public function tearDown(): void
    {
        as_unschedule_all_actions(self::ACTION);
        if ($this->wasScheduled) {
            as_schedule_recurring_action(time(), 600, self::ACTION);
        }
        $this->tearDownExpressCheckout();

        parent::tearDown();
    }

    /**
     * Scenario: today's decision holds while Express is off
     *   Given Express is off and the gateways' expiry settings as in the row
     *   And the action was scheduled before, or was not
     *   When the plugin runs its init callbacks
     *   Then the action is scheduled exactly when an enabled gateway has the expiry setting on
     *
     * Characterisation test of PaymentModule::handleExpiryDateCancelation(): it must pass before and
     * after the refactor that extracts its condition.
     *
     * @test
     * @dataProvider todaysDecision
     * @param array<string, string> $idealSettings
     */
    public function it_keeps_todays_schedule_decision(array $idealSettings, bool $scheduledBefore, bool $expected): void
    {
        $this->expressOff();
        $this->bootAndSetGateways(['ideal' => $idealSettings]);
        if ($scheduledBefore) {
            as_schedule_recurring_action(time(), 600, self::ACTION);
        }

        $this->runPluginInit();

        $this->assertSame($expected, $this->isScheduled());
    }

    /**
     * @return array<string, array{0: array<string, string>, 1: bool, 2: bool}>
     */
    public function todaysDecision(): array
    {
        $expiryOn = ['enabled' => 'yes', 'activate_expiry_days_setting' => 'yes', 'order_dueDate' => '10'];
        $disabledWithExpiry = ['enabled' => 'no', 'activate_expiry_days_setting' => 'yes', 'order_dueDate' => '10'];

        return [
            'no expiry setting anywhere, was scheduled: unscheduled' => [[], true, false],
            'no expiry setting anywhere, was not scheduled: stays off' => [[], false, false],
            'an enabled gateway with expiry on: scheduled' => [$expiryOn, false, true],
            'an enabled gateway with expiry on, already scheduled: stays scheduled' => [$expiryOn, true, true],
            'only a disabled gateway has expiry on: unscheduled' => [$disabledWithExpiry, true, false],
        ];
    }

    /**
     * Scenario: the cleanup is scheduled while Express is enabled, even with no expiry setting on
     *   Given Express is enabled and no gateway has the expiry setting on
     *   When the plugin runs its init callbacks
     *   Then the action mollie_woocommerce_cancel_unpaid_orders is scheduled
     *
     * @test
     */
    public function it_schedules_the_cleanup_when_express_is_on_without_any_expiry_setting(): void
    {
        $this->bootAndSetGateways([]);

        $this->runPluginInit();

        $this->assertTrue($this->isScheduled());
    }

    /**
     * Scenario: the express cleanup rides on the existing action, and no other action exists
     *   Given Express is enabled and no gateway has the expiry setting on
     *   When the plugin runs its init callbacks
     *   Then a callback other than PaymentModule::cancelOrderOnExpiryDate listens on the action
     *   And no other pending Mollie action about cancelling, expiring or abandoning orders is scheduled
     *
     * @test
     */
    public function it_attaches_the_express_cleanup_to_the_existing_action_only(): void
    {
        $this->bootAndSetGateways([]);

        $this->runPluginInit();

        $this->assertNotSame([], $this->listenersOtherThanTheLegacyOne(), 'The express cleanup must listen on ' . self::ACTION . '.');
        $this->assertSame([self::ACTION], $this->pendingMollieCleanupHooks());
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────────

    private function expressOff(): void
    {
        $this->setGatewaySettingsForTest('paypal', ['mollie_paypal_button_enabled_checkout' => 'no']);
        $this->setGatewaySettingsForTest('applepay', ['mollie_apple_pay_button_enabled_express_checkout' => 'no']);
    }

    /**
     * Boots the plugin owning init and the action, with every gateway's expiry setting off, then
     * applies the given settings on top.
     *
     * @param array<string, array<string, string>> $settingsByMethod
     */
    private function bootAndSetGateways(array $settingsByMethod): void
    {
        $container = $this->bootExpressOwning(['init', self::ACTION]);
        foreach ($container->get('gateway.paymentMethods') as $paymentMethod) {
            $this->setGatewaySettingsForTest((string) $paymentMethod->getProperty('id'), ['activate_expiry_days_setting' => 'no']);
        }
        foreach ($settingsByMethod as $methodId => $settings) {
            $this->setGatewaySettingsForTest($methodId, $settings);
        }
    }

    private function runPluginInit(): void
    {
        do_action('init');
    }

    private function isScheduled(): bool
    {
        return as_next_scheduled_action(self::ACTION) !== false;
    }

    /**
     * @return array<int, mixed>
     */
    private function listenersOtherThanTheLegacyOne(): array
    {
        $listeners = [];
        $hook = $GLOBALS['wp_filter'][self::ACTION] ?? null;
        if (!$hook instanceof \WP_Hook) {
            return [];
        }
        foreach ($hook->callbacks as $callbacks) {
            foreach ($callbacks as $callback) {
                $function = $callback['function'];
                if (is_array($function) && $function[0] instanceof PaymentModule && $function[1] === 'cancelOrderOnExpiryDate') {
                    continue;
                }
                $listeners[] = $function;
            }
        }

        return $listeners;
    }

    /**
     * @return array<int, string>
     */
    private function pendingMollieCleanupHooks(): array
    {
        $actions = as_get_scheduled_actions(['status' => \ActionScheduler_Store::STATUS_PENDING, 'per_page' => -1]);
        $hooks = [];
        foreach ($actions as $action) {
            $hook = $action->get_hook();
            if (strpos($hook, 'mollie') !== false && preg_match('/cancel|expir|abandon|unpaid/', $hook)) {
                $hooks[$hook] = $hook;
            }
        }
        ksort($hooks);

        return array_values($hooks);
    }
}

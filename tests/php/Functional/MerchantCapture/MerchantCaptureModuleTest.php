<?php # -*- coding: utf-8 -*-

namespace Mollie\WooCommerceTests\Functional\MerchantCapture;

use Mollie\WooCommerce\MerchantCapture\MerchantCaptureModule;
use Mollie\WooCommerceTests\TestCase;
use Mockery;
use Psr\Container\ContainerInterface;
use WC_Order;

use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\when;

class MerchantCaptureModuleTest extends TestCase
{
    /** @var \stdClass */
    private $hooks;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hooks = (object) ['actions' => [], 'filters' => []];

        when('apply_filters')->returnArg(2);
    }

    /**
     * WHEN the 'Capture or cancel on status change' setting is enabled
     * THEN the mollie_wc_gateway_disable_ship_and_capture filter returns true for a Klarna order
     * @test
     */
    public function disableShipAndCaptureReturnsTrueForKlarnaWhenStatusChangeSettingEnabled()
    {
        // WooCommerce's checkbox save writes the literal string 'yes' when checked.
        $container = $this->createMockContainer('yes');
        $order = $this->makeOrder('mollie_wc_gateway_klarna');

        $callback = $this->runModuleAndGetDisableFilterCallback($container);

        self::assertTrue($callback(false, $order));
    }

    /**
     * WHEN the setting has been saved unchecked
     * THEN the filter still returns false for a Klarna order - unchanged from current behavior
     * @test
     */
    public function disableShipAndCaptureReturnsFalseForKlarnaWhenStatusChangeSettingSavedUnchecked()
    {
        // WC_Admin_Settings::save_fields() writes the literal string 'no' for an unchecked
        // checkbox (not an empty/false value) any time the settings page is saved - this is
        // the realistic value for the overwhelming majority of merchants, not just an edge case.
        $container = $this->createMockContainer('no');
        $order = $this->makeOrder('mollie_wc_gateway_klarna');

        $callback = $this->runModuleAndGetDisableFilterCallback($container);

        self::assertFalse($callback(false, $order));
    }

    /**
     * WHEN the setting has never been saved at all (option row does not exist)
     * THEN the filter still returns false for a Klarna order
     * @test
     */
    public function disableShipAndCaptureReturnsFalseForKlarnaWhenStatusChangeSettingNeverSaved()
    {
        // get_option(..., false) returns the raw boolean default when the option row
        // was never created - distinct from the 'no' string WooCommerce saves on submit.
        $container = $this->createMockContainer(false);
        $order = $this->makeOrder('mollie_wc_gateway_klarna');

        $callback = $this->runModuleAndGetDisableFilterCallback($container);

        self::assertFalse($callback(false, $order));
    }

    /**
     * WHEN a credit-card order is in the existing authorized on-hold state
     * THEN the filter still returns true regardless of the status-change setting
     * @test
     */
    public function disableShipAndCaptureStillTrueForCreditcardAuthorizedRegardlessOfSetting()
    {
        $container = $this->createMockContainer('no', ['is_authorized' => true]);
        $order = $this->makeOrder('mollie_wc_gateway_creditcard');

        $callback = $this->runModuleAndGetDisableFilterCallback($container);

        self::assertTrue($callback(false, $order));
    }

    /**
     * WHEN the setting is enabled for a non-Klarna, non-creditcard gateway
     * THEN the filter still returns true - the condition is not limited to a specific gateway
     * @test
     */
    public function disableShipAndCaptureReturnsTrueForNonCreditcardGatewayIndependentOfPaymentMethod()
    {
        $container = $this->createMockContainer('yes');
        $order = $this->makeOrder('mollie_wc_gateway_bancontact');

        $callback = $this->runModuleAndGetDisableFilterCallback($container);

        self::assertTrue($callback(false, $order));
    }

    private function makeOrder(string $paymentMethod): WC_Order
    {
        $order = Mockery::mock(WC_Order::class);
        $order->shouldReceive('get_payment_method')->andReturn($paymentMethod);

        return $order;
    }

    /**
     * @param bool|string $onStatusChangeEnabled Raw value as returned by get_option() for
     *        merchant.manual_capture.on_status_change_enabled: 'yes'/'no' (saved checkbox) or
     *        boolean false (option row never created).
     * @param array $overrides Optional 'is_waiting' / 'is_authorized' booleans for the manual-capture closures.
     */
    private function createMockContainer($onStatusChangeEnabled, array $overrides = []): ContainerInterface
    {
        $isWaiting = $overrides['is_waiting'] ?? false;
        $isAuthorized = $overrides['is_authorized'] ?? false;

        $map = [
            'shared.plugin_id' => 'mollie-payments-for-woocommerce',
            'merchant.manual_capture.on_status_change_enabled' => $onStatusChangeEnabled,
            'merchant.manual_capture.is_waiting' => static function () use ($isWaiting) {
                return $isWaiting;
            },
            'merchant.manual_capture.is_authorized' => static function () use ($isAuthorized) {
                return $isAuthorized;
            },
        ];

        $container = Mockery::mock(ContainerInterface::class);
        $container->shouldReceive('get')->andReturnUsing(static function (string $id) use ($map) {
            if (!array_key_exists($id, $map)) {
                throw new \RuntimeException("Unexpected container->get('{$id}') in test");
            }
            return $map[$id];
        });

        return $container;
    }

    private function runModuleAndGetDisableFilterCallback(ContainerInterface $container): callable
    {
        $this->interceptHooks();

        $module = new MerchantCaptureModule();
        $module->run($container);

        $this->runActionCallbacks('init');

        $callbacks = $this->hooks->filters['mollie_wc_gateway_disable_ship_and_capture'] ?? [];
        self::assertNotEmpty($callbacks, 'mollie_wc_gateway_disable_ship_and_capture filter was not registered');

        return $callbacks[0];
    }

    private function interceptHooks(): void
    {
        $hooks = $this->hooks;

        expect('add_action')->andReturnUsing(static function () use ($hooks) {
            $args = func_get_args();
            $hooks->actions[$args[0]][] = $args[1];
            return true;
        });
        expect('add_filter')->andReturnUsing(static function () use ($hooks) {
            $args = func_get_args();
            $hooks->filters[$args[0]][] = $args[1];
            return true;
        });
    }

    private function runActionCallbacks(string $hook): void
    {
        foreach ($this->hooks->actions[$hook] ?? [] as $cb) {
            $cb();
        }
    }
}

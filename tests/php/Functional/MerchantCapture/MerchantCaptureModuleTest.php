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
     * Given later-capture mode is on and status-change capture is unchecked
     * When the disable filter runs for a Klarna order
     * Then it returns true so the legacy ship-and-capture is skipped
     * @test
     */
    public function disableShipAndCaptureReturnsTrueForKlarnaWhenLaterCaptureOnAndStatusChangeUnchecked()
    {
        $container = $this->createMockContainer(['enabled' => true, 'onStatusChange' => false]);
        $order = $this->makeOrder('mollie_wc_gateway_klarna');

        $callback = $this->runModuleAndGetDisableFilterCallback($container);

        self::assertTrue($callback(false, $order));
    }

    /**
     * Given later-capture mode is on and status-change capture is checked
     * When the disable filter runs for a Klarna order
     * Then it returns false so the payment is captured on Completed
     * @test
     */
    public function disableShipAndCaptureReturnsFalseForKlarnaWhenLaterCaptureOnAndStatusChangeChecked()
    {
        $container = $this->createMockContainer(['enabled' => true, 'onStatusChange' => true]);
        $order = $this->makeOrder('mollie_wc_gateway_klarna');

        $callback = $this->runModuleAndGetDisableFilterCallback($container);

        self::assertFalse($callback(false, $order));
    }

    /**
     * Given the store is in default immediate-capture mode (later capture off)
     * When the disable filter runs for a Klarna order, whatever the status-change setting
     * Then it returns false so capture-on-Completed keeps working as before - no regression
     * @test
     * @dataProvider provideStatusChangeToggle
     */
    public function disableShipAndCaptureReturnsFalseForKlarnaWhenLaterCaptureOff(bool $onStatusChange)
    {
        $container = $this->createMockContainer(['enabled' => false, 'onStatusChange' => $onStatusChange]);
        $order = $this->makeOrder('mollie_wc_gateway_klarna');

        $callback = $this->runModuleAndGetDisableFilterCallback($container);

        self::assertFalse($callback(false, $order));
    }

    /**
     * Given a credit-card order is in the authorized on-hold state
     * When the disable filter runs, whatever the status-change setting
     * Then it returns true - the legacy path stays off and only one capture path can fire
     * @test
     * @dataProvider provideStatusChangeToggle
     */
    public function disableShipAndCaptureStillTrueForCreditcardAuthorizedRegardlessOfStatusChangeSetting(bool $onStatusChange)
    {
        $container = $this->createMockContainer([
            'enabled' => true,
            'onStatusChange' => $onStatusChange,
            'is_authorized' => true,
        ]);
        $order = $this->makeOrder('mollie_wc_gateway_creditcard');

        $callback = $this->runModuleAndGetDisableFilterCallback($container);

        self::assertTrue($callback(false, $order));
    }

    /**
     * Given later-capture mode is on and status-change capture is unchecked
     * When the disable filter runs for a non-Klarna, non-creditcard gateway
     * Then it returns true - the later-capture rule is gateway independent
     * @test
     */
    public function disableShipAndCaptureLaterCaptureTermIsGatewayIndependent()
    {
        $container = $this->createMockContainer(['enabled' => true, 'onStatusChange' => false]);
        $order = $this->makeOrder('mollie_wc_gateway_bancontact');

        $callback = $this->runModuleAndGetDisableFilterCallback($container);

        self::assertTrue($callback(false, $order));
    }

    /**
     * Given later-capture mode is off
     * When the disable filter runs for a non-creditcard gateway with status-change unchecked
     * Then it returns false - the rule only ever suppresses capture in later-capture mode
     * @test
     */
    public function disableShipAndCaptureLaterCaptureTermIsOffWhenNotLaterCapture()
    {
        $container = $this->createMockContainer(['enabled' => false, 'onStatusChange' => false]);
        $order = $this->makeOrder('mollie_wc_gateway_bancontact');

        $callback = $this->runModuleAndGetDisableFilterCallback($container);

        self::assertFalse($callback(false, $order));
    }

    /**
     * Given the raw capture_or_void option value returned by get_option()
     * When the on_status_change_enabled service resolves it
     * Then it yields strict boolean true only for the 'yes' string, false otherwise
     * @test
     * @dataProvider provideCaptureOrVoidOptionValues
     */
    public function onStatusChangeEnabledServiceReturnsBooleanTrueOnlyForYes($stored, bool $expected)
    {
        // WooCommerce saves the literal string 'no' for an unchecked checkbox (PHP-truthy),
        // and boolean false when the option row was never created - both must resolve to false.
        when('get_option')->justReturn($stored);

        $services = (new MerchantCaptureModule())->services();
        $result = $services['merchant.manual_capture.on_status_change_enabled']();

        self::assertSame($expected, $result);
    }

    public function provideStatusChangeToggle(): array
    {
        return [
            'status-change checked' => [true],
            'status-change unchecked' => [false],
        ];
    }

    public function provideCaptureOrVoidOptionValues(): array
    {
        return [
            'checked' => ['yes', true],
            'unchecked' => ['no', false],
            'empty string' => ['', false],
            'never saved' => [false, false],
        ];
    }

    private function makeOrder(string $paymentMethod): WC_Order
    {
        $order = Mockery::mock(WC_Order::class);
        $order->shouldReceive('get_payment_method')->andReturn($paymentMethod);

        return $order;
    }

    /**
     * @param array $config Filter inputs as the container resolves them:
     *        'enabled'        bool - merchant.manual_capture.enabled (place_payment_onhold === later_capture)
     *        'onStatusChange' bool - merchant.manual_capture.on_status_change_enabled (normalized capture_or_void)
     *        'is_waiting'     bool - credit-card manual-capture waiting state
     *        'is_authorized'  bool - credit-card manual-capture authorized state
     */
    private function createMockContainer(array $config = []): ContainerInterface
    {
        $enabled = $config['enabled'] ?? false;
        $onStatusChange = $config['onStatusChange'] ?? false;
        $isWaiting = $config['is_waiting'] ?? false;
        $isAuthorized = $config['is_authorized'] ?? false;

        $map = [
            'shared.plugin_id' => 'mollie-payments-for-woocommerce',
            'merchant.manual_capture.enabled' => $enabled,
            'merchant.manual_capture.on_status_change_enabled' => $onStatusChange,
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

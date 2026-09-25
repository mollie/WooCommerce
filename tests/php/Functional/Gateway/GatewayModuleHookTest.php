<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Functional\Gateway;

use Mockery;
use Mollie\WooCommerce\Gateway\GatewayModule;
use Mollie\WooCommerce\Gateway\Surcharge;
use Mollie\WooCommerce\Shared\Data;
use Mollie\WooCommerceTests\TestCase;
use Psr\Container\ContainerInterface;

use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\when;

class GatewayModuleHookTest extends TestCase
{
    private \stdClass $hooks;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hooks = (object) ['actions' => []];

        $hooks = $this->hooks;
        expect('add_action')
            ->andReturnUsing(static function () use ($hooks): bool {
                $args = func_get_args();
                $hook = $args[0];
                $callback = $args[1];
                if (!isset($hooks->actions[$hook])) {
                    $hooks->actions[$hook] = [];
                }
                $hooks->actions[$hook][] = $callback;
                return true;
            });

        when('add_filter')->justReturn(true);
        when('has_action')->justReturn(false);
    }

    /**
     * GIVEN GatewayModule::run() is called
     * WHEN it registers hooks
     * THEN the gateway iteration callback is on woocommerce_after_register_post_type
     *
     * This ensures shop_order CPT is registered before payment_gateways() is called,
     * preventing the packing-slip fatal caused by WC Subscriptions calling wc_get_order()
     * before post types exist.
     *
     * @test
     */
    public function gatewayIterationIsRegisteredOnAfterRegisterPostTypeHook(): void
    {
        $module = new GatewayModule();
        $module->run($this->makeContainer());

        $this->assertArrayHasKey(
            'woocommerce_after_register_post_type',
            $this->hooks->actions,
            'GatewayModule::run() must register callbacks on woocommerce_after_register_post_type'
        );
        $this->assertGreaterThanOrEqual(
            2,
            count($this->hooks->actions['woocommerce_after_register_post_type']),
            'woocommerce_after_register_post_type must have the gateway iteration callback and paymentButtonsBootstrap'
        );
    }

    /**
     * GIVEN GatewayModule::run() is called
     * WHEN it registers hooks
     * THEN woocommerce_init has NO callbacks from run()
     *
     * woocommerce_init fires at WP init priority 0, before shop_order is registered
     * (priority 5). Registering the gateway iteration there caused a fatal when
     * WC Subscriptions' woocommerce_available_payment_gateways filter called wc_get_order()
     * before post types existed.
     *
     * Keeping woocommerce_init clean also ensures subscription renewals work: the filters
     * registered by addSubscriptionFilters are in place (via woocommerce_after_register_post_type
     * at priority 5) before Action Scheduler fires renewal actions (priority 10+).
     *
     * @test
     */
    public function woocommerceInitHasNoCallbacksFromRun(): void
    {
        $module = new GatewayModule();
        $module->run($this->makeContainer());

        $this->assertArrayNotHasKey(
            'woocommerce_init',
            $this->hooks->actions,
            'GatewayModule::run() must not register any callbacks on woocommerce_init — ' .
            'gateway iteration and paymentButtonsBootstrap must be on woocommerce_after_register_post_type'
        );
    }

    private function makeContainer(): ContainerInterface
    {
        $surcharge = Mockery::mock(Surcharge::class)->shouldIgnoreMissing();
        $data = Mockery::mock(Data::class)->shouldIgnoreMissing();

        $container = Mockery::mock(ContainerInterface::class);
        $container->shouldReceive('get')->with('shared.plugin_id')
            ->andReturn('mollie-payments-for-woocommerce');
        $container->shouldReceive('get')->with('gateway.classnames')
            ->andReturn([]);
        $container->shouldReceive('get')->with(Surcharge::class)
            ->andReturn($surcharge);
        $container->shouldReceive('get')->with('settings.data_helper')
            ->andReturn($data);

        return $container;
    }
}
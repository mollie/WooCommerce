<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Integration\spec\Payment;

use Mockery;
use Mollie\WooCommerce\Payment\PaymentModule;
use Mollie\WooCommerceTests\Integration\IntegrationMockedTestCase;
use Psr\Log\LoggerInterface as Logger;
use ReflectionFunction;

/**
 * Integration tests for the Mollie return-redirect hook registration and detection.
 *
 * @group integration
 * @group payment-module
 * @covers PaymentModule::run
 * @covers PaymentModule::mollieReturnRedirect
 */
class PaymentModuleReturnRedirectTest extends IntegrationMockedTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        remove_all_actions('init');
        remove_all_actions('template_redirect');
        unset($_GET['filter_flag'], $_GET['order_id'], $_GET['key']);
    }

    public function tearDown(): void
    {
        unset($_GET['filter_flag'], $_GET['order_id'], $_GET['key']);
        parent::tearDown();
    }

    public function test_it_registers_return_redirect_callback_on_init_not_template_redirect(): void
    {
        $this->bootstrapModule();

        $this->assertTrue(
            $this->hookHasCallbackBoundTo('init', PaymentModule::class),
            'Expected an "init" callback bound to a PaymentModule instance (the return-redirect handler).'
        );
        $this->assertFalse(
            $this->hookHasCallbackBoundTo('template_redirect', PaymentModule::class),
            'The return-redirect handler must no longer be registered on "template_redirect".'
        );
    }

    /**
     * PaymentModule::orderByRequest() reads order_id/key via filter_input(INPUT_GET, ...), which always
     * returns NULL under the CLI SAPI regardless of $_GET (verified empirically: PHP's filter_input()
     * input buffer is only populated for real request SAPIs, never for CLI). So under phpunit, order
     * lookup always fails and onMollieReturn() returns via its RuntimeException catch branch *before*
     * reaching wp_safe_redirect()/die. That lets this test safely observe that the handler was entered
     * purely from $_GET['filter_flag'], with no dependency on is_order_received_page() or any other
     * query-resolution-dependent conditional tag, without ever touching the redirect+die path.
     */
    public function test_it_detects_return_via_get_param_independent_of_query_resolution(): void
    {
        $this->assertFalse(
            is_order_received_page(),
            'Precondition: this request must not already be WooCommerce\'s order-received page.'
        );

        $_GET['filter_flag'] = 'onMollieReturn';

        $debugMessages = [];
        $loggerSpy = Mockery::mock(Logger::class);
        $loggerSpy->shouldReceive('debug')->andReturnUsing(function ($message) use (&$debugMessages) {
            $debugMessages[] = $message;
        });

        $this->bootstrapModule([
            Logger::class => static function () use ($loggerSpy) {
                return $loggerSpy;
            },
        ]);
        $callback = $this->findCallbackBoundTo('init', PaymentModule::class);

        $this->assertNotNull($callback, 'Expected an "init" callback bound to a PaymentModule instance.');
        $callback();

        $joined = implode(' | ', $debugMessages);
        $this->assertStringContainsString(
            'Could not find order by order Id',
            $joined,
            'Expected onMollieReturn() to be entered from $_GET[\'filter_flag\'] alone and log the order-not-found path.'
        );
    }

    private function hookHasCallbackBoundTo(string $hookName, string $className): bool
    {
        return $this->findCallbackBoundTo($hookName, $className) !== null;
    }

    private function findCallbackBoundTo(string $hookName, string $className): ?\Closure
    {
        global $wp_filter;

        if (!isset($wp_filter[$hookName])) {
            return null;
        }

        foreach ($wp_filter[$hookName]->callbacks as $priorityGroup) {
            foreach ($priorityGroup as $registered) {
                $callback = $registered['function'];
                if (!($callback instanceof \Closure)) {
                    continue;
                }

                $boundThis = (new ReflectionFunction($callback))->getClosureThis();
                if ($boundThis instanceof $className) {
                    return $callback;
                }
            }
        }

        return null;
    }
}
<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Integration\spec\Payment;

use Mollie\WooCommerce\Payment\PaymentModule;
use Mollie\WooCommerceTests\Integration\IntegrationMockedTestCase;
use ReflectionFunction;

/**
 * Integration tests for the Mollie return-redirect hook registration.
 *
 * @group integration
 * @group payment-module
 * @covers PaymentModule::run
 */
class PaymentModuleReturnRedirectTest extends IntegrationMockedTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        remove_all_actions('init');
        remove_all_actions('template_redirect');
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

    private function hookHasCallbackBoundTo(string $hookName, string $className): bool
    {
        global $wp_filter;

        if (!isset($wp_filter[$hookName])) {
            return false;
        }

        foreach ($wp_filter[$hookName]->callbacks as $priorityGroup) {
            foreach ($priorityGroup as $registered) {
                $callback = $registered['function'];
                if (!($callback instanceof \Closure)) {
                    continue;
                }

                $boundThis = (new ReflectionFunction($callback))->getClosureThis();
                if ($boundThis instanceof $className) {
                    return true;
                }
            }
        }

        return false;
    }
}
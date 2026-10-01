<?php
// kb-active

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Unit\Core\Payment;

use Mollie\WooCommerce\Core\Payment\CancelUnpaidSchedule;
use Mollie\WooCommerceTests\TestCase;

/**
 * Whether the action mollie_woocommerce_cancel_unpaid_orders must be scheduled (REQ-E1; AC-27).
 *
 * Today PaymentModule::IsExpiryDateEnabled() answers the first half, and these rows pin its exact
 * reading of the gateway settings, quirks included: a gateway whose settings say enabled is anything
 * but 'yes' is skipped, while settings with no 'enabled' key at all still count. The second half is
 * new: the Express Component's cleanup runs on the same action, so it is also needed while Express
 * is enabled.
 *
 * @covers \Mollie\WooCommerce\Core\Payment\CancelUnpaidSchedule
 */
class CancelUnpaidScheduleTest extends TestCase
{
    /**
     * Scenario: the cleanup is needed when an enabled gateway has the expiry setting on, or Express is enabled
     *   Given the settings of every Mollie gateway, as get_option() returns them, and whether Express is enabled
     *   When the schedule is decided
     *   Then it is needed exactly when one of the two holds
     *
     * @dataProvider settings
     * @covers \Mollie\WooCommerce\Core\Payment\CancelUnpaidSchedule::needed
     * @param array<int, array<string, string>|false> $gatewaySettings
     */
    public function testDecidesWhetherTheUnpaidOrderCleanupIsScheduled(array $gatewaySettings, bool $expressEnabled, bool $expected): void
    {
        self::assertSame($expected, CancelUnpaidSchedule::needed($gatewaySettings, $expressEnabled));
    }

    /**
     * @return array<string, array{0: array<int, array<string, string>|false>, 1: bool, 2: bool}>
     */
    public function settings(): array
    {
        $expiryOn = ['enabled' => 'yes', 'activate_expiry_days_setting' => 'yes'];
        $expiryOff = ['enabled' => 'yes', 'activate_expiry_days_setting' => 'no'];
        $disabledWithExpiry = ['enabled' => 'no', 'activate_expiry_days_setting' => 'yes'];

        return [
            // Today's behaviour, Express off.
            'no gateways' => [[], false, false],
            'never saved settings' => [[false, false], false, false],
            'an enabled gateway with expiry on' => [[$expiryOn], false, true],
            'an enabled gateway with expiry off' => [[$expiryOff], false, false],
            'an enabled gateway without the expiry key' => [[['enabled' => 'yes']], false, false],
            'a disabled gateway with expiry on' => [[$disabledWithExpiry], false, false],
            'settings without an enabled key, expiry on' => [[['activate_expiry_days_setting' => 'yes']], false, true],
            'expiry is only on for a disabled gateway' => [[$disabledWithExpiry, $expiryOff], false, false],
            'one enabled gateway among others has expiry on' => [[$disabledWithExpiry, false, $expiryOn], false, true],
            // Express on.
            'Express enabled, no gateway has expiry' => [[$expiryOff, false], true, true],
            'Express enabled, no gateways at all' => [[], true, true],
            'Express enabled and a gateway with expiry on' => [[$expiryOn], true, true],
        ];
    }
}

<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Unit\ExpressComponent\Rules;

use Mollie\WooCommerce\ExpressComponent\Rules\ExpressSwitch;
use Mollie\WooCommerceTests\TestCase;

/**
 * Whether a wallet's express switch for a page is on: what the merchant saved, else the default.
 *
 * @covers \Mollie\WooCommerce\ExpressComponent\Rules\ExpressSwitch
 */
class ExpressSwitchTest extends TestCase
{
    /**
     * @scenario A saved switch always wins; a switch never saved follows the default
     *   Given a switch stored as on, as off, or never saved
     *   And a default that is on or off
     *   When the switch is read
     *   Then a saved value is what is read, whatever the default
     *   And a switch never saved reads as the default
     *
     * @test
     * @dataProvider switches
     * @group REQ-NEW-EXPRESS-DEFAULTS
     */
    public function it_lets_a_saved_switch_win_over_the_default(?string $stored, bool $onByDefault, bool $expected): void
    {
        // When
        $on = ExpressSwitch::effective($stored, $onByDefault);

        // Then
        self::assertSame($expected, $on);
    }

    /**
     * @return array<string, array{0: ?string, 1: bool, 2: bool}>
     */
    public function switches(): array
    {
        return [
            'saved on, default on' => ['yes', true, true],
            'saved on, default off' => ['yes', false, true],
            'saved off, default on' => ['no', true, false],
            'saved off, default off' => ['no', false, false],
            'never saved, default on' => [null, true, true],
            'never saved, default off' => [null, false, false],
        ];
    }
}

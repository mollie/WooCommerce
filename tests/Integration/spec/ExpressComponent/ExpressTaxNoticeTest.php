<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Integration\spec\ExpressComponent;

use Mollie\WooCommerceTests\Integration\Common\ExpressFlowTestCase;
use Mollie\WooCommerceTests\Integration\Common\Traits\ExpressCheckoutFixtures;

/**
 * The admin notice that tells the merchant when the shop's tax settings do not fit express checkout.
 * Shop: PayPal the only express wallet.
 *
 * @group integration
 * @group ExpressComponent
 */
class ExpressTaxNoticeTest extends ExpressFlowTestCase
{
    use ExpressCheckoutFixtures;

    public function setUp(): void
    {
        parent::setUp();

        $this->setUpExpressCheckout();
    }

    public function tearDown(): void
    {
        $this->tearDownExpressCheckout();

        parent::tearDown();
    }

    /**
     * Scenario: the merchant is told once, while a wallet's express switch is on, why express may not fit the shop
     *   Given a wallet's express switch for the checkout is on or off
     *   And the shop's tax settings, the countries it sells and ships to and its tax rates
     *   When an administrator or a visitor opens an admin page (admin_notices)
     *   Then an administrator sees one error notice when the component is hidden and one warning
     *        notice for regional rates, each linking to the tax settings
     *   And no notice is shown when the settings fit, when no express switch is on, or to a visitor
     *
     * @test
     * @dataProvider shops
     * @group REQ-NEW-EXPRESS-TAX
     * @param array<int, string> $sellsTo
     * @param string $shipsTo 'selling' (shipping follows the selling countries) or 'all' (ships to every country).
     * @param array<int, array{0: string, 1: string, 2: string}> $rates Country, state, rate of each tax rate of the shop.
     */
    public function it_shows_one_tax_notice_while_an_express_switch_is_on(
        string $taxEnabled,
        string $taxBasedOn,
        array $sellsTo,
        string $shipsTo,
        array $rates,
        bool $switchOn,
        bool $isAdministrator,
        ?string $expectedLevel
    ): void {

        // Given
        $this->setOptionForTest('woocommerce_calc_taxes', $taxEnabled);
        $this->setOptionForTest('woocommerce_tax_based_on', $taxBasedOn);
        $this->setOptionForTest('woocommerce_allowed_countries', 'specific');
        $this->setOptionForTest('woocommerce_specific_allowed_countries', $sellsTo);
        $this->setOptionForTest('woocommerce_ship_to_countries', $shipsTo === 'all' ? 'all' : '');
        foreach ($rates as [$country, $state, $rate]) {
            $this->taxRateIds[] = \WC_Tax::_insert_tax_rate([
                'tax_rate_country' => $country,
                'tax_rate_state' => $state,
                'tax_rate' => $rate,
                'tax_rate_name' => 'VAT',
                'tax_rate_priority' => 1,
                'tax_rate_compound' => 0,
                'tax_rate_shipping' => 1,
                'tax_rate_order' => 0,
                'tax_rate_class' => '',
            ]);
        }
        $this->setGatewaySettingsForTest('paypal', ['mollie_paypal_button_enabled_checkout' => $switchOn ? 'yes' : 'no']);
        // Earlier boots left their own admin_notices callbacks behind.
        remove_all_actions('admin_notices');
        $this->bootExpress();
        wp_set_current_user($isAdministrator ? $this->administrator() : 0);

        // When
        ob_start();
        do_action('admin_notices');
        $page = (string) ob_get_clean();

        // Then
        $pattern = '/<div\b[^>]*class="notice (notice-error|notice-warning)"[^>]*>(?:(?!<\/div>).)*page=wc-settings&(?:amp;|#038;)?tab=tax/s';
        $found = preg_match_all($pattern, $page, $matches);
        if ($expectedLevel === null) {
            $this->assertSame(0, $found, 'No tax notice is expected.');

            return;
        }
        $this->assertSame(1, $found, 'Exactly one tax notice is expected.');
        $this->assertSame($expectedLevel, $matches[1][0]);
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: array<int, string>, 3: string, 4: array<int, array{0: string, 1: string, 2: string}>, 5: bool, 6: bool, 7: ?string}>
     */
    public function shops(): array
    {
        $oneRateInGermany = [['DE', '', '19.0000']];
        $ratesByStateInGermany = [['DE', 'BY', '19.0000'], ['DE', 'BE', '20.0000']];

        return [
            'two countries, taxes on the billing address' => ['yes', 'billing', ['DE', 'IT'], 'selling', $oneRateInGermany, true, true, 'notice-error'],
            'two countries, taxes on the shipping address' => ['yes', 'shipping', ['DE', 'IT'], 'selling', $oneRateInGermany, true, true, 'notice-error'],
            'one country, rates differ by state' => ['yes', 'billing', ['DE'], 'selling', $ratesByStateInGermany, true, true, 'notice-warning'],
            'one country, one rate' => ['yes', 'billing', ['DE'], 'selling', $oneRateInGermany, true, true, null],
            'two countries, taxes on the shop address' => ['yes', 'base', ['DE', 'IT'], 'selling', $oneRateInGermany, true, true, null],
            'two countries, taxes off' => ['no', 'billing', ['DE', 'IT'], 'selling', [], true, true, null],
            // The countries the taxes can follow are the shipping ones when they follow the shipping address.
            'sells to one country, ships worldwide, taxes on the shipping address' => ['yes', 'shipping', ['DE'], 'all', $oneRateInGermany, true, true, 'notice-error'],
            'sells to one country, ships worldwide, taxes on the billing address' => ['yes', 'billing', ['DE'], 'all', $oneRateInGermany, true, true, null],
            'incompatible, but no express switch is on' => ['yes', 'billing', ['DE', 'IT'], 'selling', $oneRateInGermany, false, true, null],
            'regional, but no express switch is on' => ['yes', 'billing', ['DE'], 'selling', $ratesByStateInGermany, false, true, null],
            'incompatible, shown to a visitor' => ['yes', 'billing', ['DE', 'IT'], 'selling', $oneRateInGermany, true, false, null],
        ];
    }

    private function administrator(): int
    {
        $existing = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
        $this->assertNotSame([], $existing, 'The test site needs an administrator.');

        return (int) $existing[0];
    }
}

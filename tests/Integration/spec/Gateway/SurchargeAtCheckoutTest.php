<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Integration\spec\Gateway;

use Mockery;
use Mollie\WooCommerceTests\Integration\Common\ExpressFlowTestCase;
use Mollie\WooCommerceTests\Integration\Common\Traits\ExpressCheckoutFixtures;
use Mollie\WooCommerceTests\Integration\Common\Traits\WebhookOrderFixtures;

/**
 * The fee a merchant puts on a payment method: what it adds to the cart of the shopper who chose
 * that method, and what the method says about it at the checkout.
 *
 * The e2e suite covers it in tests/qa/tests/03-plugin-settings/surcharge, ten configurations for
 * each of 21 methods; the fee does not depend on the method, so each configuration is one row here.
 * tests/Integration/e2e-equivalence.md has the list. ExpressSurchargeTest covers the express side.
 *
 * @group integration
 * @group Surcharge
 */
class SurchargeAtCheckoutTest extends ExpressFlowTestCase
{
    use ExpressCheckoutFixtures;
    use WebhookOrderFixtures;

    private const METHOD = 'ideal';

    /**
     * @var array<int, int>
     */
    private array $ownTaxRateIds = [];

    public function tearDown(): void
    {
        foreach ($this->ownTaxRateIds as $taxRateId) {
            \WC_Tax::_delete_tax_rate($taxRateId);
        }
        $this->ownTaxRateIds = [];
        $this->container = null;
        $this->tearDownExpressCheckout();
        Mockery::close();

        parent::tearDown();
    }

    /**
     * Scenario: the chosen method's fee is added to the cart
     *   Given a cart of 100.00, no tax, nothing to ship
     *   And a fee configured on the method
     *   When the shopper chooses that method
     *   Then the cart holds one "Gateway Fee" of the expected amount, and the total follows
     *   And the method's description says which fee might apply
     *
     * @test
     * @dataProvider feeConfigurations
     * @covers \Mollie\WooCommerce\Shared\GatewaySurchargeHandler::add_engraving_fees
     * @covers \Mollie\WooCommerce\Gateway\Surcharge
     *
     * @param array<string, string> $settings
     */
    public function it_adds_the_fee_of_the_chosen_method_to_the_cart(array $settings, ?string $fee, string $total, string $feeText): void
    {
        $this->shopperChoosing(self::METHOD, $settings, false);

        $this->assertSame($fee === null ? [] : [$fee], $this->gatewayFees());
        $this->assertSame($total, $this->cartTotal());
        $description = $this->descriptionOf(self::METHOD);
        if ($feeText === '') {
            $this->assertStringNotContainsString('mollie-gateway-fee', $description);
        } else {
            $this->assertStringContainsString('<p class="mollie-gateway-fee">' . $feeText . '</p>', $description);
        }
    }

    /**
     * The ten configurations of the e2e suite, on a cart of 100.00. maximum_limit is the cart
     * amount above which no fee is asked; "over limit" sets it to 10, "under limit" to 150.
     *
     * @return array<string, array{0: array<string, string>, 1: string|null, 2: string, 3: string}>
     */
    public function feeConfigurations(): array
    {
        $fixed = ' + &euro; 10 fee might apply';
        $percentage = ' + 10% fee might apply';
        $both = ' + &euro; 10 + 10% fee might apply';

        return [
            'no fee' => [$this->settings('no_fee', '0', '0', '0'), null, '100.00', ''],
            'fixed' => [$this->settings('fixed_fee', '10', '0', '0'), '10.00', '110.00', $fixed],
            'fixed, under limit' => [$this->settings('fixed_fee', '10', '0', '150'), '10.00', '110.00', $fixed],
            'fixed, over limit' => [$this->settings('fixed_fee', '10', '0', '10'), null, '100.00', $fixed],
            'percentage' => [$this->settings('percentage', '0', '10', '0'), '10.00', '110.00', $percentage],
            'percentage, under limit' => [$this->settings('percentage', '0', '10', '150'), '10.00', '110.00', $percentage],
            'percentage, over limit' => [$this->settings('percentage', '0', '10', '10'), null, '100.00', $percentage],
            'fixed and percentage' => [$this->settings('fixed_fee_percentage', '10', '10', '0'), '20.00', '120.00', $both],
            'fixed and percentage, under limit' => [$this->settings('fixed_fee_percentage', '10', '10', '150'), '20.00', '120.00', $both],
            'fixed and percentage, over limit' => [$this->settings('fixed_fee_percentage', '10', '10', '10'), null, '100.00', $both],
        ];
    }

    /**
     * Scenario: the fee never exceeds the cap the merchant set on it
     *   Given a 10 % fee capped at 4.00, on a cart of 100.00
     *   When the shopper chooses that method
     *   Then the fee is 4.00
     *
     * @test
     * @covers \Mollie\WooCommerce\Gateway\Surcharge::calculateFeeAmount
     */
    public function it_caps_the_fee_at_the_merchants_limit(): void
    {
        $this->shopperChoosing(self::METHOD, array_merge($this->settings('percentage', '0', '10', '0'), ['surcharge_limit' => '4']), false);

        $this->assertSame(['4.00'], $this->gatewayFees());
        $this->assertSame('104.00', $this->cartTotal());
    }

    /**
     * Scenario: in a shop that charges tax, the fee is taxed and is a share of the taxed cart
     *   Given 10 % tax, a cart of 100.00 before tax, nothing to ship
     *   And a fee of 10.00 plus 10 % on the method
     *   When the shopper chooses that method
     *   Then the fee is 10.00 plus 10 % of 110.00, and is taxed itself
     *   And the method's description says the fee is without VAT
     *
     * @test
     * @covers \Mollie\WooCommerce\Shared\GatewaySurchargeHandler::add_engraving_fees
     */
    public function it_taxes_the_fee_and_takes_the_percentage_of_the_taxed_cart(): void
    {
        $this->shopperChoosing(self::METHOD, $this->settings('fixed_fee_percentage', '10', '10', '0'), true);

        // 10.00 + 10 % of (100.00 + 10.00 tax) = 21.00, and 2.10 tax on it.
        $this->assertSame(['21.00'], $this->gatewayFees());
        $this->assertSame('133.10', $this->cartTotal());
        $this->assertStringContainsString(
            '<p class="mollie-gateway-fee"> + &euro; 10 + 10% fee might apply (excl. VAT)</p>',
            $this->descriptionOf(self::METHOD)
        );
    }

    /**
     * Scenario: the fee belongs to the method the shopper chose
     *   Given a fee on one method and none on another
     *   When the shopper chooses the other method
     *   Then the cart holds no fee
     *
     * @test
     * @covers \Mollie\WooCommerce\Shared\GatewaySurchargeHandler::add_engraving_fees
     */
    public function it_adds_no_fee_when_the_shopper_chose_another_method(): void
    {
        $this->shopperChoosing(self::METHOD, $this->settings('fixed_fee', '10', '0', '0'), false);

        WC()->session->set('chosen_payment_method', 'mollie_wc_gateway_creditcard');
        WC()->cart->calculate_totals();

        $this->assertSame([], $this->gatewayFees());
        $this->assertSame('100.00', $this->cartTotal());
    }

    /**
     * @return array<string, string>
     */
    private function settings(string $type, string $fixedFee, string $percentage, string $maximumLimit): array
    {
        return [
            'payment_surcharge' => $type,
            'fixed_fee' => $fixedFee,
            'percentage' => $percentage,
            'maximum_limit' => $maximumLimit,
            'surcharge_limit' => '0',
        ];
    }

    /**
     * A shopper at the checkout with ten products of 10.00, who chose the method.
     *
     * @param array<string, string> $settings
     */
    private function shopperChoosing(string $method, array $settings, bool $withTax): void
    {
        $this->setGatewaySettingsForTest($method, $settings);
        $this->addTestFilter('wc_tax_enabled', static function () use ($withTax): bool {
            return $withTax;
        });
        $this->addTestFilter('woocommerce_cart_needs_shipping', '__return_false');
        if ($withTax) {
            $this->addTestFilter('pre_option_woocommerce_prices_include_tax', static function (): string {
                return 'no';
            });
            $this->addTestFilter('pre_option_woocommerce_tax_based_on', static function (): string {
                return 'billing';
            });
            $this->ownTaxRateIds[] = \WC_Tax::_insert_tax_rate([
                'tax_rate_country' => 'DE',
                'tax_rate' => '10.0000',
                'tax_rate_name' => 'VAT',
                'tax_rate_priority' => 1,
                'tax_rate_shipping' => 1,
                'tax_rate_class' => '',
            ]);
        }

        $this->setOptionForTest(self::PLUGIN_ID . '_gatewayFeeLabel', 'Gateway Fee');
        // The fee listener is attached on init; this boot is the only one heard.
        $this->removePluginListenersOfEarlierBoots();
        $this->container = $this->bootExpressOwning(['init']);
        do_action('init');

        $this->actAsGuest();
        $this->cartWith(['simple'], 10);
        $this->fillCheckoutForm(array_merge($this->billing(), ['country' => 'DE']), $this->shipping('DE'));
        WC()->session->set('chosen_payment_method', 'mollie_wc_gateway_' . $method);
        WC()->cart->calculate_totals();
    }

    /**
     * @return array<int, string> The amount of every "Gateway Fee" in the cart.
     */
    private function gatewayFees(): array
    {
        $amounts = [];
        foreach (WC()->cart->get_fees() as $fee) {
            if ($fee->name === 'Gateway Fee') {
                $amounts[] = number_format((float) $fee->amount, 2, '.', '');
            }
        }

        return $amounts;
    }

    private function cartTotal(): string
    {
        return number_format((float) WC()->cart->get_total('edit'), 2, '.', '');
    }

    private function descriptionOf(string $method): string
    {
        $gateways = WC()->payment_gateways()->payment_gateways();

        return (string) $gateways['mollie_wc_gateway_' . $method]->get_description();
    }
}

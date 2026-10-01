<?php
// kb-active

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Unit\Components;

use Mollie\WooCommerce\Components\ComponentDataService;
use Mollie\WooCommerce\Settings\Settings;
use Mollie\WooCommerceTests\TestCase;
use Mockery;

use function Brain\Monkey\Functions\when;

/**
 * The locale the v1 card fields get (REQ-513, CF-02).
 *
 * getValidatedLocale() now delegates to Core\Payment\MollieJsLocale; this pins that the card fields
 * still receive the shop's WordPress locale mapped exactly as before the extraction.
 *
 * @covers \Mollie\WooCommerce\Components\ComponentDataService
 */
class ComponentDataServiceTest extends TestCase
{
    /**
     * Scenario: the card fields get the WordPress locale mapped onto one Mollie accepts
     *   Given a merchant profile and a gateway with Mollie components
     *   And the shop's WordPress locale
     *   When the component data is built
     *   Then options.locale is that locale without '_formal', or en_US when Mollie does not accept it
     *
     * @dataProvider locales
     */
    public function testGivesTheCardFieldsTheValidatedShopLocale(string $wordPressLocale, string $expected): void
    {
        $settings = Mockery::mock(Settings::class);
        $settings->allows('mollieWooCommerceMerchantProfileId')->andReturn('pfl_test');
        $settings->allows('isTestModeEnabled')->andReturn(false);
        when('mollieWooCommerceComponentsStylesForAvailableGateways')->justReturn(['creditcard' => []]);
        when('esc_html__')->returnArg(1);
        when('get_locale')->justReturn($wordPressLocale);

        $data = (new ComponentDataService($settings))->getComponentData();

        self::assertIsArray($data);
        self::assertSame($expected, $data['options']['locale']);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public function locales(): array
    {
        return [
            'accepted' => ['nl_NL', 'nl_NL'],
            'formal variant' => ['de_DE_formal', 'de_DE'],
            'not accepted' => ['xx_XX', 'en_US'],
        ];
    }
}

<?php
// kb-active

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Integration\spec\Gateway;

use Mollie\WooCommerce\PaymentMethods\AbstractPaymentMethod;
use Mollie\WooCommerceTests\Integration\Common\PaymentFlowTestCase;

/**
 * The icon a payment method shows when the merchant uploaded no logo of their own.
 *
 * It is Mollie's SVG from the methods list when the list has one, and the plugin's own icon
 * otherwise. The list is cached, and a cached list whose entry has no SVG — Mollie's answer without
 * images, or a double's in a shared test database — used to be a fatal TypeError on every page that
 * lists gateways, instead of the plugin's own icon.
 *
 * @group integration
 * @group PaymentMethodIcon
 */
class PaymentMethodIconTest extends PaymentFlowTestCase
{
    private const METHOD = 'ideal';

    public function setUp(): void
    {
        parent::setUp();
        // No uploaded logo and no credit card selector: the API icon is the one in question.
        $this->setGatewaySettingsForTest(self::METHOD, ['iconFilePath' => '', 'iconFileUrl' => '']);
    }

    /**
     * Scenario: a method whose Mollie entry has no usable SVG shows the plugin's own icon
     *   Given the merchant uploaded no logo for iDEAL
     *   And Mollie's methods list, as cached, has an iDEAL entry with no image, an image without an
     *       SVG or with an empty one, or an image that is not an object
     *   When the gateway's icons are built
     *   Then nothing throws
     *   And the one icon is the plugin's own iDEAL icon
     *
     * @test
     * @dataProvider listsWithoutAnSvg
     * @covers \Mollie\WooCommerce\PaymentMethods\AbstractPaymentMethod::paymentMethodIconProvider
     * @param array<string, mixed> $methodsList
     */
    public function it_falls_back_to_the_plugins_own_icon_when_mollie_has_no_svg(array $methodsList): void
    {
        $sources = $this->iconSources($methodsList);

        $this->assertCount(1, $sources);
        $this->assertStringEndsWith('public/images/ideal.svg', $sources[0]);
    }

    /**
     * @return array<string, array{0: array<string, mixed>}>
     */
    public function listsWithoutAnSvg(): array
    {
        return [
            'an entry without an image' => [[self::METHOD => ['id' => self::METHOD, 'description' => 'iDEAL']]],
            'an image without an svg' => [[self::METHOD => ['id' => self::METHOD, 'image' => (object) ['size1x' => 'https://example.org/ideal.png']]]],
            'an image that is an array' => [[self::METHOD => ['id' => self::METHOD, 'image' => ['svg' => 'https://example.org/ideal.svg']]]],
            'an empty svg' => [[self::METHOD => ['id' => self::METHOD, 'image' => (object) ['svg' => '']]]],
        ];
    }

    /**
     * Scenario: a method whose Mollie entry has an SVG shows Mollie's SVG
     *   Given the merchant uploaded no logo for iDEAL
     *   And Mollie's methods list has an SVG for iDEAL
     *   When the gateway's icons are built
     *   Then the one icon is Mollie's SVG
     *
     * @test
     * @covers \Mollie\WooCommerce\PaymentMethods\AbstractPaymentMethod::paymentMethodIconProvider
     */
    public function it_shows_mollies_svg_when_the_list_has_one(): void
    {
        $svg = 'https://www.mollie.com/external/icons/payment-methods/ideal.svg';

        $sources = $this->iconSources([
            self::METHOD => ['id' => self::METHOD, 'image' => (object) ['svg' => $svg]],
        ]);

        $this->assertSame([$svg], $sources);
    }

    /**
     * @param array<string, mixed> $methodsList What 'gateway.getPaymentMethodsAfterFeatureFlag' returns.
     * @return array<int, string>
     */
    private function iconSources(array $methodsList): array
    {
        $container = $this->bootstrapModule([
            'gateway.getPaymentMethodsAfterFeatureFlag' => static function () use ($methodsList): array {
                return $methodsList;
            },
        ]);
        $method = $container->get('gateway.paymentMethods')[self::METHOD] ?? null;
        $this->assertInstanceOf(AbstractPaymentMethod::class, $method);

        $sources = [];
        foreach ($method->paymentMethodIconProvider($container)->provideIcons() as $icon) {
            $sources[] = $icon->src();
        }

        return $sources;
    }
}

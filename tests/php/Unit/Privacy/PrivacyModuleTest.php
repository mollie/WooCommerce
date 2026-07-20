<?php
# kb-active

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Unit\Privacy;

use Mockery;
use Mollie\WooCommerce\Privacy\PrivacyModule;
use Mollie\WooCommerceTests\TestCase;

use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\when;

/**
 * @covers \Mollie\WooCommerce\Privacy\PrivacyModule::registerPrivacyPolicyContent
 */
class PrivacyModuleTest extends TestCase
{
    private PrivacyModule $sut;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sut = new PrivacyModule();
    }

    // T3: method returns silently when wp_add_privacy_policy_content does not exist (WP < 4.9.6)
    // Runs first so Brain\Monkey has not yet eval()-defined the function stub; function_exists() returns false.
    public function testRegisterPrivacyPolicyContent_silentWhenFunctionMissing(): void
    {
        $this->expectNotToPerformAssertions();
        $this->sut->registerPrivacyPolicyContent();
    }

    // T1: admin_init callback calls wp_add_privacy_policy_content with plugin name as first arg
    public function testRegisterPrivacyPolicyContent_callsWpAddPrivacyPolicyContentWithPluginName(): void
    {
        expect('wp_add_privacy_policy_content')
            ->once()
            ->with('Mollie Payments for WooCommerce', Mockery::type('string'));

        $this->sut->registerPrivacyPolicyContent();

        $this->addToAssertionCount(1);
    }

    // T2: second argument contains the exact href and anchor text "here"
    public function testRegisterPrivacyPolicyContent_policyTextContainsExactLinkAndAnchor(): void
    {
        $captured = null;
        when('wp_add_privacy_policy_content')->alias(function ($name, $text) use (&$captured): void {
            $captured = $text;
        });

        $this->sut->registerPrivacyPolicyContent();

        self::assertNotNull($captured, 'wp_add_privacy_policy_content was not called');
        self::assertStringContainsString('https://www.mollie.com/legal/privacy', $captured);
        self::assertRegExp(
            '/<a\s[^>]*href=["\']https:\/\/www\.mollie\.com\/legal\/privacy["\'][^>]*>here<\/a>/i',
            $captured
        );
    }
}

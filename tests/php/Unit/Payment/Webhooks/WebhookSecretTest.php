<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Unit\Payment\Webhooks;

use Mollie\WooCommerce\Payment\Webhooks\WebhookSecret;
use Mollie\WooCommerceTests\TestCase;

use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\when;

/**
 * @covers \Mollie\WooCommerce\Payment\Webhooks\WebhookSecret
 */
class WebhookSecretTest extends TestCase
{
    private WebhookSecret $sut;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sut = new WebhookSecret();
    }

    /**
     * @scenario check() returns false when no incoming secret is provided
     * @covers \Mollie\WooCommerce\Payment\Webhooks\WebhookSecret::check
     */
    public function testCheckReturnsFalseWhenNoSecretProvided(): void
    {
        when('get_option')->justReturn('stored-secret-that-is-exactly-32chars!');

        $result = $this->sut->check(null);

        self::assertFalse($result);
    }

    /**
     * @scenario check() returns false when the incoming secret does not match the stored one
     * @covers \Mollie\WooCommerce\Payment\Webhooks\WebhookSecret::check
     */
    public function testCheckReturnsFalseWhenWrongSecretProvided(): void
    {
        when('get_option')->justReturn('stored-secret-that-is-exactly-32chars!');

        $result = $this->sut->check('wrong-token');

        self::assertFalse($result);
    }

    /**
     * @scenario check() returns true when the incoming secret matches the stored one
     * @covers \Mollie\WooCommerce\Payment\Webhooks\WebhookSecret::check
     */
    public function testCheckReturnsTrueWhenCorrectSecretProvided(): void
    {
        $secret = 'stored-secret-that-is-exactly-32chars!';
        when('get_option')->justReturn($secret);

        $result = $this->sut->check($secret);

        self::assertTrue($result);
    }

    /**
     * @scenario check() returns false when the stored option is empty, instead of
     * generating a secret as a side effect of a verification call.
     * @covers \Mollie\WooCommerce\Payment\Webhooks\WebhookSecret::check
     */
    public function testCheckReturnsFalseWhenStoredSecretIsEmpty(): void
    {
        when('get_option')->justReturn('');
        when('wp_generate_password')->justReturn('freshly-generated-32-char-secret');
        expect('update_option')->once();

        $result = $this->sut->check('anything');

        self::assertFalse($result);
    }

    /**
     * @scenario getOrCreate() generates and stores a secret when the option is empty,
     * covering the case UrlMiddleware/WebhookTestService hit on the very first
     * payment created on a site, before rest_api_init has ever run.
     * @covers \Mollie\WooCommerce\Payment\Webhooks\WebhookSecret::getOrCreate
     */
    public function testGetOrCreateGeneratesAndStoresSecretWhenOptionEmpty(): void
    {
        $generated = 'abcdefghijklmnopqrstuvwxyz123456'; // 32 chars
        when('get_option')->justReturn('');
        when('wp_generate_password')->justReturn($generated);
        expect('update_option')->once()->andReturn(true);

        $result = $this->sut->getOrCreate();

        self::assertSame($generated, $result);
        self::assertGreaterThanOrEqual(32, strlen($result));
    }

    /**
     * @scenario getOrCreate() returns the existing option value without regenerating it
     * @covers \Mollie\WooCommerce\Payment\Webhooks\WebhookSecret::getOrCreate
     */
    public function testGetOrCreateReturnsExistingSecretWithoutRegenerating(): void
    {
        $existing = 'stored-secret-that-is-exactly-32chars!';
        when('get_option')->justReturn($existing);
        expect('wp_generate_password')->never();
        expect('update_option')->never();

        $result = $this->sut->getOrCreate();

        self::assertSame($existing, $result);
    }
}
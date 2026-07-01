<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Unit\Payment\Webhooks;

use Mockery;
use Mollie\WooCommerce\Payment\MollieOrderService;
use Mollie\WooCommerce\Payment\Webhooks\RestApi;
use Mollie\WooCommerce\Settings\Webhooks\WebhookTestService;
use Mollie\WooCommerceTests\TestCase;
use Psr\Log\LoggerInterface;

use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\when;

/**
 * @covers \Mollie\WooCommerce\Payment\Webhooks\RestApi
 */
class RestApiTest extends TestCase
{
    /** @var Mockery\MockInterface&MollieOrderService */
    private $orderService;

    /** @var Mockery\MockInterface&LoggerInterface */
    private $logger;

    /** @var Mockery\MockInterface&WebhookTestService */
    private $webhookTestService;

    private RestApi $sut;

    protected function setUp(): void
    {
        parent::setUp();
        $this->orderService = Mockery::mock(MollieOrderService::class);
        $this->logger = Mockery::mock(LoggerInterface::class);
        $this->webhookTestService = Mockery::mock(WebhookTestService::class);
        $this->sut = new RestApi($this->orderService, $this->logger, $this->webhookTestService);
    }

    /**
     * @scenario POST without mollie_webhook_secret returns HTTP 401
     * @covers \Mollie\WooCommerce\Payment\Webhooks\RestApi::checkWebhookSecret
     */
    public function testCheckWebhookSecretReturnsFalseWhenNoSecretProvided(): void
    {
        when('get_option')->justReturn('stored-secret-that-is-exactly-32chars!');

        $result = $this->sut->checkWebhookSecret(null);

        self::assertFalse($result);
    }

    /**
     * @scenario POST with incorrect mollie_webhook_secret returns HTTP 401
     * @covers \Mollie\WooCommerce\Payment\Webhooks\RestApi::checkWebhookSecret
     */
    public function testCheckWebhookSecretReturnsFalseWhenWrongSecretProvided(): void
    {
        when('get_option')->justReturn('stored-secret-that-is-exactly-32chars!');

        $result = $this->sut->checkWebhookSecret('wrong-token');

        self::assertFalse($result);
    }

    /**
     * @scenario POST with correct mollie_webhook_secret proceeds to callback
     * @covers \Mollie\WooCommerce\Payment\Webhooks\RestApi::checkWebhookSecret
     */
    public function testCheckWebhookSecretReturnsTrueWhenCorrectSecretProvided(): void
    {
        $secret = 'stored-secret-that-is-exactly-32chars!';
        when('get_option')->justReturn($secret);

        $result = $this->sut->checkWebhookSecret($secret);

        self::assertTrue($result);
    }

    /**
     * @scenario Stored secret is at least 32 chars produced by wp_generate_password(32, false)
     * @covers \Mollie\WooCommerce\Payment\Webhooks\RestApi::getOrCreateWebhookSecret
     */
    public function testGetOrCreateWebhookSecretGeneratesAndStoresSecretWhenOptionEmpty(): void
    {
        // Arrange
        $generated = 'abcdefghijklmnopqrstuvwxyz123456'; // 32 chars
        when('get_option')->justReturn('');
        when('wp_generate_password')->justReturn($generated);
        expect('update_option')->once()->andReturn(true);

        // When
        $result = $this->sut->getOrCreateWebhookSecret();

        // Then
        self::assertSame($generated, $result);
        self::assertGreaterThanOrEqual(32, strlen($result));
    }
}

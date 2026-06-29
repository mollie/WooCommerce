<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Unit\Settings\Webhooks;

use Mockery;
use Mollie\WooCommerce\SDK\Api;
use Mollie\WooCommerce\Settings\Settings;
use Mollie\WooCommerce\Settings\Webhooks\WebhookTestService;
use Mollie\WooCommerceTests\TestCase;
use Psr\Log\LoggerInterface;

use function Brain\Monkey\Functions\when;

/**
 * @covers \Mollie\WooCommerce\Settings\Webhooks\WebhookTestService
 */
class WebhookTestServiceTest extends TestCase
{
    /** @var Mockery\MockInterface&Api */
    private $apiHelper;

    /** @var Mockery\MockInterface&Settings */
    private $settingsHelper;

    /** @var Mockery\MockInterface&LoggerInterface */
    private $logger;

    private WebhookTestService $sut;

    protected function setUp(): void
    {
        parent::setUp();
        $this->apiHelper = Mockery::mock(Api::class);
        $this->settingsHelper = Mockery::mock(Settings::class);
        $this->logger = Mockery::mock(LoggerInterface::class);
        $this->sut = new WebhookTestService($this->apiHelper, $this->settingsHelper, $this->logger);
    }

    /**
     * @scenario URL from WebhookTestService::getWebhookUrl() includes mollie_webhook_secret
     * @covers \Mollie\WooCommerce\Settings\Webhooks\WebhookTestService::getWebhookUrl
     *
     * Phase 03 must change getWebhookUrl() to at least protected — or this
     * reflection call must be preserved unchanged.
     */
    public function testGetWebhookUrlIncludesMollieWebhookSecret(): void
    {
        // Arrange
        $secret = 'test-webhook-secret-exactly-32cha';
        when('get_option')->justReturn($secret);
        when('rest_url')->justReturn('https://example.com/wp-json/mollie/v1/webhook');
        when('add_query_arg')->alias(static function (array $args, string $url): string {
            $separator = strpos($url, '?') === false ? '?' : '&';
            return $url . $separator . http_build_query($args);
        });
        when('wp_parse_url')->alias(static fn(string $url): array|false => parse_url($url));

        $apiHelper = $this->apiHelper;
        $settingsHelper = $this->settingsHelper;
        $logger = $this->logger;
        $exposed = new class($apiHelper, $settingsHelper, $logger) extends WebhookTestService {
            public function exposeGetWebhookUrl(string $testId): string
            {
                return $this->getWebhookUrl($testId);
            }
        };

        // When
        $result = $exposed->exposeGetWebhookUrl('test_abc123');

        // Then
        self::assertStringContainsString('mollie_webhook_secret=' . $secret, $result);
    }
}

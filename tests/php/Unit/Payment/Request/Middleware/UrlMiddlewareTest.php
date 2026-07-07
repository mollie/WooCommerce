<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Unit\Payment\Request\Middleware;

use Mockery;
use Mollie\WooCommerce\Payment\Request\Middleware\UrlMiddleware;
use Mollie\WooCommerce\Payment\Webhooks\WebhookSecret;
use Mollie\WooCommerceTests\TestCase;
use Psr\Log\LoggerInterface;
use WC_Order;

use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\when;

/**
 * @covers \Mollie\WooCommerce\Payment\Request\Middleware\UrlMiddleware
 */
class UrlMiddlewareTest extends TestCase
{
    /** @var Mockery\MockInterface&LoggerInterface */
    private $logger;

    private UrlMiddleware $sut;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logger = Mockery::mock(LoggerInterface::class);
        $this->logger->shouldReceive('debug')->andReturn(null);
        $this->sut = new UrlMiddleware('mollie_wc', $this->logger, new WebhookSecret());
    }

    /**
     * @scenario URL from UrlMiddleware::getWebhookUrl() includes mollie_webhook_secret
     * @covers \Mollie\WooCommerce\Payment\Request\Middleware\UrlMiddleware::getWebhookUrl
     */
    public function testGetWebhookUrlIncludesMollieWebhookSecret(): void
    {
        // Arrange
        $secret = 'test-webhook-secret-exactly-32cha';
        when('get_option')->justReturn($secret);
        when('get_rest_url')->justReturn('https://example.com/wp-json/mollie/v1/webhook');
        when('wc_is_valid_url')->justReturn(true);
        when('apply_filters')->alias(static function (string $hook, ...$args): mixed {
            // Return false for the disable-rest-webhook filter; pass URL through for everything else.
            if ($hook === 'mollie_wc_gateway_disable_rest_webhook') {
                return false;
            }
            return $args[0] ?? null;
        });
        when('add_query_arg')->alias(static function (array $args, string $url): string {
            $separator = strpos($url, '?') === false ? '?' : '&';
            return $url . $separator . http_build_query($args);
        });

        $order = new WC_Order();

        // When
        $result = $this->sut->getWebhookUrl($order, 'mollie_wc_gateway_ideal');

        // Then
        self::assertStringContainsString('mollie_webhook_secret=' . $secret, $result);
    }

    /**
     * @scenario When the mollie_webhook_secret option has never been generated (e.g. the
     * very first payment on a site, built before rest_api_init has ever run), the URL
     * must still contain a freshly generated secret rather than an empty one - an empty
     * secret would be baked permanently into the webhook URL for that order.
     * @covers \Mollie\WooCommerce\Payment\Request\Middleware\UrlMiddleware::getWebhookUrl
     */
    public function testGetWebhookUrlGeneratesSecretWhenOptionIsEmpty(): void
    {
        // Arrange
        $generated = 'freshly-generated-32-char-secret';
        when('get_option')->justReturn('');
        when('wp_generate_password')->justReturn($generated);
        expect('update_option')->once()->andReturn(true);
        when('get_rest_url')->justReturn('https://example.com/wp-json/mollie/v1/webhook');
        when('wc_is_valid_url')->justReturn(true);
        when('apply_filters')->alias(static function (string $hook, ...$args): mixed {
            if ($hook === 'mollie_wc_gateway_disable_rest_webhook') {
                return false;
            }
            return $args[0] ?? null;
        });
        when('add_query_arg')->alias(static function (array $args, string $url): string {
            $separator = strpos($url, '?') === false ? '?' : '&';
            return $url . $separator . http_build_query($args);
        });

        $order = new WC_Order();

        // When
        $result = $this->sut->getWebhookUrl($order, 'mollie_wc_gateway_ideal');

        // Then
        self::assertStringContainsString('mollie_webhook_secret=' . $generated, $result);
        self::assertStringNotContainsString('mollie_webhook_secret=&', $result);
    }
}

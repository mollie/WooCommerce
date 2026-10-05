<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Unit\Payment;

use Mockery;
use Mollie\WooCommerce\Payment\MollieObject;
use Mollie\WooCommerce\Payment\MollieOrderService;
use Mollie\WooCommerce\Payment\PaymentFactory;
use Mollie\WooCommerce\Payment\Webhooks\WebhookHandler;
use Mollie\WooCommerce\SDK\HttpResponse;
use Mollie\WooCommerce\Shared\Data;
use Mollie\WooCommerceTests\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Resolving the redirect URL of a Mollie payment in the REST webhook fallback, used when no
 * WooCommerce order matches the transaction ID.
 *
 * @covers \Mollie\WooCommerce\Payment\MollieOrderService::getRedirectUrlFromPaymentObject
 */
class MollieOrderServiceTest extends TestCase
{
    private const TRANSACTION_ID = 'tr_noredirect';

    /** @var Mockery\MockInterface&LoggerInterface */
    private $logger;

    /** @var Mockery\MockInterface&PaymentFactory */
    private $paymentFactory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logger = Mockery::mock(LoggerInterface::class);
        $this->paymentFactory = Mockery::mock(PaymentFactory::class);
    }

    private function makeService(): MollieOrderService
    {
        return new MollieOrderService(
            Mockery::mock(HttpResponse::class),
            $this->logger,
            $this->paymentFactory,
            Mockery::mock(Data::class),
            'mollie-payments-for-woocommerce',
            Mockery::mock(ContainerInterface::class),
            Mockery::mock(WebhookHandler::class)
        );
    }

    /**
     * Makes the factory resolve the transaction ID to a Mollie payment with the given redirectUrl.
     *
     * @param string|null $redirectUrl
     */
    private function givenPaymentWithRedirectUrl($redirectUrl): void
    {
        $payment = new \stdClass();
        $payment->id = self::TRANSACTION_ID;
        $payment->resource = 'payment';
        $payment->redirectUrl = $redirectUrl;

        $mollieObject = Mockery::mock(MollieObject::class);
        $mollieObject->shouldReceive('data')->andReturn(null);
        $mollieObject->shouldReceive('getPaymentObject')->andReturn($payment);

        $this->paymentFactory
            ->shouldReceive('getPaymentObject')
            ->with(self::TRANSACTION_ID)
            ->andReturn($mollieObject);
    }

    /**
     * @scenario getRedirectUrlFromPaymentObject() returns '' and throws no TypeError when the
     * resolved Mollie payment object has redirectUrl === null.
     *
     * Given a Mollie payment created without a redirect URL (redirectUrl is null)
     * When the webhook fallback asks for its redirect URL
     * Then an empty string is returned instead of a TypeError
     */
    public function testGetRedirectUrlFromPaymentObjectReturnsEmptyStringWhenRedirectUrlIsNull(): void
    {
        $this->logger->shouldReceive('debug')->andReturnNull();
        $this->givenPaymentWithRedirectUrl(null);

        $result = $this->makeService()->getRedirectUrlFromPaymentObject(self::TRANSACTION_ID);

        self::assertSame('', $result);
    }

    /**
     * @scenario getRedirectUrlFromPaymentObject() returns '' when the resolved Mollie payment
     * object has redirectUrl === '' (empty string).
     *
     * Given a Mollie payment whose redirectUrl is an empty string
     * When the webhook fallback asks for its redirect URL
     * Then an empty string is returned
     */
    public function testGetRedirectUrlFromPaymentObjectReturnsEmptyStringWhenRedirectUrlIsEmpty(): void
    {
        $this->logger->shouldReceive('debug')->andReturnNull();
        $this->givenPaymentWithRedirectUrl('');

        $result = $this->makeService()->getRedirectUrlFromPaymentObject(self::TRANSACTION_ID);

        self::assertSame('', $result);
    }

    /**
     * @scenario When redirectUrl is null or empty, getRedirectUrlFromPaymentObject() calls the
     * logger's debug() exactly once with a message containing the transaction ID.
     *
     * Given a Mollie payment with no usable redirect URL
     * When the webhook fallback asks for its redirect URL
     * Then exactly one debug entry naming the transaction ID is logged
     *
     * @dataProvider missingRedirectUrlProvider
     * @param string|null $redirectUrl
     */
    public function testGetRedirectUrlFromPaymentObjectLogsDebugWithTransactionIdWhenRedirectUrlMissing($redirectUrl): void
    {
        $this->logger
            ->shouldReceive('debug')
            ->once()
            ->withArgs(static function ($message): bool {
                return is_string($message) && strpos($message, self::TRANSACTION_ID) !== false;
            })
            ->andReturnNull();
        $this->givenPaymentWithRedirectUrl($redirectUrl);

        $this->makeService()->getRedirectUrlFromPaymentObject(self::TRANSACTION_ID);

        self::assertTrue(true);
    }

    public function missingRedirectUrlProvider(): array
    {
        return [
            'null redirectUrl' => [null],
            'empty redirectUrl' => [''],
        ];
    }

    /**
     * @scenario getRedirectUrlFromPaymentObject() returns the exact, non-empty redirect URL
     * string when the payment object has one.
     *
     * Given a Mollie payment carrying the shop's return URL
     * When the webhook fallback asks for its redirect URL
     * Then that exact URL is returned
     */
    public function testGetRedirectUrlFromPaymentObjectReturnsExactUrlWhenPresent(): void
    {
        $url = 'https://shop.test/?order_id=123&key=wc_order_abc';
        $this->logger->shouldReceive('debug')->andReturnNull();
        $this->givenPaymentWithRedirectUrl($url);

        $result = $this->makeService()->getRedirectUrlFromPaymentObject(self::TRANSACTION_ID);

        self::assertSame($url, $result);
    }
}

<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Integration\spec\webhooks;

use Mockery;
use Mollie\WooCommerce\Payment\Webhooks\RestApi;
use Mollie\WooCommerceTests\Integration\Common\ExpressFlowTestCase;
use Mollie\WooCommerceTests\Integration\Common\Traits\ExpressCheckoutFixtures;
use Mollie\WooCommerceTests\Integration\Common\Traits\WebhookOrderFixtures;

/**
 * A caller presenting a secret that is not the shop's, on the REST webhook route.
 *
 * The other refusals (no secret, no id) are in WebhookRestApiIntegrationTest and
 * WebhookConcurrentDeliveriesTest; a wrong secret is the case a forged URL produces.
 *
 * @group integration
 * @group Webhooks
 */
class WebhookWrongSecretTest extends ExpressFlowTestCase
{
    use ExpressCheckoutFixtures;
    use WebhookOrderFixtures;

    private const WRONG_SECRET = 'wrong-secret-00000000000000000000';

    public function setUp(): void
    {
        parent::setUp();

        $this->setUpExpressCheckout();
        $this->recordWebhookHooks();
        $this->boot();
    }

    public function tearDown(): void
    {
        $this->container = null;
        $this->tearDownExpressCheckout();
        Mockery::close();

        parent::tearDown();
    }

    /**
     * @scenario a webhook with a wrong secret and an id the shop does not know
     *   Given the shop has its webhook secret
     *   When a POST arrives with another secret and a transaction id no order has
     *   Then the answer is 401, nothing is asked of Mollie and no order changes
     *
     * @test
     * @dataProvider unknownIds
     * @group REQ-328
     * @group REQ-386
     * @covers \Mollie\WooCommerce\Payment\Webhooks\RestApi::registerRoutes
     */
    public function it_refuses_a_wrong_secret_without_asking_mollie(string $transactionId): void
    {
        // Given
        $requestsBefore = count($this->fakeMollie()->requests());

        // When
        $response = $this->restRequest(
            'POST',
            '/' . RestApi::ROUTE_NAMESPACE . '/' . RestApi::WEBHOOK_ROUTE,
            ['id' => $transactionId, 'mollie_webhook_secret' => self::WRONG_SECRET]
        );

        // Then
        $this->assertSame(401, $response->get_status());
        $this->assertCount($requestsBefore, $this->fakeMollie()->requests(), 'A refused caller costs no Mollie call.');
        $this->assertNothingLeakedToLog();
    }

    /**
     * @return array<string, array{0: string}>
     */
    public function unknownIds(): array
    {
        return [
            'a Mollie-shaped id no order has' => ['tr_madeUpAttempt00'],
            'an id that is not Mollie-shaped' => ['not-a-mollie-id'],
        ];
    }
}

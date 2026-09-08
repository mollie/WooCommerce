<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Functional\Shared;

use Mockery;
use Mollie\Api\Endpoints\CustomerEndpoint;
use Mollie\WooCommerce\Settings\Settings;
use Mollie\WooCommerceTests\Functional\HelperMocks;
use Mollie\WooCommerceTests\TestCase;

use function Brain\Monkey\Functions\when;

/**
 * @covers \Mollie\WooCommerce\Shared\Data::getUserMollieCustomerId
 */
class DataTest extends TestCase
{
    private HelperMocks $helperMocks;

    public function __construct($name = null, array $data = [], $dataName = '')
    {
        parent::__construct($name, $data, $dataName);
        $this->helperMocks = new HelperMocks();
    }

    private function makeUserData(): object
    {
        return (object) [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'display_name' => 'Jane Doe',
            'user_email' => 'jane@example.com',
        ];
    }

    private function dataHelperWithShouldStoreCustomer(bool $shouldStoreCustomer, $apiClientMock)
    {
        $settings = $this->createConfiguredMock(Settings::class, [
            'isTestModeEnabled' => true,
            'getApiKey' => 'test_SOME_API_KEY',
            'shouldStoreCustomer' => $shouldStoreCustomer,
        ]);

        return new \Mollie\WooCommerce\Shared\Data(
            $this->helperMocks->apiHelper($apiClientMock),
            $this->helperMocks->loggerMock(),
            $this->helperMocks->pluginId(),
            $settings,
            $this->helperMocks->pluginPath()
        );
    }

    // ──────────────────────────────────────────────────────────────────────
    // getUserMollieCustomerId() — creation gating driven by isMollieCustomerAllowedForOrder()
    // (see tests/php/Unit/Shared/DataTest.php for that policy's own tests)
    // ──────────────────────────────────────────────────────────────────────

    /**
     * @test
     * @scenario When a Mollie Customer isn't allowed for this order and the user has no
     *           stored/derivable Mollie customer ID, getUserMollieCustomerId() must not call
     *           the Mollie API's customers->create() endpoint and must return null.
     */
    public function doesNotCreateCustomerWhenNotAllowedForOrder(): void
    {
        when('wc_get_orders')->justReturn([]);

        $customerEndpointMock = Mockery::mock(CustomerEndpoint::class);
        $customerEndpointMock->shouldReceive('create')->never();

        $apiClientMock = $this->helperMocks->apiClient();
        $apiClientMock->customers = $customerEndpointMock;

        $dataHelper = $this->dataHelperWithShouldStoreCustomer(false, $apiClientMock);

        $result = $dataHelper->getUserMollieCustomerId(42, 'test_key', 999);

        $this->assertNull($result);
    }

    /**
     * @test
     * @scenario When a Mollie Customer is allowed for this order (setting enabled), a missing
     *           Mollie customer ID is created via the API.
     */
    public function createsCustomerWhenAllowedViaSetting(): void
    {
        when('wc_get_orders')->justReturn([]);
        when('get_userdata')->justReturn($this->makeUserData());

        $mollieCustomer = (object) ['id' => 'cst_new123'];

        $customerEndpointMock = Mockery::mock(CustomerEndpoint::class);
        $customerEndpointMock->shouldReceive('create')->once()->andReturn($mollieCustomer);

        $apiClientMock = $this->helperMocks->apiClient();
        $apiClientMock->customers = $customerEndpointMock;

        $dataHelper = $this->dataHelperWithShouldStoreCustomer(true, $apiClientMock);

        $result = $dataHelper->getUserMollieCustomerId(42, 'test_key', 999);

        $this->assertSame('cst_new123', $result);
    }

    /**
     * @test
     * @scenario A subscription order is allowed to create a Mollie Customer even when the
     *           "store customer details" setting is disabled.
     */
    public function createsCustomerWhenOrderIsSubscriptionEvenWhenSettingDisabled(): void
    {
        when('wc_get_orders')->justReturn([]);
        when('apply_filters')->justReturn(true); // simulates Data::isSubscription($orderId) === true
        when('get_userdata')->justReturn($this->makeUserData());

        $mollieCustomer = (object) ['id' => 'cst_new456'];

        $customerEndpointMock = Mockery::mock(CustomerEndpoint::class);
        $customerEndpointMock->shouldReceive('create')->once()->andReturn($mollieCustomer);

        $apiClientMock = $this->helperMocks->apiClient();
        $apiClientMock->customers = $customerEndpointMock;

        $dataHelper = $this->dataHelperWithShouldStoreCustomer(false, $apiClientMock);

        $result = $dataHelper->getUserMollieCustomerId(42, 'test_key', 999);

        $this->assertSame('cst_new456', $result);
    }

    /**
     * @test
     * @scenario An already-known Mollie customer ID (derived here from an active subscription
     *           order) is reused as-is, without ever calling customers->create(), even when a
     *           Mollie Customer would not otherwise be allowed for this order.
     */
    public function reusesExistingCustomerIdEvenWhenNotAllowedForOrder(): void
    {
        $subscriptionOrder = Mockery::mock('WC_Order');
        $subscriptionOrder->shouldReceive('get_id')->andReturn(555);

        when('wc_get_orders')->justReturn([$subscriptionOrder]);
        when('get_post_meta')->justReturn('cst_existing123');

        $customerEndpointMock = Mockery::mock(CustomerEndpoint::class);
        $customerEndpointMock->shouldReceive('get')
            ->with('cst_existing123')
            ->once()
            ->andReturn((object) ['id' => 'cst_existing123']);
        $customerEndpointMock->shouldReceive('create')->never();

        $apiClientMock = $this->helperMocks->apiClient();
        $apiClientMock->customers = $customerEndpointMock;

        $dataHelper = $this->dataHelperWithShouldStoreCustomer(false, $apiClientMock);

        $result = $dataHelper->getUserMollieCustomerId(42, 'test_key', 999);

        $this->assertSame('cst_existing123', $result);
    }
}

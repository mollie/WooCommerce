<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Unit\Shared;

use Mollie\WooCommerce\Settings\Settings;
use Mollie\WooCommerceTests\Functional\HelperMocks;
use Mollie\WooCommerceTests\TestCase;

use function Brain\Monkey\Functions\when;

/**
 * @covers \Mollie\WooCommerce\Shared\Data::isMollieCustomerAllowedForOrder
 */
class DataTest extends TestCase
{
    private HelperMocks $helperMocks;

    public function __construct($name = null, array $data = [], $dataName = '')
    {
        parent::__construct($name, $data, $dataName);
        $this->helperMocks = new HelperMocks();
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
    // isMollieCustomerAllowedForOrder() — the single source of truth
    // ──────────────────────────────────────────────────────────────────────

    /**
     * @test
     * @scenario When "store customer details" is disabled and the order is not
     *           subscription-related, a Mollie Customer is not allowed.
     */
    public function isFalseWhenSettingDisabledAndNotSubscription(): void
    {
        $dataHelper = $this->dataHelperWithShouldStoreCustomer(false, $this->helperMocks->apiClient());

        $this->assertFalse($dataHelper->isMollieCustomerAllowedForOrder(999));
    }

    /**
     * @test
     * @scenario When "store customer details" is enabled, a Mollie Customer is allowed
     *           regardless of subscription status.
     */
    public function isTrueWhenSettingEnabled(): void
    {
        $dataHelper = $this->dataHelperWithShouldStoreCustomer(true, $this->helperMocks->apiClient());

        $this->assertTrue($dataHelper->isMollieCustomerAllowedForOrder(999));
    }

    /**
     * @test
     * @scenario Even when "store customer details" is disabled, a subscription order is
     *           exempt — Mollie's recurring-payment/mandate model requires a Customer
     *           regardless of the setting.
     */
    public function isTrueForSubscriptionOrderEvenWhenSettingDisabled(): void
    {
        when('apply_filters')->justReturn(true); // simulates Data::isSubscription($orderId) === true

        $dataHelper = $this->dataHelperWithShouldStoreCustomer(false, $this->helperMocks->apiClient());

        $this->assertTrue($dataHelper->isMollieCustomerAllowedForOrder(999));
    }
}

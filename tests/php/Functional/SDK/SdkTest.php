<?php # -*- coding: utf-8 -*-

namespace Mollie\WooCommerceTests\Functional\SDK;

use Mollie\Api\Endpoints\CustomerEndpoint;
use Mollie\Api\Endpoints\PaymentEndpoint;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Resources\Customer;
use Mollie\Api\Resources\Mandate;
use Mollie\Api\Resources\MandateCollection;
use Mollie\Api\Resources\Payment;
use Mollie\WooCommerce\Gateway\MolliePaymentGateway;
use Mollie\WooCommerce\Payment\MollieObject;
use Mollie\WooCommerce\SDK\HttpResponse;
use Mollie\WooCommerce\SDK\WordPressHttpAdapter;
use Mollie\WooCommerce\Subscription\MollieSubscriptionGateway;
use Mollie\WooCommerceTests\Functional\HelperMocks;
use Mollie\WooCommerceTests\Stubs\WooCommerceMocks;
use Mollie\WooCommerceTests\TestCase;

use function Brain\Monkey\Functions\expect;


/**
 * Class Sdk_Test
 */
class SdkTest extends TestCase
{
    /** @var HelperMocks */
    private $helperMocks;
    /**
     * @var WooCommerceMocks
     */
    protected $wooCommerceMocks;

    public function __construct($name = null, array $data = [], $dataName = '')
    {
        parent::__construct($name, $data, $dataName);
        $this->helperMocks = new HelperMocks();
        $this->wooCommerceMocks = new WooCommerceMocks();
    }

    /**
     * WHEN the Mollie API returns an error
     * THEN the SDK should throw an ApiException
     * @test
     */
    public function sdkThrows()
    {
        $testee = new WordPressHttpAdapter();
        expect('wp_remote_request')->once()->andReturn(new \WP_Error());
        expect('is_wp_error')->once()->andReturn(true);

        $this->expectException(ApiException::class);
        $testee->send('POST', 'https://test.com', ['User-Agent'=>'test'], 'test');
    }
}




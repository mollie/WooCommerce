<?php # -*- coding: utf-8 -*-

namespace Mollie\WooCommerceTests\Functional\SDK;

use Mollie\Api\Exceptions\ApiException;
use Mollie\WooCommerce\SDK\WordPressHttpAdapter;
use Mollie\WooCommerceTests\Functional\HelperMocks;
use Mollie\WooCommerceTests\TestCase;

use function Brain\Monkey\Functions\expect;


/**
 * Class Sdk_Test
 */
class SdkTest extends TestCase
{
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




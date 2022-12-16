<?php

namespace Mollie\WooCommerceTests\Functional\PayPalButton;

use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mollie\WooCommerce\Buttons\PayPalButton\PayPalDataObjectHttp;
use Mollie\WooCommerceTests\Functional\HelperMocks;
use Mollie\WooCommerceTests\Stubs\postDTOTestsStubs;
use Mollie\WooCommerceTests\TestCase;
use Mollie_WC_PayPalButton_PayPalDataObjectHttp;

use function Brain\Monkey\Functions\when;
use function Brain\Monkey\Functions\expect;

class PayPalDataObjectTest extends TestCase
{
    use MockeryPHPUnitIntegration;
    /**
     *
     */
    public function testDataObjectSuccess()
    {
        /*
         * Stubs
         */
        $postDummyData = new postDTOTestsStubs();

        $_POST = [
            'nonce' => $postDummyData->nonce,
            'productId' => $postDummyData->productId,
            'productQuantity' => $postDummyData->productQuantity,
            'malicious'=>'not supposed to exist'
        ];

        /*
         * Sut
         */
        $logger = $this->helperMocks->loggerMock();
        $dataObject = new PayPalDataObjectHttp($logger);
        expect('wp_verify_nonce')
            ->once()
            ->andReturn(true);
        $dataObject->orderData('productDetail');

        self::assertEquals($_POST['nonce'], $dataObject->nonce());
        self::assertEquals($_POST['productId'], $dataObject->productId());
        self::assertEquals($_POST['productQuantity'], $dataObject->productQuantity());
        self::assertFalse(isset($dataObject->malicious));
    }



    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();

        when('__')->returnArg(1);
    }
}

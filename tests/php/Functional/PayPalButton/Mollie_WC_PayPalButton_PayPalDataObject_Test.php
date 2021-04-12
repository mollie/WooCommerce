<?php

namespace Mollie\WooCommerceTests\Functional\PayPalButton;

use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mollie\WooCommerceTests\Stubs\postDTOTestsStubs;
use Mollie\WooCommerceTests\TestCase;
use Mollie_WC_PayPalButton_PayPalDataObjectHttp;

use function Brain\Monkey\Functions\when;


class Mollie_WC_PayPalButton_PayPalDataObject_Test extends TestCase
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

        $postOrder = [
            'nonce' => $postDummyData->nonce,
            'productId' => $postDummyData->productId,
            'productQuantity' => $postDummyData->productQuantity,
        ];

        /*
         * Sut
         */
        $dataObject = new Mollie_WC_PayPalButton_PayPalDataObjectHttp();

        $dataObject->orderData($postOrder, 'productDetail');

        self::assertEquals($postOrder['nonce'], $dataObject->nonce);
        self::assertEquals($postOrder['productId'], $dataObject->productId);
        self::assertEquals($postOrder['productQuantity'], $dataObject->productQuantity);
    }



    /**
     * @inheritDoc
     */
    protected function setUp()
    {
        parent::setUp();

        when('__')->returnArg(1);
    }
}

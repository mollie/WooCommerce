<?php

namespace Mollie\WooCommerceTests\Functional\PayPalButton;

use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mollie\WooCommerce\Buttons\PayPalButton\PayPalDataObjectHttp;
use Mollie\WooCommerceTests\Functional\HelperMocks;
use Mollie\WooCommerceTests\Stubs\postDTOTestsStubs;
use Mollie\WooCommerceTests\TestCase;
use Mollie_WC_PayPalButton_PayPalDataObjectHttp;

use function Brain\Monkey\Functions\when;


class PayPalDataObjectTest extends TestCase
{
    use MockeryPHPUnitIntegration;
    /** @var HelperMocks */
    private $helperMocks;

    public function __construct($name = null, array $data = [], $dataName = '')
    {
        parent::__construct($name, $data, $dataName);
        $this->helperMocks = new HelperMocks();
    }
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
            'malicious'=>'not supposed to exist'
        ];

        /*
         * Sut
         */
        $logger = $this->helperMocks->loggerMock();
        $dataObject = new PayPalDataObjectHttp($logger);

        $dataObject->orderData($postOrder, 'productDetail');

        self::assertEquals($postOrder['nonce'], $dataObject->nonce);
        self::assertEquals($postOrder['productId'], $dataObject->productId);
        self::assertEquals($postOrder['productQuantity'], $dataObject->productQuantity);
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

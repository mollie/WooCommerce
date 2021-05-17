<?php

namespace Mollie\WooCommerceTests\Functional\PayPalButton;

use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mollie\Api\Endpoints\OrderEndpoint;
use Mollie\WooCommerceTests\Stubs\postDTOTestsStubs;
use Mollie\WooCommerceTests\TestCase;
use Mollie_WC_ApplePayButton_DataObjectHttp;
use Mollie_WC_Helper_Data;
use Mollie_WC_Payment_RefundLineItemsBuilder;
use Mollie_WC_PayPalButton_AjaxRequests;
use PHPUnit_Framework_Exception;
use PHPUnit_Framework_MockObject_MockObject;

use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\stubs;
use function Brain\Monkey\Functions\when;

class Mollie_WC_PayPalButton_AjaxRequests_Test extends TestCase
{
    use MockeryPHPUnitIntegration;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|Mollie_WC_Payment_RefundLineItemsBuilder
     */
    private $refundLineItemsBuilder;

    /**
     * @var Mollie_WC_Helper_Data
     */
    private $dataHelper;

    /**
     * @var OrderEndpoint
     */
    private $ordersApiClient;



    public function testcreateWcOrderSuccess()
    {
        /*
         * Stubs
         */
        $postDummyData = new postDTOTestsStubs();

        $_POST = [
            'nonce' => $postDummyData->nonce,
            'needShipping' => true,
            'productId' => $postDummyData->productId,
            'productQuantity' => $postDummyData->productQuantity,
        ];
        $order = $this->wcOrder();
        $orderId = $order->get_id();
        stubs(
            [
                'wc_create_order' => $order,
            ]
        );
        $dataObject = new \Mollie_WC_PayPalButton_PayPalDataObjectHttp();
        $dataObject->orderData($_POST, 'productDetail');


        /*
         * Sut
         */
        $testee = $this->buildTesteeMock(
            Mollie_WC_PayPalButton_AjaxRequests::class,
            [],
            [
                'updateOrderPostMeta',
                'processOrderPayment',
                'addShippingMethodsToOrder',
            ]
        )->getMock();

        /*
         * Expectations
         */
        expect('wp_verify_nonce')
            ->once()
            ->with($_POST['nonce'], 'mollie_PayPal_button')
            ->andReturn(true);
        expect('wc_get_product')
            ->once();

        expect('wp_send_json_success')
            ->once()->with(['result' => 'success']);

        $testee->expects($this->once())->method(
            'addShippingMethodsToOrder'
        )->with(
            $order
        )->willReturn($order);
        $testee->expects($this->once())->method(
            'updateOrderPostMeta'
        )->with($orderId, $order);
        $testee->expects($this->once())->method(
            'processOrderPayment'
        )->with($orderId)->willReturn(['result' => 'success']);


        $order->expects($this->once())->method('payment_complete');


        /*
         * Execute Test
         */
        $testee->createWcOrder();
    }


    /**
     *
     * @return PHPUnit_Framework_MockObject_MockObject
     * @throws PHPUnit_Framework_Exception
     */
    private function wcOrder()
    {
        $item = $this->createConfiguredMock(
            'WC_Order',
            [
                'get_id' => 11,


            ]
        );

        return $item;
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

<?php

namespace Mollie\WooCommerceTests\Functional\PayPalButton;

use AjaxRequests;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mollie\Api\Endpoints\OrderEndpoint;
use Mollie\WooCommerce\Buttons\PayPalButton\PayPalAjaxRequests;
use Mollie\WooCommerce\Buttons\PayPalButton\PayPalDataObjectHttp;
use Mollie\WooCommerce\Gateway\Surcharge;
use Mollie\WooCommerce\Subscription\MollieSubscriptionGateway;
use Mollie\WooCommerceTests\Functional\HelperMocks;
use Mollie\WooCommerceTests\Stubs\postDTOTestsStubs;
use Mollie\WooCommerceTests\TestCase;
use Mollie_WC_ApplePayButton_DataObjectHttp;
use Mollie_WC_Helper_Data;
use Mollie_WC_Payment_RefundLineItemsBuilder;
use PHPUnit_Framework_Exception;
use PHPUnit_Framework_MockObject_MockObject;

use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\stubs;
use function Brain\Monkey\Functions\when;

class AjaxRequestsTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|RefundLineItemsBuilder
     */
    private $refundLineItemsBuilder;

    /**
     * @var Data
     */
    private $dataHelper;

    /**
     * @var OrderEndpoint
     */
    private $ordersApiClient;
    /** @var HelperMocks */
    private $helperMocks;

    public function __construct($name = null, array $data = [], $dataName = '')
    {
        parent::__construct($name, $data, $dataName);
        $this->helperMocks = new HelperMocks();
    }


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
        $paymentSurcharge = Surcharge::NO_FEE;
        $fixedFee = 10.00;
        $percentage = 0;
        $feeLimit = 1;
        stubs(
            [
                'wc_create_order' => $order,
            ]
        );
        $logger = $this->helperMocks->loggerMock();
        $paypalGateway = $this->mollieGateway('paypal', false, true);

        $dataObject = new PayPalDataObjectHttp($logger);
        $dataObject->orderData($_POST, 'productDetail');


        /*
         * Sut
         */
        $testee = $this->buildTesteeMock(
            PayPalAjaxRequests::class,
            [
                $paypalGateway,
                $this->helperMocks->noticeMock(),
                $logger
            ],
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
        expect('get_option')
            ->with('mollie-payments-for-woocommerce_gatewayFeeLabel')
            ->andReturn(
                $this->helperMocks->paymentMethodSettings(
                    [
                        'payment_surcharge' => $paymentSurcharge,
                        'surcharge_limit' => $feeLimit,
                        'fixed_fee' => $fixedFee,
                        'percentage' => $percentage,
                    ]
                )
            );
        expect('wp_send_json_success')
            ->once()->with(['result' => 'success']);
        $testee->expects($this->once())->method(
            'updateOrderPostMeta'
        )->with($orderId, $order);
        $testee->expects($this->once())->method(
            'processOrderPayment'
        )->with($orderId)->willReturn(['result' => 'success']);

        /*
         * Execute Test
         */
        $testee->createWcOrder();
    }

    public function mollieGateway($paymentMethodName, $isSepa = false, $isSubscription = false){
        $gateway = $this->createConfiguredMock(
            MollieSubscriptionGateway::class,
            [
            ]
        );
        $gateway->paymentMethod = $this->helperMocks->paymentMethodBuilder($paymentMethodName, $isSepa, $isSubscription);

        return $gateway;
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
    protected function setUp(): void
    {
        parent::setUp();

        when('__')->returnArg(1);
    }
}

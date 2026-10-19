<?php

namespace Mollie\WooCommerceTests\Functional\PayPalButton;

use AjaxRequests;
use Mollie\Api\Endpoints\OrderEndpoint;
use Mollie\WooCommerce\Buttons\PayPalButton\PayPalAjaxRequests;
use Mollie\WooCommerce\Buttons\PayPalButton\PayPalDataObjectHttp;
use Mollie\WooCommerce\Gateway\Surcharge;
use Mollie\WooCommerce\Subscription\MollieSubscriptionGatewayHandler;
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
        $paypalGateway = $this->helperMocks->genericPaymentGatewayMock();
        expect('wp_verify_nonce')
            ->andReturn(true);
        $dataObject = new PayPalDataObjectHttp($logger);
        $dataObject->orderData('productDetail');


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
                'isNonceValid',
                'purchasableProduct',
            ]
        )->getMock();

        /*
         * Expectations
         */

        $testee->expects($this->once())
            ->method('purchasableProduct')
            ->willReturn($this->productMock());
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
        expect('wp_verify_nonce')
            ->with($_POST['nonce'], 'woocommerce-process_checkout')
            ->andReturn(true);
        $testee->expects($this->once())->method(
            'isNonceValid'
        )->willReturn(
            true
        );
        /*
         * Execute Test
         */
        $testee->createWcOrder();
    }

    /**
     * GIVEN a valid PayPal button request
     * WHEN the requested product cannot be purchased
     * THEN no order is created and an error is returned
     */
    public function testcreateWcOrderRejectsNonPurchasableProduct()
    {
        $_POST = [
            'nonce' => 'nonce',
            'productId' => '42',
            'productQuantity' => '1',
        ];
        $logger = $this->helperMocks->loggerMock();
        $testee = $this->buildTesteeMock(
            PayPalAjaxRequests::class,
            [
                $this->helperMocks->genericPaymentGatewayMock(),
                $this->helperMocks->noticeMock(),
                $logger,
            ],
            ['updateOrderPostMeta', 'processOrderPayment', 'isNonceValid', 'purchasableProduct']
        )->getMock();
        expect('wp_verify_nonce')->andReturn(true);
        $testee->method('isNonceValid')->willReturn(true);
        $testee->method('purchasableProduct')->willReturn(null);
        expect('wc_create_order')->never();
        $testee->expects($this->never())->method('processOrderPayment');
        $this->expectJsonErrorEndsRequest();
        expect('wp_send_json_success')->never();

        $testee->createWcOrder();
    }

    /**
     * GIVEN a valid PayPal button request
     * WHEN the requested product cannot be purchased
     * THEN updateAmount returns an error instead of the product price
     */
    public function testupdateAmountRejectsNonPurchasableProduct()
    {
        $_POST = [
            'nonce' => 'nonce',
            'productId' => '42',
            'productQuantity' => '1',
        ];
        $testee = $this->buildTesteeMock(
            PayPalAjaxRequests::class,
            [
                $this->helperMocks->genericPaymentGatewayMock(),
                $this->helperMocks->noticeMock(),
                $this->helperMocks->loggerMock(),
            ],
            ['isNonceValid', 'purchasableProduct']
        )->getMock();
        expect('wp_verify_nonce')->andReturn(true);
        $testee->method('isNonceValid')->willReturn(true);
        $testee->method('purchasableProduct')->willReturn(null);
        $this->expectJsonErrorEndsRequest('product not purchasable');
        expect('wp_send_json_success')->never();

        $testee->updateAmount();
    }

    /**
     * GIVEN a product ID and quantity sent to the PayPal button endpoints
     * WHEN the product or quantity would be refused by the WooCommerce cart
     * THEN purchasableProduct returns null; otherwise it returns the product
     *
     * @dataProvider purchasabilityProvider
     */
    public function testpurchasableProductAppliesCartRules(
        array $productState,
        $quantity,
        bool $dataHasErrors,
        bool $filterPasses,
        bool $expectProduct
    ) {
        $product = $this->productMock($productState);
        when('wc_get_product')->justReturn($product);
        when('absint')->alias(static function ($value) {
            return abs((int) $value);
        });
        $filterArgs = [];
        when('apply_filters')->alias(static function (...$args) use ($filterPasses, &$filterArgs) {
            $filterArgs = $args;
            return $filterPasses;
        });

        $data = $this->createConfiguredMock(PayPalDataObjectHttp::class, [
            'hasErrors' => $dataHasErrors,
            'productId' => '42',
            'productQuantity' => $quantity,
        ]);
        $testee = $this->buildTesteeMock(
            PayPalAjaxRequests::class,
            [
                $this->helperMocks->genericPaymentGatewayMock(),
                $this->helperMocks->noticeMock(),
                $this->helperMocks->loggerMock(),
            ],
            []
        )->getMock();

        $method = new \ReflectionMethod($testee, 'purchasableProduct');
        if (PHP_VERSION_ID < 80100) {
            $method->setAccessible(true);
        }
        $result = $method->invoke($testee, $data);

        $expectProduct ? $this->assertSame($product, $result) : $this->assertNull($result);
        if ($expectProduct && ($productState['type'] ?? '') === 'variation') {
            $this->assertSame(
                ['woocommerce_add_to_cart_validation', true, 7, (int) $quantity, 42, []],
                $filterArgs,
                'A variation is validated as its parent product plus the variation, as the cart does'
            );
        }
    }

    public function purchasabilityProvider(): array
    {
        return [
            'published simple product' => [[], '2', false, true, true],
            'unpublished product' => [['is_purchasable' => false], '1', false, true, false],
            'out of stock' => [['is_in_stock' => false], '1', false, true, false],
            'not enough stock' => [['has_enough_stock' => false], '5', false, true, false],
            'variable parent' => [['type' => 'variable'], '1', false, true, false],
            'sold individually, quantity 2' => [['is_sold_individually' => true], '2', false, true, false],
            'sold individually, quantity 1' => [['is_sold_individually' => true], '1', false, true, true],
            'zero quantity' => [[], '0', false, true, false],
            'negative quantity' => [[], '-3', false, true, false],
            'rejected by add-to-cart validation' => [[], '1', false, false, false],
            'invalid request data' => [[], '1', true, true, false],
            'physical product' => [['is_virtual' => false], '1', false, true, false],
            'subscription' => [['type' => 'subscription'], '1', false, true, false],
            'subscription variation' => [['type' => 'subscription_variation'], '1', false, true, false],
            'virtual variation' => [['type' => 'variation'], '1', false, true, true],
        ];
    }

    /**
     * GIVEN the cart PayPal button endpoint is called
     * WHEN the cart is empty or holds a product that needs shipping
     * THEN no order is created and an error is returned
     *
     * @dataProvider cartProvider
     */
    public function testcreateWcOrderFromCartRefusesCartsTheButtonIsNotShownFor(array $virtualFlags)
    {
        $_POST = ['nonce' => 'nonce'];
        $this->mockCart($virtualFlags);
        $testee = $this->buildTesteeMock(
            PayPalAjaxRequests::class,
            [
                $this->helperMocks->genericPaymentGatewayMock(),
                $this->helperMocks->noticeMock(),
                $this->helperMocks->loggerMock(),
            ],
            ['isNonceValid', 'createOrderFromCart', 'processOrderPayment']
        )->getMock();
        $testee->method('isNonceValid')->willReturn(true);
        $testee->expects($this->never())->method('createOrderFromCart');
        $testee->expects($this->never())->method('processOrderPayment');
        $this->expectJsonErrorEndsRequest();

        $testee->createWcOrderFromCart();
    }

    public function cartProvider(): array
    {
        return [
            'empty cart' => [[]],
            'physical product' => [[false]],
            'virtual and physical products' => [[true, false]],
        ];
    }

    /**
     * GIVEN a cart with only virtual products
     * WHEN the cart PayPal button rules are checked
     * THEN the cart qualifies
     */
    public function testcartWithOnlyVirtualProductsQualifies()
    {
        $this->mockCart([true, true]);
        $testee = $this->buildTesteeMock(
            PayPalAjaxRequests::class,
            [
                $this->helperMocks->genericPaymentGatewayMock(),
                $this->helperMocks->noticeMock(),
                $this->helperMocks->loggerMock(),
            ],
            []
        )->getMock();

        $method = new \ReflectionMethod($testee, 'cartHasOnlyVirtualProducts');
        if (PHP_VERSION_ID < 80100) {
            $method->setAccessible(true);
        }

        $this->assertTrue($method->invoke($testee));
    }

    /**
     * wp_send_json_error() ends the request in production
     */
    private function expectJsonErrorEndsRequest(?string $message = null): void
    {
        $expectation = expect('wp_send_json_error')->once();
        if ($message !== null) {
            $expectation->with($message);
        }
        $expectation->andThrow(new \RuntimeException('wp_send_json_error'));
        $this->expectExceptionMessage('wp_send_json_error');
    }

    private function mockCart(array $virtualFlags): void
    {
        $contents = [];
        foreach ($virtualFlags as $index => $isVirtual) {
            $contents['item' . $index] = ['data' => $this->productMock(['is_virtual' => $isVirtual])];
        }
        $cart = $this->createConfiguredMock(\WC_Cart::class, [
            'is_empty' => $contents === [],
            'get_cart_contents' => $contents,
        ]);
        when('WC')->justReturn((object) ['cart' => $cart]);
    }

    private function productMock(array $state = [])
    {
        $type = $state['type'] ?? 'simple';
        $product = $this->getMockBuilder(\WC_Product::class)
            ->setMethods([
                'is_type',
                'is_purchasable',
                'is_in_stock',
                'has_enough_stock',
                'is_sold_individually',
                'is_virtual',
                'get_parent_id',
            ])
            ->getMock();
        $product->method('is_type')->willReturnCallback(static function ($checked) use ($type) {
            return in_array($type, (array) $checked, true);
        });
        $product->method('is_virtual')->willReturn($state['is_virtual'] ?? true);
        $product->method('is_purchasable')->willReturn($state['is_purchasable'] ?? true);
        $product->method('is_in_stock')->willReturn($state['is_in_stock'] ?? true);
        $product->method('has_enough_stock')->willReturn($state['has_enough_stock'] ?? true);
        $product->method('is_sold_individually')->willReturn($state['is_sold_individually'] ?? false);
        $product->method('get_parent_id')->willReturn($type === 'variation' ? 7 : 0);

        return $product;
    }

    public function mollieGateway($paymentMethodName, $isSepa = false, $isSubscription = false){
        return $this->helperMocks->mollieGatewayBuilder($paymentMethodName, $isSepa, $isSubscription, []);
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

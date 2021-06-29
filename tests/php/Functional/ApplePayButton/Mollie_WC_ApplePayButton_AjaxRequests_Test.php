<?php

namespace Mollie\WooCommerceTests\Functional\Helper;

use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mollie\Api\Endpoints\OrderEndpoint;
use Mollie\Api\Endpoints\WalletEndpoint;
use Mollie\Api\MollieApiClient;
use Mollie\WooCommerceTests\Stubs\postDTOTestsStubs;
use Mollie\WooCommerceTests\TestCase;
use Mollie_WC_ApplePayButton_AjaxRequests;
use Mollie_WC_ApplePayButton_ApplePayDataObjectHttp;
use Mollie_WC_ApplePayButton_ResponsesToApple;
use Mollie\WooCommerce\SDK\Api;
use Mollie_WC_ApplePayButton_DataObjectHttp;
use Mollie_WC_Helper_ApplePayDirectHandler;
use Mollie\WooCommerce\Utils\Data;
use Mollie_WC_Payment_RefundLineItemsBuilder;
use PHPUnit_Framework_Exception;
use PHPUnit_Framework_MockObject_MockObject;
use WC_Countries;

use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\stubs;
use function Brain\Monkey\Functions\when;

class Mollie_WC_ApplePayButton_AjaxRequests_Test extends TestCase
{
    use MockeryPHPUnitIntegration;

    /**
     * @var PHPUnit_Framework_MockObject_MockObject|Mollie_WC_Payment_RefundLineItemsBuilder
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

    public function testValidateMerchant()
    {
        /*
         * Stubs
         */
        $postDummyData = new postDTOTestsStubs();
        $_POST = [
            'validationUrl' => $postDummyData->validationUrl,
            'nonce' => $postDummyData->nonce

        ];
        $responseFromMollie = ["response from MollieSettingsPage"];
        stubs(
            [
                'get_site_url' => 'http://www.testdomain.com',

            ]
        );
        $responsesTemplate = new \Mollie_WC_ApplePayButton_ResponsesToApple();


        /*
         * Sut
         */
        $testee = $this->buildTesteeMock(
            Mollie_WC_ApplePayButton_AjaxRequests::class,
            [$responsesTemplate],
            ['validationApiWalletsEndpointCall']
        )->getMock();


        /*
         * Expectations
         */
        expect('wp_verify_nonce')
            ->once()
            ->with($_POST['nonce'], 'mollie_applepay_button')
            ->andReturn(true);
        $testee->expects($this->once())->method(
            'validationApiWalletsEndpointCall'
        )->with('www.testdomain.com', $_POST['validationUrl'])->willReturn(
            $responseFromMollie
        );

        expect('update_option')
            ->once()
            ->with('mollie_wc_applepay_validated', 'yes');
        expect('wp_send_json_success')
            ->once()
            ->with($responseFromMollie);


        /*
         * Execute Test
         */
        $testee->validateMerchant();
    }

    public function testUpdateShippingContactSuccess()
    {
        /*
         * Stubs
         */
        $postDummyData = new postDTOTestsStubs();
        $expected = [
            'newLineItems' => [
                [
                    'label' => "Subtotal",
                    'amount' => "1.00",
                    'type' => "final"
                ],
                [
                    'label' => "Flat1",
                    'amount' => "1.00",
                    'type' => "final"
                ],
                [
                    'label' => "Estimated Tax",
                    'amount' => '0.20',
                    'type' => "final"
                ]
            ],

            'newShippingMethods' => [
                [
                    'label' => "Flat1",
                    'detail' => "",
                    'amount' => "1.00",
                    'identifier' => "flat_rate:1"
                ],
                [
                    'label' => "Flat4",
                    'detail' => "",
                    'amount' => "4.00",
                    'identifier' => "flat_rate:4"
                ]
            ],

            'newTotal' => [
                'label' => "Blog Name",
                'amount' => "2.20",
                'type' => "final"
            ]
        ];
        $_POST = [
            'callerPage' => 'productDetail',
            'nonce' => $postDummyData->nonce,
            'simplifiedContact' => [
                'locality' => 'locality',
                'postalCode' => 'postalCode',
                'countryCode' => 'IT'
            ],
            'needShipping' => $postDummyData->needShipping,
            'productId' => $postDummyData->productId,
            'productQuantity' => $postDummyData->productQuantity

        ];
        stubs(
            [
                'WC' => $this->wooCommerce('1.00', '1.00', '2.20', '0.20'),
                'wc_get_base_location' => ['country' => 'IT'],
                'get_bloginfo' => 'Blog Name'

            ]
        );
        $responsesTemplate = new \Mollie_WC_ApplePayButton_ResponsesToApple();

        /*
         * Sut
         */
        $testee = $this->buildTesteeMock(
            Mollie_WC_ApplePayButton_AjaxRequests::class,
            [$responsesTemplate],
            ['createWCCountries', 'getShippingPackages']
        )->getMock();

        /*
         * Expectations
         */
        expect('wp_verify_nonce')
            ->once()
            ->with($_POST['nonce'], 'mollie_applepay_button')
            ->andReturn(true);
        $testee->expects($this->once())
            ->method('createWCCountries')
            ->willReturn($this->wcCountries());
        $testee->expects($this->once())
            ->method('getShippingPackages')
            ->willReturn([]);

        expect('wp_send_json_success')
            ->once()
            ->with($expected);


        /*
         * Execute Test
         */
        $testee->updateShippingContact();
    }

    /**
     *
     * @return PHPUnit_Framework_MockObject_MockObject
     * @throws PHPUnit_Framework_Exception
     */
    private function wcCountries()
    {
        $item = $this->createConfiguredMock(
            WC_Countries::class,
            [
                'get_allowed_countries' => ['IT' => 'Italy'],
                'get_shipping_countries' => ['IT' => 'Italy'],
            ]
        );

        return $item;
    }

    public function testUpdateShippingContactError()
    {
        /*
         * Stubs
         */
        $postDummyData = new postDTOTestsStubs();
        $expected = [
            'errors' => [
                [
                    "code" => 'addressUnserviceable',
                    "contactField" => null,
                    "message" => "",
                ]
            ],
            'newTotal' => [
                'label' => "Blog Name",
                'amount' => "0",
                'type' => "pending"
            ]
        ];
        $_POST = [
            'callerPage' => 'productDetail',
            'nonce' => $postDummyData->nonce,
            'simplifiedContact' => [
                'locality' => 'locality',
                'postalCode' => 'postalCode',
                'countryCode' => 'ES'
            ],
            'needShipping' => $postDummyData->needShipping,
            'productId' => $postDummyData->productId,
            'productQuantity' => $postDummyData->productQuantity

        ];
        stubs(
            [
                'WC' => $this->wooCommerce('1.00', '1.00', '2.20', '0.20'),
                'wc_get_base_location' => ['country' => 'IT'],
                'get_bloginfo' => 'Blog Name'

            ]
        );
        $responsesTemplate = new \Mollie_WC_ApplePayButton_ResponsesToApple();

        /*
         * Sut
         */
        $testee = $this->buildTesteeMock(
            Mollie_WC_ApplePayButton_AjaxRequests::class,
            [$responsesTemplate],
            ['createWCCountries', 'getShippingPackages']
        )->getMock();

        /*
         * Expectations
         */
        expect('wp_verify_nonce')
            ->once()
            ->with($_POST['nonce'], 'mollie_applepay_button')
            ->andReturn(true);
        $testee->expects($this->once())
            ->method('createWCCountries')
            ->willReturn($this->wcCountries());
        $testee->expects($this->never())
            ->method('getShippingPackages');

        expect('wp_send_json_error')
            ->once()
            ->with($expected);

        /*
         * Execute Test
         */
        $testee->updateShippingContact();
    }

    public function testUpdateShippingContactErrorMissingData()
    {
        /*
         * Stubs
         */
        $postDummyData = new postDTOTestsStubs();
        $expected = [
            'errors' => [
                [
                    "code" => 'shipping Contact Invalid',
                    "contactField" => 'postalCode',
                    "message" => "Missing postalCode",
                ],
                [
                    "code" => 'shipping Contact Invalid',
                    "contactField" => 'countryCode',
                    "message" => "Missing countryCode",
                ],
            ],
            'newTotal' => [
                'label' => "Blog Name",
                'amount' => "0",
                'type' => "pending"
            ]
        ];
        $_POST = [
            'callerPage' => 'productDetail',
            'nonce' => $postDummyData->nonce,
            'simplifiedContact' => [
                'locality' => 'locality',
                'postalCode' => '',
                'countryCode' => ''
            ],
            'needShipping' => $postDummyData->needShipping,
            'productId' => $postDummyData->productId,
            'productQuantity' => $postDummyData->productQuantity

        ];
        stubs(
            [
                'wc_get_base_location' => ['country' => 'IT'],
                'get_bloginfo' => 'Blog Name'

            ]
        );
        $responsesTemplate = new \Mollie_WC_ApplePayButton_ResponsesToApple();


        /*
         * Sut
         */
        $testee = $this->buildTesteeMock(
            Mollie_WC_ApplePayButton_AjaxRequests::class,
            [$responsesTemplate],
            ['createWCCountries']
        )->getMock();


        /*
         * Expectations
         */
        expect('wp_verify_nonce')
            ->once()
            ->with($_POST['nonce'], 'mollie_applepay_button')
            ->andReturn(true);
        expect('mollieWooCommerceDebug')
            ->once()
            ->with("ApplePay Data Error: Missing value for countryCode");
        $testee->expects($this->never())
            ->method('createWCCountries')
            ->willReturn($this->wcCountries());
        expect('wp_send_json_error')
            ->once()
            ->with($expected);

        /*
         * Execute Test
         */
        $testee->updateShippingContact();
    }

    public function testUpdateShippingMethod()
    {
        /*
         * Stubs
         */
        $postDummyData = new postDTOTestsStubs();
        $expected = [
            'newLineItems' => [
                [
                    'label' => "Subtotal",
                    'amount' => "1.00",
                    'type' => "final"
                ],
                [
                    'label' => "Flat4",
                    'amount' => "4.00",
                    'type' => "final"
                ],
                [
                    'label' => "Estimated Tax",
                    'amount' => '0.20',
                    'type' => "final"
                ]
            ],

            'newShippingMethods' => [
                [
                    'label' => "Flat1",
                    'detail' => "",
                    'amount' => "1.00",
                    'identifier' => "flat_rate:1"
                ],
                [
                    'label' => "Flat4",
                    'detail' => "",
                    'amount' => "4.00",
                    'identifier' => "flat_rate:4"
                ]
            ],

            'newTotal' => [
                'label' => "Blog Name",
                'amount' => "5.20",
                'type' => "final"
            ]
        ];
        $_POST = [
            'callerPage' => 'productDetail',
            'nonce' => $postDummyData->nonce,
            'simplifiedContact' => [
                'locality' => 'locality',
                'postalCode' => 'postalCode',
                'countryCode' => 'IT'
            ],
            'productId' => $postDummyData->productId,
            'productQuantity' => $postDummyData->productQuantity,
            'shippingMethod' => [
                'label' => "Flat4",
                'detail' => "",
                'amount' => "4.00",
                'identifier' => "flat_rate:4"
            ]

        ];
        stubs(
            [
                'WC' => $this->wooCommerce('1.00', '4.00', '5.20', '0.20'),
                'wc_get_base_location' => ['country' => 'IT'],
                'get_bloginfo' => 'Blog Name'

            ]
        );
        $responsesTemplate = new \Mollie_WC_ApplePayButton_ResponsesToApple();


        /*
         * Sut
         */
        $testee = $this->buildTesteeMock(
            Mollie_WC_ApplePayButton_AjaxRequests::class,
            [$responsesTemplate],
            [ 'getShippingPackages']
        )->getMock();

        /*
         * Stubbing
         */

        /*
         * Expectations
         */
        expect('wp_verify_nonce')
            ->once()
            ->with($_POST['nonce'], 'mollie_applepay_button')
            ->andReturn(true);

        $testee->expects($this->once())
            ->method('getShippingPackages')
            ->willReturn([]);

        expect('wp_send_json_success')
            ->once()
            ->with($expected);


        /*
         * Execute Test
         */
        $testee->updateShippingMethod();
    }

    public function testcreateWcOrderSuccess()
    {
        /*
         * Stubs
         */
        $postDummyData = new postDTOTestsStubs();
        $applePayPayment = [
            "token" => '',
            "billingContact" => $postDummyData->billingContact,
            "shippingContact" => $postDummyData->shippingContact
        ];

        $_POST = [
            'nonce' => $postDummyData->nonce,
            'shippingContact' => $applePayPayment['shippingContact'],
            'billingContact' => $applePayPayment['billingContact'],
            'needShipping' => $postDummyData->needShipping,
            'productId' => $postDummyData->productId,
            'productQuantity' => $postDummyData->productQuantity,
            'shippingMethod' => $postDummyData->shippingMethod
        ];
        $order = $this->wcOrder();
        $orderId = $order->get_id();
        stubs(
            [
                'wc_create_order' => $order,
            ]
        );
        $dataObject = new Mollie_WC_ApplePayButton_ApplePayDataObjectHttp();
        $dataObject->orderData($_POST, 'productDetail');
        $authorizationResultResponse = [
            'returnUrl' => 'returnUrl',
            'responseToApple' => ['status' => 0]
        ];
        $responsesTemplate = $this->createPartialMock(
            Mollie_WC_ApplePayButton_ResponsesToApple::class,
            ['redirectUrlOnSuccessfulPayment', 'responseSuccess']
        );


        /*
         * Sut
         */
        $testee = $this->buildTesteeMock(
            Mollie_WC_ApplePayButton_AjaxRequests::class,
            [$responsesTemplate],
            [
                'updateOrderPostMeta',
                'processOrderPayment',
                'addAddressesToOrder',
                'addShippingMethodsToOrder',
            ]
        )->getMock();

        /*
         * Expectations
         */
        expect('wp_verify_nonce')
            ->once()
            ->with($_POST['nonce'], 'mollie_applepay_button')
            ->andReturn(true);
        expect('wc_get_product')
            ->once();


        $testee->expects($this->once())->method(
            'addAddressesToOrder'
        )->with($dataObject, $order)->willReturn($order);
        $testee->expects($this->once())->method(
            'addShippingMethodsToOrder'
        )->with(
            $dataObject->shippingMethod,
            $dataObject->shippingAddress,
            $order
        )->willReturn($order);
        $testee->expects($this->once())->method(
            'updateOrderPostMeta'
        )->with($orderId, $order);
        $testee->expects($this->once())->method(
            'processOrderPayment'
        )->with($orderId)->willReturn(['result' => 'success']);
        $responsesTemplate->expects($this->once())->method(
            'redirectUrlOnSuccessfulPayment'
        )->with($orderId)->willReturn('returnUrl');

        $responsesTemplate->expects($this->once())->method(
            'responseSuccess'
        )->with($authorizationResultResponse);

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
    private function wooCommerce(
        $subtotal = 0,
        $shippingTotal = 0,
        $total = 0,
        $tax = 0
    ) {
        $item = $this->createConfiguredMock(
            'WooCommerce',
            [

            ]
        );
        $item->cart = $this->wcCart($subtotal, $shippingTotal, $total, $tax);
        $item->customer = $this->wcCustomer();
        $item->shipping = $this->wcShipping();
        $item->session = $this->wcSession();

        return $item;
    }

    /**
     *
     * @return PHPUnit_Framework_MockObject_MockObject
     * @throws PHPUnit_Framework_Exception
     */
    private function wcCart($subtotal, $shippingTotal, $total, $tax)
    {
        $item = $this->createConfiguredMock(
            'WC_Cart',
            [
                'needs_shipping' => true,
                'get_subtotal' => $subtotal,
                'is_empty' => true,
                'get_shipping_total' => $shippingTotal,
                'add_to_cart' => '88888',
                'get_total_tax' => $tax,
                'get_total' => $total,
                'calculate_shipping' => null

            ]
        );

        return $item;
    }

    /**
     *
     * @return PHPUnit_Framework_MockObject_MockObject
     * @throws PHPUnit_Framework_Exception
     */
    private function wcCustomer()
    {
        $item = $this->createConfiguredMock(
            'WC_Customer',
            [
                'get_shipping_country' => 'IT'

            ]
        );

        return $item;
    }

    /**
     *
     * @return PHPUnit_Framework_MockObject_MockObject
     * @throws PHPUnit_Framework_Exception
     */
    private function wcShipping()
    {
        $item = $this->createConfiguredMock(
            'WC_Shipping',
            [
                'calculate_shipping' => [
                    0 => [
                        'rates' => [
                            $this->wcShippingRate(
                                'flat_rate:1',
                                'Flat1',
                                '1.00'
                            ),
                            $this->wcShippingRate(
                                'flat_rate:4',
                                'Flat4',
                                '4.00'
                            )
                        ]
                    ]
                ]
            ]
        );

        return $item;
    }

    /**
     *
     * @return PHPUnit_Framework_MockObject_MockObject
     * @throws PHPUnit_Framework_Exception
     */
    private function wcShippingRate($id, $label, $cost)
    {
        $item = $this->createConfiguredMock(
            'WC_Shipping_Rate',
            [
                'get_id' => $id,
                'get_label' => $label,
                'get_cost' => $cost

            ]
        );

        return $item;
    }

    /**
     *
     * @return PHPUnit_Framework_MockObject_MockObject
     * @throws PHPUnit_Framework_Exception
     */
    private function wcSession()
    {
        $item = $this->createConfiguredMock(
            'WC_Session',
            [
                'set' => null

            ]
        );

        return $item;
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

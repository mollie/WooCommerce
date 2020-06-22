<?php

namespace Mollie\WooCommerceTests\Functional\Helper;

use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mollie\Api\Endpoints\OrderEndpoint;
use Mollie\Api\Endpoints\WalletEndpoint;
use Mollie\Api\MollieApiClient;
use Mollie\WooCommerceTests\Stubs\postDTOTestsStubs;
use Mollie\WooCommerceTests\TestCase;
use Mollie_WC_Helper_Api;
use Mollie_WC_Helper_ApplePayDataObjectHttp;
use Mollie_WC_Helper_ApplePayDirectHandler;
use Mollie_WC_Helper_Data;
use Mollie_WC_Payment_RefundLineItemsBuilder;
use PHPUnit_Framework_Exception;
use PHPUnit_Framework_MockObject_MockObject;
use WC_Countries;

use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\stubs;
use function Brain\Monkey\Functions\when;

class Mollie_WC_Helper_ApplePayDirectHandler_Test extends TestCase
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

    /**
     *
     */
    public function testApplePayScriptDataOnProduct()
    {
        /*
         * Stubs
         */
        $postDummyData = new postDTOTestsStubs();
        $expected = [
            'product' => [
                'needShipping' => $postDummyData->needShipping,
                'id' => $postDummyData->productId,
                'price' => '1',
                'isVariation' => false,
            ],
            'shop' => [
                'countryCode' => 'IT',
                'currencyCode' => 'EUR',
                'totalLabel' => 'test'
            ],
            'ajaxUrl' => 'admin-ajax.php'
        ];
        stubs(
            [
                'wc_shipping_enabled' => true,
                'wc_get_shipping_method_count' => 1,
                'wc_get_base_location' => ['country' => 'IT'],
                'get_woocommerce_currency' => 'EUR',
                'get_bloginfo' => 'test',
                'is_product' => true,
                'get_the_id' => $postDummyData->productId,
                'wc_get_product' => $this->wcProduct(),
                'admin_url' => 'admin-ajax.php'
            ]
        );


        /*
         * Sut
         */
        $applePayDirectHandler = new Mollie_WC_Helper_ApplePayDirectHandler();

        /*
         * Execute Test
         */
        $result = $applePayDirectHandler->applePayScriptData();
        self::assertEquals($expected, $result);
    }

    /**
     *
     * @return PHPUnit_Framework_MockObject_MockObject
     * @throws PHPUnit_Framework_Exception
     */
    private function wcProduct()
    {
        $item = $this->createConfiguredMock(
            'WC_Product',
            [
                'get_price' => '1',
                'get_type' => 'simple',
                'needs_shipping' => true
            ]
        );

        return $item;
    }

    public function testApplePayScriptDataOnCart()
    {
        /*
         * Stubs
         */
        $subtotal = '1';
        $expected = [
            'product' => [
                'needShipping' => true,
                'subtotal' => $subtotal
            ],
            'shop' => [
                'countryCode' => 'IT',
                'currencyCode' => 'EUR',
                'totalLabel' => 'test'
            ],
            'ajaxUrl' => 'admin-ajax.php'
        ];
        stubs(
            [
                'wc_get_base_location' => ['country' => 'IT'],
                'get_woocommerce_currency' => 'EUR',
                'get_bloginfo' => 'test',
                'is_product' => false,
                'is_cart' => true,
                'admin_url' => 'admin-ajax.php',
                'WC' => $this->wooCommerce($subtotal)

            ]
        );


        /*
         * Sut
         */
        $applePayDirectHandler = new Mollie_WC_Helper_ApplePayDirectHandler();

        /*
         * Execute Test
         */
        $result = $applePayDirectHandler->applePayScriptData();
        self::assertEquals($expected, $result);
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
        $responseFromMollie = ["response from Mollie"];
        stubs(
            [
                'get_site_url' => 'http://www.testdomain.com',

            ]
        );


        /*
         * Sut
         */
        $applePayDirectHandler = $this->createPartialMock(
            Mollie_WC_Helper_ApplePayDirectHandler::class,
            ['validationApiWalletsEndpointCall']
        );


        /*
         * Expectations
         */
        expect('wp_verify_nonce')
            ->once()
            ->with($_POST['nonce'], 'mollie_applepay_button')
            ->andReturn(true);
        $applePayDirectHandler->expects($this->once())->method(
            'validationApiWalletsEndpointCall'
        )->with('www.testdomain.com', $_POST['validationUrl'])->willReturn(
            $responseFromMollie
        );

        expect('update_option')
            ->once()
            ->with('mollie_apple_pay_session_response', $responseFromMollie);
        expect('wp_send_json_success')
            ->once()
            ->with($responseFromMollie);


        /*
         * Execute Test
         */
        $applePayDirectHandler->validateMerchant();
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


        /*
         * Sut
         */
        $applePayDirectHandler = $this->createPartialMock(
            Mollie_WC_Helper_ApplePayDirectHandler::class,
            ['createWCCountries', 'responseSuccess', 'getShippingPackages']
        );

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
        $applePayDirectHandler->expects($this->once())
            ->method('createWCCountries')
            ->willReturn($this->wcCountries());
        $applePayDirectHandler->expects($this->once())
            ->method('getShippingPackages')
            ->willReturn([]);


        $applePayDirectHandler->expects($this->once())
            ->method('responseSuccess')
            ->with($expected);

        /*
         * Execute Test
         */
        $applePayDirectHandler->updateShippingContact();
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


        /*
         * Sut
         */
        $applePayDirectHandler = $this->createPartialMock(
            Mollie_WC_Helper_ApplePayDirectHandler::class,
            ['createWCCountries', 'responseSuccess', 'getShippingPackages']
        );

        /*
         * Expectations
         */
        expect('wp_verify_nonce')
            ->once()
            ->with($_POST['nonce'], 'mollie_applepay_button')
            ->andReturn(true);
        $applePayDirectHandler->expects($this->once())
            ->method('createWCCountries')
            ->willReturn($this->wcCountries());
        $applePayDirectHandler->expects($this->never())
            ->method('getShippingPackages');
        $applePayDirectHandler->expects($this->never())
            ->method('responseSuccess');
        expect('wp_send_json_error')
            ->once()
            ->with($expected);

        /*
         * Execute Test
         */
        $applePayDirectHandler->updateShippingContact();
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


        /*
         * Sut
         */
        $applePayDirectHandler = $this->createPartialMock(
            Mollie_WC_Helper_ApplePayDirectHandler::class,
            ['createWCCountries']
        );


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
        $applePayDirectHandler->expects($this->never())
            ->method('createWCCountries')
            ->willReturn($this->wcCountries());
        expect('wp_send_json_error')
            ->once()
            ->with($expected);

        /*
         * Execute Test
         */
        $applePayDirectHandler->updateShippingContact();
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


        /*
         * Sut
         */
        $applePayDirectHandler = $this->createPartialMock(
            Mollie_WC_Helper_ApplePayDirectHandler::class,
            ['responseSuccess', 'getShippingPackages']
        );

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

        $applePayDirectHandler->expects($this->once())
            ->method('getShippingPackages')
            ->willReturn([]);


        $applePayDirectHandler->expects($this->once())
            ->method('responseSuccess')
            ->with($expected);

        /*
         * Execute Test
         */
        $applePayDirectHandler->updateShippingMethod();
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
        $dataObject = new Mollie_WC_Helper_ApplePayDataObjectHttp();
        $dataObject->orderData($_POST, 'productDetail');
        $authorizationResultResponse = [
            'returnUrl' => 'returnUrl',
            'responseToApple' => ['status' => 0]
        ];

        /*
         * Sut
         */
        $applePayDirectHandler = $this->createPartialMock(
            Mollie_WC_Helper_ApplePayDirectHandler::class,
            [
                'updateOrderPostMeta',
                'processOrderPayment',
                'addAddressesToOrder',
                'addShippingMethodsToOrder',
                'responseSuccess',
                'redirectOnApplePaySuccessfulPayment'
            ]
        );

        /*
         * Expectations
         */
        expect('wp_verify_nonce')
            ->once()
            ->with($_POST['nonce'], 'mollie_applepay_button')
            ->andReturn(true);
        expect('wc_get_product')
            ->once();


        $applePayDirectHandler->expects($this->once())->method(
            'addAddressesToOrder'
        )->with($dataObject, $order)->willReturn($order);
        $applePayDirectHandler->expects($this->once())->method(
            'addShippingMethodsToOrder'
        )->with(
            $dataObject->shippingMethod,
            $dataObject->shippingAddress,
            $order
        )->willReturn($order);
        $applePayDirectHandler->expects($this->once())->method(
            'updateOrderPostMeta'
        )->with($orderId, $order);
        $applePayDirectHandler->expects($this->once())->method(
            'processOrderPayment'
        )->with($orderId)->willReturn(['result' => 'success']);
        $applePayDirectHandler->expects($this->once())->method(
            'redirectOnApplePaySuccessfulPayment'
        )->with($orderId)->willReturn('returnUrl');

        $applePayDirectHandler->expects($this->once())->method(
            'responseSuccess'
        )->with($authorizationResultResponse);

        $order->expects($this->once())->method('payment_complete');

        /*
         * Execute Test
         */
        $applePayDirectHandler->createWcOrder();
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

    /**
     *
     * @return PHPUnit_Framework_MockObject_MockObject
     * @throws PHPUnit_Framework_Exception
     */
    private function apiHelper()
    {
        $item = $this->createConfiguredMock(
            Mollie_WC_Helper_Api::class,
            [
                'getApiClient' => $this->apiClient()
            ]
        );

        return $item;
    }

    /**
     *
     * @return PHPUnit_Framework_MockObject_MockObject
     * @throws PHPUnit_Framework_Exception
     */
    private function apiClient()
    {
        $item = $this->createConfiguredMock(MollieApiClient::class, []);
        $mollieWallet = $this->createConfiguredMock(
            WalletEndpoint::class,
            ['requestApplePayPaymentSession' => 'ok']
        );
        $item->wallets = $mollieWallet;

        return $item;
    }


}

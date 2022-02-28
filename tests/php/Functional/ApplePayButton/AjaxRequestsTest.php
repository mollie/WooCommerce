<?php

namespace Mollie\WooCommerceTests\Functional\ApplePayButton;

use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mollie\Api\Endpoints\OrderEndpoint;
use Mollie\Api\MollieApiClient;
use Mollie\WooCommerce\Buttons\ApplePayButton\AppleAjaxRequests;
use Mollie\WooCommerce\Buttons\ApplePayButton\ResponsesToApple;
use Mollie\WooCommerce\Gateway\Surcharge;
use Mollie\WooCommerce\Payment\RefundLineItemsBuilder;
use Mollie\WooCommerce\Shared\Data;
use Mollie\WooCommerce\Subscription\MollieSubscriptionGateway;
use Mollie\WooCommerceTests\Functional\HelperMocks;
use Mollie\WooCommerceTests\Stubs\postDTOTestsStubs;
use Mollie\WooCommerceTests\TestCase;
use PHPUnit_Framework_Exception;
use WC_Countries;

use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\stubs;
use function Brain\Monkey\Functions\when;

class AjaxRequestsTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    /**
     * @var RefundLineItemsBuilder
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

    public function testValidateMerchant()
    {
        /*
         * Stubs
         */
        $postDummyData = new postDTOTestsStubs();
        $_POST = [
            'validationUrl' => $postDummyData->validationUrl,
            'woocommerce-process-checkout-nonce' => $postDummyData->nonce,
        ];
        $responseFromMollie = ["response from Mollie"];
        stubs(
            [
                'get_site_url' => 'http://www.testdomain.com',

            ]
        );
        list($logger, $responsesTemplate) = $this->responsesToApple();
        $apiClientMock = $this->createConfiguredMock(
            MollieApiClient::class,
            []
        );

        /*
         * Sut
         */
        $testee = $this->buildTesteeMock(
            AppleAjaxRequests::class,
            [
                $responsesTemplate,
                $this->helperMocks->noticeMock(),
                $logger,
                $this->helperMocks->apiHelper($apiClientMock),
                $this->helperMocks->settingsHelper(),
            ],
            ['validationApiWalletsEndpointCall']
        )->getMock();


        /*
         * Expectations
         */
        expect('wp_verify_nonce')
            ->once()
            ->with($_POST['woocommerce-process-checkout-nonce'], 'woocommerce-process_checkout')
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
            'woocommerce-process-checkout-nonce' => $postDummyData->nonce,
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
        list($logger, $responsesTemplate) = $this->responsesToApple();
        $apiClientMock = $this->createConfiguredMock(
            MollieApiClient::class,
            []
        );

        /*
         * Sut
         */
        $testee = $this->buildTesteeMock(
            AppleAjaxRequests::class,
            [
                $responsesTemplate,
                $this->helperMocks->noticeMock(),
                $logger,
                $this->helperMocks->apiHelper($apiClientMock),
                $this->helperMocks->settingsHelper(),
            ],
            ['createWCCountries', 'getShippingPackages']
        )->getMock();

        /*
         * Expectations
         */
        expect('wp_verify_nonce')
            ->once()
            ->with($_POST['woocommerce-process-checkout-nonce'], 'woocommerce-process_checkout')
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
            'woocommerce-process-checkout-nonce' => $postDummyData->nonce,
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
        list($logger, $responsesTemplate) = $this->responsesToApple();
        $apiClientMock = $this->createConfiguredMock(
            MollieApiClient::class,
            []
        );

        /*
         * Sut
         */
        $testee = $this->buildTesteeMock(
            AppleAjaxRequests::class,
            [
                $responsesTemplate,
                $this->helperMocks->noticeMock(),
                $logger,
                $this->helperMocks->apiHelper($apiClientMock),
                $this->helperMocks->settingsHelper(),
            ],
            ['createWCCountries']
        )->getMock();


        /*
         * Expectations
         */
        expect('wp_verify_nonce')
            ->once()
            ->with($_POST['woocommerce-process-checkout-nonce'], 'woocommerce-process_checkout')
            ->andReturn(true);
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
        return $this->createConfiguredMock(
            'WC_Session',
            [
                'set' => null

            ]
        );
    }

    /**
     *
     * @throws PHPUnit_Framework_Exception
     */
    private function wcOrder()
    {
        return $this->createConfiguredMock(
            'Mollie\WooCommerceTests\Stubs\WC_Order',
            [
                'get_id' => 11,
            ]
        );
    }

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();

        when('__')->returnArg(1);
    }

    /**
     * @return array
     */
    protected function responsesToApple(): array
    {
        $logger = $this->helperMocks->loggerMock();
        $appleGateway = $this->mollieGateway('applepay', false, true);
        $responsesTemplate = new ResponsesToApple($logger, $appleGateway);
        return array($logger, $responsesTemplate);
    }
}

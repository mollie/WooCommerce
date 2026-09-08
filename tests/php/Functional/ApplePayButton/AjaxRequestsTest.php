<?php

namespace Mollie\WooCommerceTests\Functional\ApplePayButton;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mollie\Api\Endpoints\OrderEndpoint;
use Mollie\Api\MollieApiClient;
use Mollie\WooCommerce\Buttons\ApplePayButton\AppleAjaxRequests;
use Mollie\WooCommerce\Buttons\ApplePayButton\ApplePayDataObjectHttp;
use Mollie\WooCommerce\Buttons\ApplePayButton\ResponsesToApple;
use Mollie\WooCommerce\Gateway\Refund\RefundLineItemsBuilder;
use Mollie\WooCommerce\Gateway\Surcharge;
use Mollie\WooCommerce\Shared\Data;
use Mollie\WooCommerceTests\Functional\HelperMocks;
use Mollie\WooCommerceTests\Stubs\postDTOTestsStubs;
use Mollie\WooCommerceTests\Stubs\WooCommerceMocks;
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
    /** @var WooCommerceMocks */
    private $wooCommerceMocks;

    public function __construct($name = null, array $data = [], $dataName = '')
    {
        parent::__construct($name, $data, $dataName);
        $this->helperMocks = new HelperMocks();
        $this->wooCommerceMocks = new WooCommerceMocks();
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
                'wp_parse_url' =>null
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
            ['validationApiWalletsEndpointCall', 'isNonceValid', 'applePayDataObjectHttp']
        )->getMock();
        /*
         * Expectations
         */
        $applePayDataObjectHttp = $this->createPartialMock(ApplePayDataObjectHttp::class, ['getFilteredRequestData']);
        $applePayDataObjectHttp->method('getFilteredRequestData')->willReturn($_POST);
        $testee->expects($this->once())->method('applePayDataObjectHttp')->willReturn(
            $applePayDataObjectHttp
        );
        expect('wp_verify_nonce')
            ->with($_POST['woocommerce-process-checkout-nonce'], 'woocommerce-process_checkout')
            ->andReturn(true);
        $testee->expects($this->once())->method(
            'isNonceValid'
        )->willReturn(
            true
        );
        $testee->expects($this->once())->method(
            'validationApiWalletsEndpointCall'
        )->with('www.testdomain.com', $_POST['validationUrl'], 'test_NtHd7vSyPSpEyuTEwhjsxdjsgVG4Sx')->willReturn(
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
            ['createWCCountries', 'getShippingPackages', 'applePayDataObjectHttp']
        )->getMock();

        /*
         * Expectations
         */
        $applePayDataObjectHttp = $this->createPartialMock(ApplePayDataObjectHttp::class, ['getFilteredRequestData']);
        $applePayDataObjectHttp->method('getFilteredRequestData')->willReturn($_POST);
        $testee->expects($this->once())->method('applePayDataObjectHttp')->willReturn(
            $applePayDataObjectHttp
        );
        expect('wp_verify_nonce')
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
            ['createWCCountries', 'applePayDataObjectHttp']
        )->getMock();


        /*
         * Expectations
         */
        $applePayDataObjectHttp = $this->getMockBuilder(ApplePayDataObjectHttp::class)->setConstructorArgs([$logger]
        )->onlyMethods(['getFilteredRequestData'])->getMock();
        $applePayDataObjectHttp->method('getFilteredRequestData')->willReturn($_POST);
        $testee->expects($this->once())->method('applePayDataObjectHttp')->willReturn(
            $applePayDataObjectHttp
        );
        expect('wp_verify_nonce')
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

    /**
     *
     * GIVEN a request to createWcOrder with an invalid/missing checkout nonce
     * WHEN createWcOrder() is invoked
     * THEN it returns early before touching the cart or processing the checkout
     */
    public function testCreateWcOrderReturnsEarlyOnInvalidNonce()
    {
        list($logger, $responsesTemplate) = $this->responsesToApple();
        $apiClientMock = $this->createConfiguredMock(MollieApiClient::class, []);

        $testee = $this->buildTesteeMock(
            AppleAjaxRequests::class,
            [
                $responsesTemplate,
                $this->helperMocks->noticeMock(),
                $logger,
                $this->helperMocks->apiHelper($apiClientMock),
                $this->helperMocks->settingsHelper(),
            ],
            ['isNonceValid', 'responseAfterSuccessfulResult', 'applePayDataObjectHttp', 'addAddressesToOrder']
        )->getMock();

        $testee->expects($this->once())->method('isNonceValid')->willReturn(false);
        $testee->expects($this->never())->method('responseAfterSuccessfulResult');
        $testee->expects($this->never())->method('applePayDataObjectHttp');
        $testee->expects($this->never())->method('addAddressesToOrder');

        $testee->createWcOrder();
    }

    /**
     *
     * GIVEN a request to createWcOrderFromCart with an invalid/missing checkout nonce
     * WHEN createWcOrderFromCart() is invoked
     * THEN it returns early before adding addresses or processing the checkout
     */
    public function testCreateWcOrderFromCartReturnsEarlyOnInvalidNonce()
    {
        list($logger, $responsesTemplate) = $this->responsesToApple();
        $apiClientMock = $this->createConfiguredMock(MollieApiClient::class, []);

        $testee = $this->buildTesteeMock(
            AppleAjaxRequests::class,
            [
                $responsesTemplate,
                $this->helperMocks->noticeMock(),
                $logger,
                $this->helperMocks->apiHelper($apiClientMock),
                $this->helperMocks->settingsHelper(),
            ],
            ['isNonceValid', 'responseAfterSuccessfulResult', 'applePayDataObjectHttp', 'addAddressesToOrder']
        )->getMock();

        $testee->expects($this->once())->method('isNonceValid')->willReturn(false);
        $testee->expects($this->never())->method('responseAfterSuccessfulResult');
        $testee->expects($this->never())->method('applePayDataObjectHttp');
        $testee->expects($this->never())->method('addAddressesToOrder');

        $testee->createWcOrderFromCart();
    }

    /**
     *
     * GIVEN WPML String Translation is active and a translation exists for the gateway fee label
     * WHEN cartCalculationResults() builds the ApplePay surcharge fee line
     * THEN the fee label in the result is the WPML-translated label, not the raw stored option
     *
     * @test
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function cartCalculationResultsUsesWpmlTranslatedFeeLabel()
    {
        require_once TEST_PATH . '/../overrides/wpml.php';

        $applepaySettings = $this->helperMocks->paymentMethodSettings(
            [
                'payment_surcharge' => Surcharge::FIXED_FEE,
                'fixed_fee' => 2.00,
            ]
        );
        expect('get_option')
            ->andReturnUsing(static function ($key, $default = null) use ($applepaySettings) {
                if ($key === 'mollie_wc_gateway_applepay_settings') {
                    return $applepaySettings;
                }
                return 'Gateway Fee';
            });
        expect('icl_register_string')
            ->with('mollie-payments-for-woocommerce', 'gatewayFeeLabel', 'Gateway Fee');
        expect('apply_filters')
            ->with('wpml_translate_single_string', 'Gateway Fee', 'mollie-payments-for-woocommerce', 'gatewayFeeLabel')
            ->andReturn('Kosten betaalmethode');

        list($logger, $responsesTemplate) = $this->responsesToApple();
        $apiClientMock = $this->createConfiguredMock(MollieApiClient::class, []);

        $testee = $this->buildTesteeMock(
            AppleAjaxRequests::class,
            [
                $responsesTemplate,
                $this->helperMocks->noticeMock(),
                $logger,
                $this->helperMocks->apiHelper($apiClientMock),
                $this->helperMocks->settingsHelper(),
            ],
            []
        )->getMock();

        $cart = $this->createConfiguredMock(
            'WC_Cart',
            [
                'get_total' => 20.00,
                'get_subtotal' => 18.00,
                'needs_shipping' => false,
                'get_total_tax' => 2.00,
            ]
        );

        $result = $this->invokeProtectedMethod($testee, 'cartCalculationResults', [$cart, [], []]);

        $this->assertSame('Kosten betaalmethode', $result['fee']['label']);
    }

    /**
     * GIVEN an ApplePayDataObjectHttp with callerPage set to 'productDetail'
     * WHEN whichCalculateTotals() reads callerPage from AppleAjaxRequests (a different class)
     * THEN it must not fatal on accessing the protected property directly
     */
    public function testWhichCalculateTotalsDoesNotFatalOnProtectedCallerPageAccess()
    {
        $logger = $this->helperMocks->loggerMock();
        $dataObject = $this->createPartialMock(
            ApplePayDataObjectHttp::class,
            ['productId', 'productQuantity', 'simplifiedContact', 'shippingMethod']
        );
        $dataObject->method('productId')->willReturn('123');
        $dataObject->method('productQuantity')->willReturn('1');
        $dataObject->method('simplifiedContact')->willReturn(['country' => 'NL']);
        $dataObject->method('shippingMethod')->willReturn([]);
        $reflection = new \ReflectionProperty(ApplePayDataObjectHttp::class, 'callerPage');
        $reflection->setAccessible(true);
        $reflection->setValue($dataObject, 'productDetail');

        list(, $responsesTemplate) = $this->responsesToApple();
        $apiClientMock = $this->createConfiguredMock(MollieApiClient::class, []);
        $testee = $this->buildTesteeMock(
            AppleAjaxRequests::class,
            [
                $responsesTemplate,
                $this->helperMocks->noticeMock(),
                $logger,
                $this->helperMocks->apiHelper($apiClientMock),
                $this->helperMocks->settingsHelper(),
            ],
            ['calculateTotalsSingleProduct']
        )->getMock();
        $testee->expects($this->once())
            ->method('calculateTotalsSingleProduct')
            ->willReturn(['total' => 10]);

        $result = $this->invokeProtectedMethod($testee, 'whichCalculateTotals', [$dataObject]);

        $this->assertSame(['total' => 10], $result);
    }

    private function invokeProtectedMethod($object, string $method, array $args = [])
    {
        $reflection = new \ReflectionMethod($object, $method);
        if (PHP_VERSION_ID < 80100) {
            $reflection->setAccessible(true);
        }
        return $reflection->invokeArgs($object, $args);
    }

    public function mollieGateway($paymentMethodName, $isSepa = false, $isSubscription = false){
        return $this->helperMocks->mollieGatewayBuilder($paymentMethodName, $isSepa, $isSubscription, []);
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
        return $this->wooCommerceMocks->wooCommerce($subtotal, $shippingTotal, $total, $tax);
    }

    /**
     *
     * @return PHPUnit_Framework_MockObject_MockObject
     * @throws PHPUnit_Framework_Exception
     */
    private function wcCart($subtotal, $shippingTotal, $total, $tax)
    {
        return $this->wooCommerceMocks->wcCart($subtotal, $shippingTotal, $total, $tax);
    }

    /**
     *
     * @return PHPUnit_Framework_MockObject_MockObject
     * @throws PHPUnit_Framework_Exception
     */
    private function wcCustomer()
    {
        return $this->wooCommerceMocks->wcCustomer();
    }

    /**
     *
     * @return PHPUnit_Framework_MockObject_MockObject
     * @throws PHPUnit_Framework_Exception
     */
    private function wcCountries()
    {
        return $this->wooCommerceMocks->wcCountries();
    }

    /**
     *
     * @return PHPUnit_Framework_MockObject_MockObject
     * @throws PHPUnit_Framework_Exception
     */
    private function wcShipping()
    {
        return $this->wooCommerceMocks->wcShipping();
    }

    /**
     *
     * @return PHPUnit_Framework_MockObject_MockObject
     * @throws PHPUnit_Framework_Exception
     */
    private function wcShippingRate($id, $label, $cost)
    {
        return $this->wooCommerceMocks->wcShippingRate($id, $label, $cost);
    }

    /**
     *
     * @return PHPUnit_Framework_MockObject_MockObject
     * @throws PHPUnit_Framework_Exception
     */
    private function wcSession()
    {
        return $this->wooCommerceMocks->wcSession();
    }

    /**
     *
     * @throws PHPUnit_Framework_Exception
     */
    private function wcOrder()
    {
        return $this->wooCommerceMocks->wcOrder();
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

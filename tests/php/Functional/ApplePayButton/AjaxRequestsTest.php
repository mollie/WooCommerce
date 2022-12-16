<?php

namespace Mollie\WooCommerceTests\Functional\ApplePayButton;

use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mollie\Api\Endpoints\OrderEndpoint;
use Mollie\Api\MollieApiClient;
use Mollie\WooCommerce\Buttons\ApplePayButton\AppleAjaxRequests;
use Mollie\WooCommerce\Buttons\ApplePayButton\ApplePayDataObjectHttp;
use Mollie\WooCommerce\Buttons\ApplePayButton\ResponsesToApple;
use Mollie\WooCommerce\Payment\RefundLineItemsBuilder;
use Mollie\WooCommerce\Shared\Data;
use Mollie\WooCommerceTests\Stubs\postDTOTestsStubs;
use Mollie\WooCommerceTests\TestCase;
use PHPUnit_Framework_Exception;

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
        return $this->woocommerceMocks->wooCommerce($subtotal, $shippingTotal, $total, $tax);
    }

    /**
     *
     * @return PHPUnit_Framework_MockObject_MockObject
     * @throws PHPUnit_Framework_Exception
     */
    private function wcCountries()
    {
        return $this->woocommerceMocks->wcCountries();
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

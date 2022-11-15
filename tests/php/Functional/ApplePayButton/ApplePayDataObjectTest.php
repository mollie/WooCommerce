<?php

namespace Mollie\WooCommerceTests\Functional\ApplePayButton;

use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mollie\WooCommerce\Buttons\ApplePayButton\ApplePayDataObjectHttp;
use Mollie\WooCommerceTests\Stubs\postDTOTestsStubs;
use Mollie\WooCommerceTests\Functional\HelperMocks;
use Mollie\WooCommerceTests\TestCase;


use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\when;


class ApplePayDataObjectTest extends TestCase
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
        $postDummyData = new postDTOTestsStubs();
        $_POST = [
            'woocommerce-process-checkout-nonce' => $postDummyData->nonce,
            'validationUrl' => $postDummyData->validationUrl,
            'productId' => $postDummyData->productId,
            'productQuantity' => $postDummyData->productQuantity,
            'simplifiedContact' => $postDummyData->simplifiedContact,
            'needShipping' => $postDummyData->needShipping,
            'callerPage' => $postDummyData->callerPage,
            'shippingMethod' => $postDummyData->shippingMethod,
            'shippingContact' => $postDummyData->shippingContact,
            'billingContact' => $postDummyData->billingContact
        ];
        expect('wp_verify_nonce')
            ->andReturn(true);
        $logger = $this->helperMocks->loggerMock();
        $dataObject = $this->getMockBuilder(ApplePayDataObjectHttp::class)->setConstructorArgs([$logger]
        )->onlyMethods(['getFilteredRequestData'])->getMock();
        $dataObject->method('getFilteredRequestData')->willReturn($_POST);
        $dataObject->validationData();
        $nonce = $dataObject->nonce();
        self::assertEquals($_POST['woocommerce-process-checkout-nonce'], $nonce);
        $validationUrl = $dataObject->validationUrl();
        self::assertEquals($_POST['validationUrl'], $validationUrl);

        $dataObject->updateContactData();
        $nonce = $dataObject->nonce();
        self::assertEquals($_POST['woocommerce-process-checkout-nonce'], $nonce);
        $productId = $dataObject->productId();
        self::assertEquals($_POST['productId'], $productId);

        $simplifiedContact = $dataObject->simplifiedContact();
        $expectedContact = [
            'city' => $_POST['simplifiedContact']['locality'],
            'postcode' => $_POST['simplifiedContact']['postalCode'],
            'country' => strtoupper(
                $_POST['simplifiedContact']['countryCode']
            )
        ];
        self::assertEquals($expectedContact, $simplifiedContact);

        $dataObject->updateMethodData();
        $method = $dataObject->shippingMethod();
        self::assertEquals($_POST['shippingMethod'], $method);

        $dataObject->orderData('productDetail');
        $shippingAddress = $dataObject->shippingAddress();
        $shippingAddress['address_1'] = htmlspecialchars_decode($shippingAddress['address_1'], ENT_QUOTES);
        $shippingAddress['address_2'] = htmlspecialchars_decode($shippingAddress['address_2'], ENT_QUOTES);
        $expectedAddress = [
            'first_name' => $postDummyData->shippingContact['givenName'],
            'last_name' => $postDummyData->shippingContact['familyName'],
            'email' => $postDummyData->shippingContact['emailAddress'],
            'phone' => $postDummyData->shippingContact['phoneNumber'],
            'address_1' => $postDummyData->shippingContact['addressLines'][0],
            'address_2' => $postDummyData->shippingContact['addressLines'][1],
            'city' => $postDummyData->shippingContact['locality'],
            'state' => $postDummyData->shippingContact['administrativeArea'],
            'postcode' => $postDummyData->shippingContact['postalCode'],
            'country' => strtoupper(
                $postDummyData->shippingContact['countryCode']
            )
        ];
        self::assertEquals($expectedAddress, $shippingAddress);
    }

    public function testDataObjectErrorNoUrl()
    {
        $postDummyData = new postDTOTestsStubs();
        $logger = $this->helperMocks->loggerMock();
        $_POST = [
            'woocommerce-process-checkout-nonce' => $postDummyData->nonce,
        ];
        $dataObject = $this->getMockBuilder(ApplePayDataObjectHttp::class)->setConstructorArgs([$logger]
        )->onlyMethods(['getFilteredRequestData'])->getMock();
        $dataObject->method('getFilteredRequestData')->willReturn($_POST);
        expect('wp_verify_nonce')
            ->andReturn(true);
        expect('mollieWooCommerceDebug')
            ->withAnyArgs();

        $dataObject->validationData();
        $expectedErrorsIndex = [['errorCode' => 'unknown']];
        self::assertEquals($expectedErrorsIndex, $dataObject->errors());
    }

    public function testDataObjectErrorNoNonce()
    {
        $postDummyData = new postDTOTestsStubs();
        $logger = $this->helperMocks->loggerMock();
        $_POST = [
            'woocommerce-process-checkout-nonce' => '',
            'validationUrl' => $postDummyData->validationUrl
        ];
        $dataObject = $this->getMockBuilder(ApplePayDataObjectHttp::class)->setConstructorArgs([$logger]
        )->onlyMethods(['getFilteredRequestData'])->getMock();
        $dataObject->method('getFilteredRequestData')->willReturn($_POST);
        expect('wp_verify_nonce')
            ->andReturn(true);
        $dataObject->validationData();
        $expectedErrorsValue = [['errorCode' => 'unknown']];
        self::assertEquals($expectedErrorsValue, $dataObject->errors());
    }

    public function testDataObjectErrorShippingIncorrect()
    {
        $postDummyData = new postDTOTestsStubs();
        $logger = $this->helperMocks->loggerMock();
        $_POST = [
            'woocommerce-process-checkout-nonce' => $postDummyData->nonce,
            'productId' => $postDummyData->productId,
            'productQuantity' => $postDummyData->productQuantity,
            'callerPage' => $postDummyData->callerPage,
            'simplifiedContact' => [
                'locality' => 'localityValue',
                'postalCode' => '',
                'countryCode' => ''
            ],
            'needShipping' => $postDummyData->needShipping,
        ];
        $dataObject = $this->getMockBuilder(ApplePayDataObjectHttp::class)->setConstructorArgs([$logger]
        )->onlyMethods(['getFilteredRequestData'])->getMock();
        $dataObject->method('getFilteredRequestData')->willReturn($_POST);
        expect('wp_verify_nonce')
            ->andReturn(true);

        $dataObject->updateContactData();
        $expectedErrorsContact = [
            [
                'errorCode' => 'shipping Contact Invalid',
                'contactField' => 'postalCode'
            ],
            [
                'errorCode' => 'shipping Contact Invalid',
                'contactField' => 'countryCode'
            ]
        ];
        self::assertEquals($expectedErrorsContact, $dataObject->errors());
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

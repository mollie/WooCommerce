<?php # -*- coding: utf-8 -*-

namespace Mollie\WooCommerceTests\Unit\WC\Gateway;

use InvalidArgumentException;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Method;
use Mollie\Api\Resources\MethodCollection;
use Mollie\WooCommerceTests\Stubs\varPolylangTestsStubs;
use Mollie\WooCommerceTests\TestCase;
use Mollie_WC_Gateway_Abstract as Testee;
use ReflectionMethod;
use UnexpectedValueException;
use WooCommerce;


use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Actions\expectDone;

/**
 * Class Mollie_WC_Helper_Settings_Test
 */
class Mollie_WC_Gateway_Abstract_Test extends TestCase
{
    /* -----------------------------------------------------------------
       getIconUrl Tests
       -------------------------------------------------------------- */

    /**
     * Test getIconUrl will return the url string
     *
     * @test
     */
    public function getIconUrlReturnsUrlString()
    {
        /*
         * Setup Stubs to mock the API call
         */
        $links = new \stdClass();
        $methods = new MethodCollection(13, $links);
        $client = $this
            ->buildTesteeMock(
                MollieApiClient::class,
                [],
                []
            )
            ->getMock();
        $methodIdeal = new Method($client);
        $methodIdeal->id = "ideal";
        $methodIdeal->image = json_decode('{
                            "size1x": "https://mollie.com/external/icons/payment-methods/ideal.png",
                            "size2x": "https://mollie.com/external/icons/payment-methods/ideal%402x.png",
                            "svg": "https://mollie.com/external/icons/payment-methods/ideal.svg"
                            }');
        //this part is the same code as data::getApiPaymentMethods
        $methods[] = $methodIdeal;
        $methods_cleaned = array();
        foreach ( $methods as $method ) {
            $public_properties = get_object_vars( $method ); // get only the public properties of the object
            $methods_cleaned[] = $public_properties;
        }
        $methods = $methods_cleaned;
        $svg = '<svg width="32" height="24" viewBox="0 0 32 24" fill="none" xmlns="http://www.w3.org/2000/svg">
<path d="M4.12903 0.5H27.871C29.8701 0.5 31.5 2.13976 31.5 4.17391V19.8261C31.5 21.8602 29.8701 23.5 27.871 23.5H4.12903C2.12986 23.5 0.5 21.8602 0.5 19.8261V4.17391C0.5 2.13976 2.12986 0.5 4.12903 0.5Z" fill="white" stroke="#E6E6E6"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M0 0V12.5212H8.30034C10.1134 12.4942 11.5498 12.0462 12.569 11.1836C13.8086 10.1345 14.4372 8.47817 14.4372 6.26058C14.4372 5.20054 14.2719 4.24969 13.9458 3.4345C13.6339 2.65466 13.173 1.9916 12.5758 1.46371C11.5241 0.534015 10.0479 0.031316 8.30034 0.00275604C8.30034 0.0027402 5.53356 0.00182163 0 0Z" transform="translate(8.77417 5.73972)" fill="white"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M0 3.82108H2.31348V0H0V3.82108Z" transform="translate(10.3789 12.7876)" fill="#0A0B09"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M2.61911 1.30262C2.61911 2.02199 2.03276 2.6055 1.30955 2.6055C0.586377 2.6055 0 2.02199 0 1.30262C0 0.583247 0.586377 0 1.30955 0C2.03276 0 2.61911 0.583247 2.61911 1.30262Z" transform="translate(10.2479 9.57452)" fill="#0A0B09"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M8.14328 0.831615C9.75192 0.831615 11.0926 1.26515 12.0205 2.08536C13.0693 3.01254 13.6011 4.41727 13.6011 6.26058C13.6011 9.9137 11.8159 11.6896 8.14328 11.6896C7.85786 11.6896 1.56999 11.6896 0.836044 11.6896C0.836044 10.9446 0.836044 1.57658 0.836044 0.831615C1.56999 0.831615 7.85786 0.831615 8.14328 0.831615ZM8.30034 0H0V12.5212H8.30034V12.5188C10.1134 12.4942 11.5498 12.0462 12.569 11.1836C13.8086 10.1345 14.4372 8.47817 14.4372 6.26058C14.4372 5.20054 14.2719 4.24969 13.9458 3.4345C13.6339 2.65466 13.173 1.9916 12.5758 1.46371C11.5241 0.534015 10.0479 0.031316 8.30034 0.00275604C8.30034 0.00273228 8.30034 0 8.30034 0Z" transform="translate(8.77417 5.73972)" fill="#0A0B09"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M8.14328 0.831615C9.75192 0.831615 11.0926 1.26515 12.0205 2.08536C13.0693 3.01254 13.6011 4.41727 13.6011 6.26058C13.6011 9.9137 11.8159 11.6896 8.14328 11.6896C7.85786 11.6896 1.56999 11.6896 0.836044 11.6896C0.836044 10.9446 0.836044 1.57658 0.836044 0.831615C1.56999 0.831615 7.85786 0.831615 8.14328 0.831615ZM8.30034 0H0V12.5212H8.30034V12.5188C10.1134 12.4942 11.5498 12.0462 12.569 11.1836C13.8086 10.1345 14.4372 8.47817 14.4372 6.26058C14.4372 5.20054 14.2719 4.24969 13.9458 3.4345C13.6339 2.65466 13.173 1.9916 12.5758 1.46371C11.5241 0.534015 10.0479 0.031316 8.30034 0.00275604C8.30034 0.00273228 8.30034 0 8.30034 0Z" transform="translate(8.77417 5.73972)" fill="#0A0B09"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M2.93449 9.02922H0V0H2.93449H2.81606C5.26332 0 7.86827 0.960705 7.86827 4.52648C7.86827 8.29614 5.26332 9.02922 2.81606 9.02922H2.93449Z" transform="translate(13.7546 7.58371)" fill="#CD0067"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M0.440403 0.368287V1.8043H0.770354C0.893754 1.8043 0.982829 1.7974 1.03758 1.7836C1.10924 1.76585 1.16869 1.73577 1.21599 1.69338C1.26324 1.65099 1.30184 1.58123 1.3317 1.48412C1.36156 1.38701 1.37649 1.25465 1.37649 1.08704C1.37649 0.919433 1.36156 0.79077 1.3317 0.701027C1.30184 0.611332 1.26004 0.541334 1.20629 0.491057C1.15255 0.440756 1.08435 0.406755 1.00175 0.388982C0.940023 0.375201 0.819108 0.368287 0.638976 0.368287H0.440403ZM0 0H0.805683C0.987391 0 1.12589 0.0138291 1.22122 0.0414863C1.34928 0.0790277 1.45899 0.145723 1.55034 0.241573C1.64168 0.337399 1.71117 0.454727 1.75884 0.593535C1.8065 0.732344 1.83034 0.903514 1.83034 1.10702C1.83034 1.28587 1.80798 1.43998 1.76331 1.5694C1.70871 1.7275 1.63076 1.85543 1.52951 1.95325C1.45304 2.02736 1.34978 2.08514 1.21972 2.12663C1.1224 2.15728 0.992359 2.17258 0.829522 2.17258H0V0Z" transform="translate(13.2489 9.91452)" fill="#FFFFFE"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M0 2.17258V0H1.61686V0.368287H0.440428V0.849435H1.53474V1.21772H0.440428V1.8043H1.65866V2.17258H0Z" transform="translate(15.5775 9.91452)" fill="#FFFFFE"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M1.37974 1.31127L1.07866 0.506382L0.783636 1.31127H1.37974ZM2.19163 2.17258H1.71186L1.52127 1.67955H0.648674L0.467897 2.17258H0L0.848777 0H1.31818L2.19163 2.17258Z" transform="translate(17.5109 9.91452)" fill="#FFFFFE"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M0 2.15476V0H0.440428V1.78647H1.53772V2.15476H0Z" transform="translate(20.0709 9.93234)" fill="#FFFFFE"/>
</svg>
';
        /*
        * Expect to call availablePaymentMethods() function and return a mock of one method with id 'ideal'
        */
        expect('mollieWooCommerceAvailablePaymentMethods')
            ->once()
            ->withNoArgs()
            ->andReturn($methods);
        expect('wp_safe_remote_get')
            ->andReturn([]);
        expect('wp_remote_retrieve_body')
            ->andReturn($svg);

        /*
         * Setup Testee
         */
        $testee = $this->buildTesteeMock(
            Testee::class,
            [],
            ['getMollieMethodId']
        )
            ->getMockForAbstractClass();
        $testee = $this->proxyFor($testee);

        /*
        * Expect testee is has id 'ideal'
        */
        $testee
            ->expects($this->once())
            ->method('getMollieMethodId')
            ->willReturn('ideal');

        /*
         * Execute test
         */
        $result = $testee->getIconUrl();
        $idealSvg = '<svg style="float:right"  width="32" height="24" viewBox="0 0 32 24" fill="none" xmlns="http://www.w3.org/2000/svg">
<path d="M4.12903 0.5H27.871C29.8701 0.5 31.5 2.13976 31.5 4.17391V19.8261C31.5 21.8602 29.8701 23.5 27.871 23.5H4.12903C2.12986 23.5 0.5 21.8602 0.5 19.8261V4.17391C0.5 2.13976 2.12986 0.5 4.12903 0.5Z" fill="white" stroke="#E6E6E6"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M0 0V12.5212H8.30034C10.1134 12.4942 11.5498 12.0462 12.569 11.1836C13.8086 10.1345 14.4372 8.47817 14.4372 6.26058C14.4372 5.20054 14.2719 4.24969 13.9458 3.4345C13.6339 2.65466 13.173 1.9916 12.5758 1.46371C11.5241 0.534015 10.0479 0.031316 8.30034 0.00275604C8.30034 0.0027402 5.53356 0.00182163 0 0Z" transform="translate(8.77417 5.73972)" fill="white"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M0 3.82108H2.31348V0H0V3.82108Z" transform="translate(10.3789 12.7876)" fill="#0A0B09"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M2.61911 1.30262C2.61911 2.02199 2.03276 2.6055 1.30955 2.6055C0.586377 2.6055 0 2.02199 0 1.30262C0 0.583247 0.586377 0 1.30955 0C2.03276 0 2.61911 0.583247 2.61911 1.30262Z" transform="translate(10.2479 9.57452)" fill="#0A0B09"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M8.14328 0.831615C9.75192 0.831615 11.0926 1.26515 12.0205 2.08536C13.0693 3.01254 13.6011 4.41727 13.6011 6.26058C13.6011 9.9137 11.8159 11.6896 8.14328 11.6896C7.85786 11.6896 1.56999 11.6896 0.836044 11.6896C0.836044 10.9446 0.836044 1.57658 0.836044 0.831615C1.56999 0.831615 7.85786 0.831615 8.14328 0.831615ZM8.30034 0H0V12.5212H8.30034V12.5188C10.1134 12.4942 11.5498 12.0462 12.569 11.1836C13.8086 10.1345 14.4372 8.47817 14.4372 6.26058C14.4372 5.20054 14.2719 4.24969 13.9458 3.4345C13.6339 2.65466 13.173 1.9916 12.5758 1.46371C11.5241 0.534015 10.0479 0.031316 8.30034 0.00275604C8.30034 0.00273228 8.30034 0 8.30034 0Z" transform="translate(8.77417 5.73972)" fill="#0A0B09"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M8.14328 0.831615C9.75192 0.831615 11.0926 1.26515 12.0205 2.08536C13.0693 3.01254 13.6011 4.41727 13.6011 6.26058C13.6011 9.9137 11.8159 11.6896 8.14328 11.6896C7.85786 11.6896 1.56999 11.6896 0.836044 11.6896C0.836044 10.9446 0.836044 1.57658 0.836044 0.831615C1.56999 0.831615 7.85786 0.831615 8.14328 0.831615ZM8.30034 0H0V12.5212H8.30034V12.5188C10.1134 12.4942 11.5498 12.0462 12.569 11.1836C13.8086 10.1345 14.4372 8.47817 14.4372 6.26058C14.4372 5.20054 14.2719 4.24969 13.9458 3.4345C13.6339 2.65466 13.173 1.9916 12.5758 1.46371C11.5241 0.534015 10.0479 0.031316 8.30034 0.00275604C8.30034 0.00273228 8.30034 0 8.30034 0Z" transform="translate(8.77417 5.73972)" fill="#0A0B09"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M2.93449 9.02922H0V0H2.93449H2.81606C5.26332 0 7.86827 0.960705 7.86827 4.52648C7.86827 8.29614 5.26332 9.02922 2.81606 9.02922H2.93449Z" transform="translate(13.7546 7.58371)" fill="#CD0067"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M0.440403 0.368287V1.8043H0.770354C0.893754 1.8043 0.982829 1.7974 1.03758 1.7836C1.10924 1.76585 1.16869 1.73577 1.21599 1.69338C1.26324 1.65099 1.30184 1.58123 1.3317 1.48412C1.36156 1.38701 1.37649 1.25465 1.37649 1.08704C1.37649 0.919433 1.36156 0.79077 1.3317 0.701027C1.30184 0.611332 1.26004 0.541334 1.20629 0.491057C1.15255 0.440756 1.08435 0.406755 1.00175 0.388982C0.940023 0.375201 0.819108 0.368287 0.638976 0.368287H0.440403ZM0 0H0.805683C0.987391 0 1.12589 0.0138291 1.22122 0.0414863C1.34928 0.0790277 1.45899 0.145723 1.55034 0.241573C1.64168 0.337399 1.71117 0.454727 1.75884 0.593535C1.8065 0.732344 1.83034 0.903514 1.83034 1.10702C1.83034 1.28587 1.80798 1.43998 1.76331 1.5694C1.70871 1.7275 1.63076 1.85543 1.52951 1.95325C1.45304 2.02736 1.34978 2.08514 1.21972 2.12663C1.1224 2.15728 0.992359 2.17258 0.829522 2.17258H0V0Z" transform="translate(13.2489 9.91452)" fill="#FFFFFE"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M0 2.17258V0H1.61686V0.368287H0.440428V0.849435H1.53474V1.21772H0.440428V1.8043H1.65866V2.17258H0Z" transform="translate(15.5775 9.91452)" fill="#FFFFFE"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M1.37974 1.31127L1.07866 0.506382L0.783636 1.31127H1.37974ZM2.19163 2.17258H1.71186L1.52127 1.67955H0.648674L0.467897 2.17258H0L0.848777 0H1.31818L2.19163 2.17258Z" transform="translate(17.5109 9.91452)" fill="#FFFFFE"/>
<path fill-rule="evenodd" clip-rule="evenodd" d="M0 2.15476V0H0.440428V1.78647H1.53772V2.15476H0Z" transform="translate(20.0709 9.93234)" fill="#FFFFFE"/>
</svg>
';

        self::assertEquals($idealSvg, $result);

    }
    /**
     * Test getIconUrl will return the url string even if Api returns empty
     *
     * @test
     */
    public function getIconUrlReturnsUrlStringFromAssets()
    {

        /*
        * Expect to call availablePaymentMethods() function and return false from the API
        */
        expect('mollieWooCommerceAvailablePaymentMethods')
            ->once()
            ->withNoArgs()
            ->andReturn(false);
        expect('esc_attr')
            ->once()
            ->withAnyArgs()
            ->andReturn("/images/ideal.svg");


        /*
         * Setup Testee
         */
        $testee = $this->buildTesteeMock(
            Testee::class,
            [],
            ['getMollieMethodId']
        )
            ->getMockForAbstractClass();
        $testee = $this->proxyFor($testee);

        /*
        * Expect testee is has id 'ideal'
        */
        $testee
            ->expects($this->once())
            ->method('getMollieMethodId')
            ->willReturn('ideal');

        /*
         * Execute test
         */
        $result = $testee->getIconUrl();
        $expected = '<img src="/images/ideal.svg" style="width: 25px; vertical-align: bottom;" />';

        self::assertStringEndsWith($expected, $result);

    }

    /**
     * Test associativePaymentMethodsImages returns associative array
     * ordered by id (method name) of image urls
     *
     * @test
     */
    public function associativePaymentMethodsImagesReturnsArrayOrderedById()
    {
        /*
         * Setup stubs
         */
        $links = new \stdClass();
        $methods = new MethodCollection(13, $links);
        $client = $this
            ->buildTesteeMock(
                MollieApiClient::class,
                [],
                []
            )
            ->getMock();
        $methodIdeal = new Method($client);
        $methodIdeal->id = "ideal";
        $methodIdeal->image = json_decode('{
                            "size1x": "https://mollie.com/external/icons/payment-methods/ideal.png",
                            "size2x": "https://mollie.com/external/icons/payment-methods/ideal%402x.png",
                            "svg": "https://mollie.com/external/icons/payment-methods/ideal.svg"
                            }');
        $methods[] = $methodIdeal;
        $methods_cleaned = array();
        foreach ( $methods as $method ) {
            $public_properties = get_object_vars( $method ); // get only the public properties of the object
            $methods_cleaned[] = $public_properties;
        }
        $methods = $methods_cleaned;
        $paymentMethodsImagesResult = [
            "ideal" => $methodIdeal->image
        ];
        /*
         * Setup Testee
         */
        $testee = $this->buildTesteeMock(
            Testee::class,
            [],
            []
        )
            ->getMockForAbstractClass();
        $testee = $this->proxyFor($testee);

        /*
         * Execute Test
         */
        $result = $testee->associativePaymentMethodsImages($methods);

        self::assertEquals($paymentMethodsImagesResult,$result );
    }

    /**
     * Test associativePaymentMethodsImages returns array ordered by id of payment method to access images directly
     *
     * @test
     */
    public function associativePaymentMethodsImagesReturnsEmptyArrayIfApiFails()
    {
        /*
         * Setup Testee
         */
        $testee = $this->buildTesteeMock(
            Testee::class,
            [],
            []
        )
            ->getMockForAbstractClass();
        $testee = $this->proxyFor($testee);

        /*
         * Execute Test
         */
        $emptyArr = [];
        $result = $testee->associativePaymentMethodsImages($emptyArr);

        self::assertEquals($emptyArr, $result);
    }

    /* -----------------------------------------------------------------
     getReturnUrl Tests
     -------------------------------------------------------------- */

    /**
     * Test getReturnUrl
     * given polylang plugin is installed
     * then will return correct string
     */
    public function testgetReturnUrlReturnsStringwithPolylang()
    {
        /*
         * Setup Testee
         */
        $testee = $this->testPolylangTestee();

        //set variables
        $varStubs = new varPolylangTestsStubs();


        /*
        * Setup Stubs
        */
        $wcUrl = $this->createConfiguredMock(
            WooCommerce::class,
            ['api_request_url' => $varStubs->apiRequestUrl]
        );
        $wcOrder = $this->createMock('WC_Order');


        /*
        * Expectations
        */
        expect('get_home_url')
            ->andReturn($varStubs->homeUrl);
        //get url from request
        expect('WC')
            ->andReturn($wcUrl);
        //delete url final slash
        expect('untrailingslashit')
            ->twice()
            ->andReturn($varStubs->untrailedUrl, $varStubs->untrailedWithParams);
        expect('idn_to_ascii')
            ->andReturn($varStubs->untrailedUrl);
        //get order id and key and append to the the url
        expect('mollieWooCommerceOrderId')
            ->andReturn($varStubs->orderId);
        expect('mollieWooCommerceOrderKey')
            ->andReturn($varStubs->orderKey);
        $testee
            ->expects($this->once())
            ->method('appendOrderArgumentsToUrl')
            ->with($varStubs->orderId, $varStubs->orderKey, $varStubs->untrailedUrl)
            ->willReturn($varStubs->urlWithParams);

        //check for multilanguage plugin enabled and receive url
        $testee
            ->expects($this->once())
            ->method('getSiteUrlWithLanguage')
            ->willReturn("{$varStubs->afterLangUrl}");

        expect('mollieWooCommerceDebug')
            ->withAnyArgs();

        /*
         * Execute test
         */
        $result = $testee->getReturnUrl($wcOrder);

        self::assertEquals($varStubs->result, $result);
    }

    /* -----------------------------------------------------------------
      getWebhookUrl Tests
      -------------------------------------------------------------- */
    /**
     * Test getWebhookUrl
     * given polylang plugin is installed
     * then will return correct string
     */
    public function testgetWebhookUrlReturnsStringwithPolylang()
    {
        /*
         * Setup Testee
         */
        $testee = $this->testPolylangTestee();

        //set variables
        $varStubs = new varPolylangTestsStubs();

        /*
        * Setup Stubs
        */
        $wcUrl = $this->createConfiguredMock(
            WooCommerce::class,
            ['api_request_url' => $varStubs->apiRequestUrl]
        );
        $wcOrder = $this->createMock('WC_Order');


        /*
        * Expectations
        */
        expect('get_home_url')
            ->andReturn($varStubs->homeUrl);
        //get url from request
        expect('WC')
            ->andReturn($wcUrl);
        //delete url final slash
        expect('untrailingslashit')
            ->twice()
            ->andReturn($varStubs->untrailedUrl, $varStubs->untrailedWithParams);
        expect('idn_to_ascii')
            ->andReturn($varStubs->untrailedUrl);
        //get order id and key and append to the the url
        expect('mollieWooCommerceOrderId')
            ->andReturn($varStubs->orderId);
        expect('mollieWooCommerceOrderKey')
            ->andReturn($varStubs->orderKey);
        $testee
            ->expects($this->once())
            ->method('appendOrderArgumentsToUrl')
            ->with($varStubs->orderId, $varStubs->orderKey, $varStubs->untrailedUrl)
            ->willReturn($varStubs->urlWithParams);
        //check for multilanguage plugin enabled, receives url and adds it
        $testee
            ->expects($this->once())
            ->method('getSiteUrlWithLanguage')
            ->willReturn("{$varStubs->homeUrl}/nl");
        expect('mollieWooCommerceDebug')
            ->withAnyArgs();

        /*
         * Execute test
         */
        $result = $testee->getWebhookUrl($wcOrder);

        self::assertEquals(
            "{$varStubs->homeUrl}/nl/wc-api/mollie_return?order_id={$varStubs->orderId}&key=wc_order_{$varStubs->orderKey}",
            $result
        );
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    public function testPolylangTestee()
    {
        $testee = $this
            ->buildTesteeMock(
                Testee::class,
                [],
                ['getSiteUrlWithLanguage', 'appendOrderArgumentsToUrl']
            )
            ->getMockForAbstractClass();
        $testee = $this->proxyFor($testee);
        return $testee;
    }

    /**
     * @return array
     */
    public function testPolylangVariables()
    {
        $orderId = $this->faker->randomDigit;
        $orderKey = $this->faker->word;
        $homeUrl = rtrim($this->faker->url, '/\\');
        $apiRequestUrl = "{$homeUrl}/wc-api/mollie_return";
        $untrailedUrl = rtrim($apiRequestUrl, '/\\');
        $urlWithParams
            = "{$untrailedUrl}/?order_id={$orderId}&key=wc_order_{$orderKey}";
        $untrailedWithParams = rtrim($urlWithParams, '/\\');
        return array(
            $orderId,
            $orderKey,
            $homeUrl,
            $apiRequestUrl,
            $untrailedUrl,
            $urlWithParams,
            $untrailedWithParams
        );
    }

    /* -----------------------------------------------------------------
      getReturnRedirectUrlForOrder Tests
      -------------------------------------------------------------- */
    /**
     * Test getReturnRedirectUrlForOrder
     * given order does NOT need payment
     * then will fire
     * do_action('mollie-payments-for-woocommerce_customer_return_payment_success', $order)
     *
     * @throws \Brain\Monkey\Expectation\Exception\ExpectationArgsRequired
     */
    public function testgetReturnRedirectUrlForOrderHookIsSuccessWhenNeedPaymentFalse()
    {
        /*
         * Setup Testee
         */
        $testee = $this->buildTesteeMock(
            Testee::class,
            [],
            ['orderNeedsPayment', 'get_return_url']
        )->getMockForAbstractClass();
        $testee = $this->proxyFor($testee);

        /*
        * Setup Stubs
        */
        $order = $this->createMock(\WC_Order::class);

        /*
        * Expectations
        */
        expect('mollieWooCommerceOrderId')
            ->once()
            ->with($order);
        expect('mollieWooCommerceDebug')
            ->once();
        $testee
            ->expects($this->once())
            ->method('orderNeedsPayment')
            ->with($order)
            ->willReturn(false);
        expectDone('mollie-payments-for-woocommerce_customer_return_payment_success')
            ->once()
            ->with($order);
        $testee
            ->expects($this->once())
            ->method('get_return_url')
            ->with($order);
        /*
         * Execute test
         */
        $testee->getReturnRedirectUrlForOrder($order);
    }

    /**
     * Test getReturnRedirectUrlForOrder
     * given order does need payment
     * When there is exception thrown
     * then will fire
     * do_action('mollie-payments-for-woocommerce_customer_return_payment_failed', $order)
     *
     * @throws \Brain\Monkey\Expectation\Exception\ExpectationArgsRequired
     */
    public function testgetReturnRedirectUrlForOrderHookIsFailedWhenNeedPaymentException()
    {
        /*
         * Setup Testee
         */
        $testee = $this->buildTesteeMock(
            Testee::class,
            [],
            ['orderNeedsPayment', 'get_return_url', 'paymentObject', 'activePaymentObject']
        )->getMockForAbstractClass();
        $testee = $this->proxyFor($testee);

        /*
        * Setup Stubs
        */
        $order = $this->createMock(\WC_Order::class);
        $payment = $this->createMock(\Mollie_WC_Payment_Object::class);

        /*
        * Expectations
        */
        expect('mollieWooCommerceOrderId')
            ->once()
            ->with($order);
        expect('mollieWooCommerceDebug')
            ->twice();
        $testee
            ->expects($this->once())
            ->method('orderNeedsPayment')
            ->with($order)
            ->willReturn(true);
        $testee
            ->expects($this->once())
            ->method('paymentObject')
            ->willReturn($payment);
        $testee
            ->expects($this->once())
            ->method('activePaymentObject')
            ->willThrowException(new UnexpectedValueException());
        expect('mollieWooCommerceNotice')
            ->once();
        expectDone('mollie-payments-for-woocommerce_customer_return_payment_failed')
            ->once()
            ->with($order);
        $testee
            ->expects($this->once())
            ->method('get_return_url')
            ->with($order);
        /*
         * Execute test
         */
        $testee->getReturnRedirectUrlForOrder($order);
    }

    public function testValidFilters()
    {
        $currency = 'USD';
        $order_total = 42.0;
        $billing_country = 'NL';
        $payment_locale = '';

        list($sut, $sutReflection) = $this->createSutForFilters();

        $sut
            ->expects($this->once())
            ->method('getAmountValue')
            ->willReturn('42.00');

        $filters = $sutReflection->invoke(
            $sut,
            $currency,
            $order_total,
            $payment_locale,
            $billing_country
        );

        $expected = [
            'amount' => [
                'currency' => $currency,
                'value' => '42.00',
            ],
            'locale' => '',
            'billingCountry' => 'NL',
            'sequenceType' => \Mollie\Api\Types\SequenceType::SEQUENCETYPE_ONEOFF,
            'resource' => 'orders',
        ];

        self::assertEquals($expected, $filters);
    }

    public function testInvalidFilterAmount() {

        $this->expectException(InvalidArgumentException::class);

        $currency = 'USD';
        $order_total = 0.0;
        $payment_locale = '';
        $billing_country = '';

        list($sut, $sutReflection) = $this->createSutForFilters();

        $sutReflection->invoke(
            $sut,
            $currency,
            $order_total,
            $payment_locale,
            $billing_country
        );
    }

    public function testInvalidFilterCurrency()
    {
        $this->expectException(InvalidArgumentException::class);

        $currency = 'SURINAAMSE DOLLAR';
        $order_total = 42.0;
        $payment_locale = '';
        $billing_country = '';

        list($sut, $sutReflection) = $this->createSutForFilters();

        $sutReflection->invoke(
            $sut,
            $currency,
            $order_total,
            $payment_locale,
            $billing_country
        );
    }

    public function testInvalidFilterBillingCountry()
    {
        $this->expectException(InvalidArgumentException::class);

        $currency = 'USD';
        $order_total = 42.0;
        $payment_locale = '';
        $billing_country = 'Nederland';

        list($sut, $sutReflection) = $this->createSutForFilters();

        $sut
            ->expects($this->once())
            ->method('getAmountValue')
            ->willReturn('42.00');

        $sutReflection->invoke(
            $sut,
            $currency,
            $order_total,
            $payment_locale,
            $billing_country
        );
    }

    /**
     * @return array
     * @throws \ReflectionException
     */
    private function createSutForFilters()
    {
        $sut = $this
            ->getMockBuilder(Testee::class)
            ->setMethods([
                'init_settings',
                'get_option',
                'process_admin_options',
                'formatCurrencyValue',
                'getAmountValue',
            ])
            ->getMockForAbstractClass();

        $sutReflection = new ReflectionMethod($sut, 'getFilters');
        $sutReflection->setAccessible(true);

        return array($sut, $sutReflection);
    }
}

<?php # -*- coding: utf-8 -*-

namespace Mollie\WooCommerceTests\Unit\WC\Gateway;

use MollieTests\TestCase;
use Mollie_WC_Gateway_Giftcard as Testee;


/**
 * Class Mollie_WC_Helper_Settings_Test
 */
class Mollie_WC_Gateway_Giftcard_Test extends TestCase
{
    /* -----------------------------------------------------------------
       getPaymentLocale Tests
       -------------------------------------------------------------- */

    /**
     * Test checkSvgIssuers will return empty string if the string in the array
     * given is not set.
     * @test
     */
    public function checkSvgIssuersReturnsEmptyStringIfIssuersNotSet()
    {

        /*
         * Setup Testee
         */
        $testee = $this->buildTesteeMethodMock(
            Testee::class,
            [],
            []
        );

        /*
         * Execute test
         */
        $arrayNull = [];
        $result = $testee->checkSvgIssuers($arrayNull);

        self::assertEquals('', $result);

    }
    /**
     * Test checkSvgIssuers will return empty string if the image in the array
     * given is not set.
     * @test
     */
    public function checkSvgIssuersReturnsEmptyStringIfImageNotSet()
    {

        /*
         * Setup Testee
         */
        $testee = $this->buildTesteeMethodMock(
            Testee::class,
            [],
            []
        );
        $json = ' {
                    "resource": "method",
                    "id": "ideal",
                    "description": "iDEAL",
                    "minimumAmount": {
                        "value": "0.01",
                        "currency": "EUR"
                    },
                    "maximumAmount": {
                        "value": "50000.00",
                        "currency": "EUR"
                    },
                    "image": {
                        "size1x": "https://www.mollie.com/external/icons/payment-methods/ideal.png",
                        "size2x": "https://www.mollie.com/external/icons/payment-methods/ideal%402x.png",
                        "svg": "https://www.mollie.com/external/icons/payment-methods/ideal.svg"
                    },
                    "issuers": [
                        {
                            "resource": "issuer",
                            "id": "ideal_ABNANL2A",
                            "name": "ABN AMRO",
                            "image": {}
                        }
                   
                    ],
                    "_links": {
                        "self": {
                            "href": "https://api.mollie.com/v2/methods/ideal?include=issuers",
                            "type": "application/hal+json"
                        },
                        "documentation": {
                            "href": "https://docs.mollie.com/reference/v2/methods-api/get-method",
                            "type": "text/html"
                        }
                    }
                }';
        $method = json_decode($json);
        $imageNull = $method->issuers;

        /*
         * Execute test
         */
        $result = $testee->checkSvgIssuers($imageNull);

        self::assertEquals('', $result);
    }
    /**
     * Test checkSvgIssuers will return empty string if the svg in the array
     * given is not set.
     * @test
     */
    public function checkSvgIssuersReturnsEmptyStringIfSvgNotSet()
    {

        /*
         * Setup Testee
         */
        $testee = $this->buildTesteeMethodMock(
            Testee::class,
            [],
            []
        );
        $json = ' {
                    "resource": "method",
                    "id": "ideal",
                    "description": "iDEAL",
                    "minimumAmount": {
                        "value": "0.01",
                        "currency": "EUR"
                    },
                    "maximumAmount": {
                        "value": "50000.00",
                        "currency": "EUR"
                    },
                    "image": {
                        "size1x": "https://www.mollie.com/external/icons/payment-methods/ideal.png",
                        "size2x": "https://www.mollie.com/external/icons/payment-methods/ideal%402x.png",
                        "svg": "https://www.mollie.com/external/icons/payment-methods/ideal.svg"
                    },
                    "issuers": [
                        {
                            "resource": "issuer",
                            "id": "ideal_ABNANL2A",
                            "name": "ABN AMRO",
                            "image": {
                                "size1x": "https://www.mollie.com/external/icons/payment-methods/ideal.png",
                                "size2x": "https://www.mollie.com/external/icons/payment-methods/ideal%402x.png"
                                
                            }
                        }
                        
                    ],
                    "_links": {
                        "self": {
                            "href": "https://api.mollie.com/v2/methods/ideal?include=issuers",
                            "type": "application/hal+json"
                        },
                        "documentation": {
                            "href": "https://docs.mollie.com/reference/v2/methods-api/get-method",
                            "type": "text/html"
                        }
                    }
                }';
        $method = json_decode($json);
        $svgNull = $method->issuers;

        /*
         * Execute test
         */

        $result = $testee->checkSvgIssuers($svgNull);

        self::assertEquals('', $result);
    }
    /**
     * Test checkSvgIssuers will return empty string if the string in the array
     * given is not set.
     * @test
     */
    public function checkSvgIssuersReturnsSvgUrl()
    {

        /*
         * Setup Testee
         */
        $testee = $this->buildTesteeMethodMock(
            Testee::class,
            [],
            []
        );

        /*
         * Execute test
         */
        $arrayNull = [];
        $result = $testee->checkSvgIssuers($arrayNull);

        self::assertEquals('', $result);

        $json = ' {
                    "resource": "method",
                    "id": "ideal",
                    "description": "iDEAL",
                    "minimumAmount": {
                        "value": "0.01",
                        "currency": "EUR"
                    },
                    "maximumAmount": {
                        "value": "50000.00",
                        "currency": "EUR"
                    },
                    "image": {
                        "size1x": "https://www.mollie.com/external/icons/payment-methods/ideal.png",
                        "size2x": "https://www.mollie.com/external/icons/payment-methods/ideal%402x.png",
                        "svg": "https://www.mollie.com/external/icons/payment-methods/ideal.svg"
                    },
                    "issuers": [
                        {
                            "resource": "issuer",
                            "id": "ideal_ABNANL2A",
                            "name": "ABN AMRO",
                            "image": {
                                "size1x": "https://www.mollie.com/external/icons/ideal-issuers/ABNANL2A.png",
                                "size2x": "https://www.mollie.com/external/icons/ideal-issuers/ABNANL2A%402x.png",
                                "svg": "https://www.mollie.com/external/icons/ideal-issuers/ABNANL2A.svg"
                            }
                        }
                        
                    ],
                    "_links": {
                        "self": {
                            "href": "https://api.mollie.com/v2/methods/ideal?include=issuers",
                            "type": "application/hal+json"
                        },
                        "documentation": {
                            "href": "https://docs.mollie.com/reference/v2/methods-api/get-method",
                            "type": "text/html"
                        }
                    }
                }';
        $method = json_decode($json);
        $imageNull = $method->issuers;
        $result = $testee->checkSvgIssuers($imageNull);

        self::assertEquals('https://www.mollie.com/external/icons/ideal-issuers/ABNANL2A.svg', $result);
    }


}

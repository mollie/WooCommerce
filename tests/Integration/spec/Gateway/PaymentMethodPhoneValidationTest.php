<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Integration\spec\Gateway;

use Mollie\WooCommerce\Gateway\GatewayModule;
use Mollie\WooCommerceTests\Integration\IntegrationMockedTestCase;

class PaymentMethodPhoneValidationTest extends IntegrationMockedTestCase
{
    private GatewayModule $gatewayModule;
    private \WP_Error $mockErrors;

    public function setUp(): void
    {
        parent::setUp();
        $this->gatewayModule = new GatewayModule();
        $this->mockErrors = $this->createMock(\WP_Error::class);

        // Clear POST data before each test
        $_POST = [];
    }

    public function tearDown(): void
    {
        // Clean up POST data after each test
        $_POST = [];
        parent::tearDown();
    }

    /**
     * Test that validation is skipped when payment method doesn't match
     */
    public function test_skips_validation_when_wrong_payment_method()
    {
        $fields = [
            'payment_method' => 'different_gateway',
            'billing_phone' => 'invalid_phone'
        ];

        $result = $this->gatewayModule->addPaymentMethodMandatoryFieldsPhoneVerification(
            $fields,
            'mollie_wc_gateway_in3',
            'billing_phone_in3',
            'Phone',
            $this->mockErrors
        );

        // Should return unchanged when payment method doesn't match
        $this->assertEquals($fields, $result);
        $this->assertEquals('invalid_phone', $result['billing_phone']);
    }

    /**
     * Phone validation regex explanation:
     *
     * The regex `/^\+[1-9]\d{10,13}$|^[1-9]\d{9,13}$|^06\d{9,13}$/` validates three phone number formats:
     *
     * 1. International format: `^\+[1-9]\d{10,13}$`
     *    - Must start with '+' followed by a digit 1-9 (no leading zeros after country code)
     *    - Followed by 10-13 additional digits
     *    - Total length: 12-15 characters (including the '+')
     *    - Examples: +31612345678, +1234567890123
     *
     * 2. National format: `^[1-9]\d{9,13}$`
     *    - Must start with a digit 1-9 (no leading zeros)
     *    - Followed by 9-13 additional digits
     *    - Total length: 10-14 digits
     *    - Examples: 1234567890, 12345678901234
     *
     * 3. Dutch mobile format: `^06\d{9,13}$`
     *    - Must start with '06' (Dutch mobile prefix)
     *    - Followed by 9-13 additional digits
     *    - Total length: 11-15 digits
     *    - Examples: 06123456789, 061234567890123
     *
     * Invalid examples:
     * - Empty strings, non-numeric characters
     * - Numbers starting with 0 (except 06 format)
     * - Numbers too short or too long for their format
     * - International format starting with +0
     */
    public function test_keeps_valid_phone_numbers()
    {
        $validPhones = [
            '+31612345678',      // International format
            '+49123456789012',   // International (longer)
            '+1234567890123',    // International (max length)
            '612345678901',      // National format
            '12345678901234',    // National format Max length 14
            '06123456789',       // 06 format (min)
            '061234567890123'    // 06 format (max)
        ];

        foreach ($validPhones as $phone) {
            $fields = [
                'payment_method' => 'mollie_wc_gateway_in3',
                'billing_phone' => $phone
            ];

            $result = $this->gatewayModule->addPaymentMethodMandatoryFieldsPhoneVerification(
                $fields,
                'mollie_wc_gateway_in3',
                'billing_phone_in3',
                'Phone',
                $this->mockErrors
            );

            $this->assertEquals($phone, $result['billing_phone'], "Valid phone should be kept: $phone");
        }
    }

    /**
     * Test invalid phone numbers that should be set to null
     */
    public function test_nullifies_invalid_phone_numbers()
    {
        $invalidPhones = [
            '+0612345678',       // Invalid: 0 after +
            '+123456789',        // Too short for international
            '+123456789012345',   // Too long for international
            '0123456789',        // Invalid: starts with 0 (not 06)
            '12345678',          // Too short for national
            '123456789012345678', // Too long for national
            '06123',             // Too short for 06 format
            'invalid',           // Non-numeric
            '+abc123456789',     // Non-numeric after +
            '++31612345678',     // Double +
            '+31-612345678',     // Contains dash
            '+31 612345678',     // Contains space
        ];

        foreach ($invalidPhones as $phone) {
            $fields = [
                'payment_method' => 'mollie_wc_gateway_in3',
                'billing_phone' => $phone
            ];

            $result = $this->gatewayModule->addPaymentMethodMandatoryFieldsPhoneVerification(
                $fields,
                'mollie_wc_gateway_in3',
                'billing_phone_in3',
                'Phone',
                $this->mockErrors
            );

            $this->assertNull($result['billing_phone'], "Invalid phone should be null: $phone");
        }
    }

    /**
     * Test edge cases with empty/null phone values
     */
    public function test_handles_empty_phone_values()
    {
        $emptyCases = [
            '',    // Empty string
            null,  // Null value
            ' ',   // Whitespace only
        ];

        foreach ($emptyCases as $phone) {
            $fields = [
                'payment_method' => 'mollie_wc_gateway_in3',
                'billing_phone' => $phone
            ];

            $result = $this->gatewayModule->addPaymentMethodMandatoryFieldsPhoneVerification(
                $fields,
                'mollie_wc_gateway_in3',
                'billing_phone_in3',
                'Phone',
                $this->mockErrors
            );

            $this->assertNull($result['billing_phone'], "Empty phone should result in null");
        }
    }

    /**
     * Test POST field fallback when billing_phone is empty
     */
    public function test_post_field_fallback_with_invalid_phone()
    {
        // Set up POST data
        $_POST['billing_phone_in3'] = 'invalid_phone';

        $fields = [
            'payment_method' => 'mollie_wc_gateway_in3',
            'billing_phone' => ''
        ];

        $result = $this->gatewayModule->addPaymentMethodMandatoryFieldsPhoneVerification(
            $fields,
            'mollie_wc_gateway_in3',
            'billing_phone_in3',
            'Phone',
            $this->mockErrors
        );

        $this->assertNull($result['billing_phone']);
    }

    /**
     * Test POST field fallback when billing_phone is empty and POST has valid phone
     */
    public function test_post_field_fallback_with_valid_phone()
    {
        // Set up POST data with valid phone
        $_POST['billing_phone_in3'] = '+31612345678';

        $fields = [
            'payment_method' => 'mollie_wc_gateway_in3',
            'billing_phone' => ''
        ];

        $result = $this->gatewayModule->addPaymentMethodMandatoryFieldsPhoneVerification(
            $fields,
            'mollie_wc_gateway_in3',
            'billing_phone_in3',
            'Phone',
            $this->mockErrors
        );

        $this->assertEquals('+31612345678', $result['billing_phone']);
    }

    /**
     * Test no POST field available
     */
    public function test_no_post_field_available()
    {
        // No POST data set
        $fields = [
            'payment_method' => 'mollie_wc_gateway_in3',
            'billing_phone' => ''
        ];

        $result = $this->gatewayModule->addPaymentMethodMandatoryFieldsPhoneVerification(
            $fields,
            'mollie_wc_gateway_in3',
            'billing_phone_in3',
            'Phone',
            $this->mockErrors
        );

        $this->assertNull($result['billing_phone']);
    }

    /**
     * Test method works with different gateway names
     */
    public function test_works_with_different_gateways()
    {
        $gateways = [
            'mollie_wc_gateway_in3',
            'mollie_wc_gateway_riverty',
            'mollie_wc_gateway_other'
        ];

        foreach ($gateways as $gateway) {
            $fields = [
                'payment_method' => $gateway,
                'billing_phone' => '+31612345678'
            ];

            $result = $this->gatewayModule->addPaymentMethodMandatoryFieldsPhoneVerification(
                $fields,
                $gateway,
                'billing_phone_' . str_replace('mollie_wc_gateway_', '', $gateway),
                'Phone',
                $this->mockErrors
            );

            $this->assertEquals('+31612345678', $result['billing_phone'], "Should work with gateway: $gateway");
        }
    }
}

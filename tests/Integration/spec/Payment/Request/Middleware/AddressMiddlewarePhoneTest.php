<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Integration\spec\Payment\Request\Middleware;

use Mollie\WooCommerce\Payment\Request\Middleware\AddressMiddleware;
use Mollie\WooCommerceTests\Integration\IntegrationMockedTestCase;
use WC_Order;

class AddressMiddlewarePhoneTest extends IntegrationMockedTestCase
{
    private AddressMiddleware $middleware;

    public function setUp(): void
    {
        $this->middleware = new AddressMiddleware();
    }

    /**
     * Test complete phone formatting flow through middleware
     */
    public function test_phone_formatting_integration()
    {
        $phoneTests = [
        // Format: [input, country, expected_output]
            ['+31612345678', 'NL', '+31612345678'],           // Already formatted
            ['0612345678', 'NL', '+31612345678'],             // Dutch mobile
            ['0031612345678', 'NL', '+31612345678'],          // 00 prefix
            ['+31 6 1234-5678', 'NL', '+31612345678'],        // With formatting
            ['0201234567', 'DE', '+49201234567'],             // German

        // Unformattable inputs -> null (never sent to the API, never throw).
        // A null phone is valid for methods that do not require a phone.
            ['12345678', 'NL', null],                         // Too short
            ['invalid', 'NL', null],                          // Non-numeric
            ['', 'NL', null],                                 // Empty
        ];

        foreach ($phoneTests as [$inputPhone, $country, $expected]) {
            $order = $this->createOrderWithPhone($inputPhone, $country);
            $result = $this->middleware->__invoke([], $order, 'order', fn($data) => $data);

            $actualPhone = $result['billingAddress']->phone ?? null;
            $this->assertEquals(
                $expected,
                $actualPhone,
                "Phone '$inputPhone' in country '$country' should become '$expected', got '$actualPhone'"
            );
        }
    }

    /**
     * Test phone fallback priority: billing -> shipping -> POST
     */
    public function test_phone_fallback_priority()
    {
        // Billing phone takes priority
        $order = new WC_Order();
        $order->set_billing_phone('+31612345678');
        $order->set_shipping_phone('+31987654321');
        $order->set_billing_country('NL');
        $this->setRequiredBillingFields($order);

        $result = $this->middleware->__invoke([], $order, 'order', fn($data) => $data);
        $this->assertEquals('+31612345678', $result['billingAddress']->phone);

        // Falls back to shipping when billing empty
        $order->set_billing_phone('');
        $result = $this->middleware->__invoke([], $order, 'order', fn($data) => $data);
        $this->assertEquals('+31987654321', $result['billingAddress']->phone);

        // Falls back to POST when both empty
        $order->set_shipping_phone('');
        $_POST['billing_phone'] = '0612345678';

        $result = $this->middleware->__invoke([], $order, 'order', fn($data) => $data);
        $this->assertEquals('+31612345678', $result['billingAddress']->phone);

        unset($_POST['billing_phone']);
    }

    /**
     * Test country-specific phone prefixes
     */
    public function test_country_specific_phone_prefixes()
    {
        $countryTests = [
            'NL' => ['0612345678', '+31612345678'],
            'DE' => ['0301234567', '+49301234567'],
            'FR' => ['0123456789', '+33123456789'],
            'GB' => ['07123456789', '+447123456789'],
            // Unknown region - cannot be formatted to a valid E.164 number -> null
            'XX' => ['0612345678', null],
        ];

        foreach ($countryTests as $country => [$input, $expected]) {
            $order = $this->createOrderWithPhone($input, $country);
            $result = $this->middleware->__invoke([], $order, 'order', fn($data) => $data);

            $this->assertEquals(
                $expected,
                $result['billingAddress']->phone,
                "Phone '$input' in country '$country' should become '$expected'"
            );
        }
    }

    /**
     * Test billing address inclusion rules with phone validation
     */
    public function test_billing_address_inclusion_with_invalid_phone()
    {
        // Invalid phone should not prevent billing address inclusion
        $order = $this->createCompleteOrderWithPhone('invalid_phone', 'NL');
        $result = $this->middleware->__invoke([], $order, 'payment', fn($data) => $data);

        $this->assertNotNull($result['billingAddress']);
        $this->assertNull($result['billingAddress']->phone);
        $this->assertNotNull($result['billingAddress']->streetAndNumber);
    }

    /**
     * Test PayPal express orders skip phone processing
     */
    public function test_paypal_express_skips_phone_processing()
    {
        $order = $this->createOrderWithPhone('+31612345678', 'NL');
        $order->update_meta_data('_mollie_payment_method_button', 'PayPalButton');

        $result = $this->middleware->__invoke([], $order, 'order', fn($data) => $data);

        $this->assertNull($result['billingAddress']);
    }

    /**
     * @scenario Given the Italian national number 3887403368 and region hint IT,
     *           getFormatedPhoneNumber() returns +393887403368 and never a value beginning with +388.
     *
     */
    public function test_italian_national_number_gets_e164_country_code()
    {
        $order = $this->createOrderWithPhone('3887403368', 'IT');

        $result = $this->middleware->__invoke([], $order, 'order', fn($data) => $data);
        $phone = $result['billingAddress']->phone ?? null;

        $this->assertSame('+393887403368', $phone);
        $this->assertStringStartsNotWith('+388', (string) $phone);
    }

    /**
     * @scenario Given an already E.164-formatted number such as +49152123456,
     *           getFormatedPhoneNumber() validates it and returns it unchanged.
     *
     */
    public function test_already_e164_number_is_validated_and_unchanged()
    {
        $order = $this->createOrderWithPhone('+49152123456', 'DE');

        $result = $this->middleware->__invoke([], $order, 'order', fn($data) => $data);

        $this->assertSame('+49152123456', $result['billingAddress']->phone ?? null);
    }

    /**
     * @scenario Given a non-E.164 national number and a region hint from the address country,
     *           getFormatedPhoneNumber() returns that number reformatted to E.164 for that region.
     *
     */
    public function test_non_e164_national_numbers_reformat_via_region_hint()
    {
        $phoneTests = [
            // [national number, region hint (address country), expected E.164]
            ['3887403368', 'IT', '+393887403368'],  // Italian mobile, no trunk 0
            ['0612345678', 'NL', '+31612345678'],   // Dutch mobile, trunk 0
            ['0301234567', 'DE', '+49301234567'],   // German, trunk 0
        ];

        foreach ($phoneTests as [$inputPhone, $country, $expected]) {
            $order = $this->createOrderWithPhone($inputPhone, $country);
            $result = $this->middleware->__invoke([], $order, 'order', fn($data) => $data);

            $this->assertSame(
                $expected,
                $result['billingAddress']->phone ?? null,
                "Phone '$inputPhone' with region hint '$country' should become '$expected'"
            );
        }
    }

    /**
     * @scenario Both the billing phone and the shipping phone are normalized exclusively
     *           through getFormatedPhoneNumber(); both reach the API in E.164.
     *
     */
    public function test_shipping_and_billing_phone_both_normalized_to_e164()
    {
        $order = $this->createOrderWithPhone('3887403368', 'IT');
        $order->set_shipping_phone('3479411234');
        $this->setRequiredShippingFields($order, 'IT');

        $result = $this->middleware->__invoke([], $order, 'order', fn($data) => $data);

        $this->assertSame('+393887403368', $result['billingAddress']->phone ?? null);
        $this->assertSame('+393479411234', $result['shippingAddress']->phone ?? null);
    }

    /**
     * @scenario When a provided phone cannot be parsed/formatted into valid E.164 for the target
     *           region (we could not fix it by guessing), the middleware returns a null phone and
     *           does NOT throw — so the invalid number is never sent to the Mollie API and methods
     *           that do not require a phone still complete. A notice for methods that DO require a
     *           phone is handled reactively by PaymentProcessor when the API rejects it, which is
     *           outside this middleware and out of scope here.
     *
     */
    public function test_unformattable_phone_returns_null_without_throwing()
    {
        // A digit string that libphonenumber cannot validate for the region hint
        // (not a real number) cannot be formatted into valid E.164.
        $order = $this->createOrderWithPhone('00000000', 'DE');

        $result = $this->middleware->__invoke([], $order, 'order', fn($data) => $data);

        $this->assertNull(
            $result['billingAddress']->phone ?? null,
            'An unformattable phone must resolve to null, not be sent to the API'
        );
    }

    // Helper methods
    private function createOrderWithPhone(string $phone, string $country): WC_Order
    {
        $gatewayId ='mollie_wc_gateway_ideal';
        $order = $this->getConfiguredOrder(
            0, // guest customer
            $gatewayId,
            ['simple'],
            [],
            false // don't set as paid
        );
        $order->set_billing_phone($phone);
        $order->set_billing_country($country);
        $this->setRequiredBillingFields($order);
        return $order;
    }

    private function createCompleteOrderWithPhone(string $phone, string $country): WC_Order
    {
        $order = $this->createOrderWithPhone($phone, $country);

        return $order;
    }

    private function setRequiredBillingFields(WC_Order $order): void
    {
        $order->set_billing_first_name('John');
        $order->set_billing_last_name('Doe');
        $order->set_billing_email('john@example.com');
        $order->set_billing_address_1('Test Street 123');
        $order->set_billing_postcode('1234AB');
        $order->set_billing_city('Test City');
    }

    private function setRequiredShippingFields(WC_Order $order, string $country): void
    {
        $order->set_shipping_first_name('John');
        $order->set_shipping_last_name('Doe');
        $order->set_shipping_address_1('Test Street 123');
        $order->set_shipping_postcode('1234AB');
        $order->set_shipping_city('Test City');
        $order->set_shipping_country($country);
    }
}

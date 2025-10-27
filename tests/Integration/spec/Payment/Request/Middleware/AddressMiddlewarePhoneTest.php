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

        // Invalid cases -> null
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
            // Unknown country - no prefix added
            'XX' => ['0612345678', '0612345678'],
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
}

<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\ExpressComponent\WooCommerce;

use Automattic\WooCommerce\Blocks\Domain\Services\CheckoutFields;
use Automattic\WooCommerce\Blocks\Package;
use Automattic\WooCommerce\StoreApi\Routes\V1\Checkout;
use Automattic\WooCommerce\StoreApi\RoutesController;
use Automattic\WooCommerce\StoreApi\StoreApi;
use Throwable;
use WC_Order;
use WP_Error;
use WP_REST_Request;
/**
 * What the shopper entered in the block checkout besides what the wallet gives: the order note and
 * WooCommerce's additional checkout fields. Place order would have sent them to the Store API
 * checkout route, which validates them; the express order is checked by that route's own validation.
 */
final class CheckoutForm
{
    /**
     * @param array<string, mixed> $additionalFields Contact and order fields, by field id.
     * @param array<string, mixed> $billingAddress As the cart store has it, additional address fields included.
     * @param array<string, mixed> $shippingAddress As the cart store has it, additional address fields included.
     */
    public function __construct(private string $note, private array $additionalFields, private array $billingAddress, private array $shippingAddress)
    {
    }
    public static function fromRequest(WP_REST_Request $request): self
    {
        return new self((string) $request->get_param('customer_note'), self::object($request->get_param('additional_fields')), self::object($request->get_param('billing_address')), self::object($request->get_param('shipping_address')));
    }
    public static function empty(): self
    {
        return new self('', [], [], []);
    }
    /**
     * WooCommerce's reason when the form would not pass its checkout, otherwise null. Also null on a
     * WooCommerce without additional checkout fields: there is nothing to check.
     */
    public function invalidReason(): ?string
    {
        $route = self::checkoutRoute();
        if ($route === null) {
            return null;
        }
        $request = new WP_REST_Request('POST', '/wc/store/v1/checkout');
        $request->set_param('customer_note', $this->note);
        $request->set_param('additional_fields', $this->additionalFields);
        $request->set_param('billing_address', $this->billingAddress);
        $request->set_param('shipping_address', $this->shippingAddress);
        try {
            $valid = $route->validate_callback($request);
        } catch (Throwable $error) {
            return null;
        }
        if (!$valid instanceof WP_Error) {
            return null;
        }
        $messages = self::messages($valid);
        return $messages !== [] ? implode(' ', $messages) : $valid->get_error_message();
    }
    /**
     * Writes the note and the additional fields as WooCommerce's checkout would; the caller saves.
     */
    public function writeTo(WC_Order $order): void
    {
        if ($this->note !== '') {
            $order->set_customer_note(wc_sanitize_textarea($this->note));
        }
        $fields = self::checkoutFields();
        if ($fields === null) {
            return;
        }
        // Location, group, and where the values come from.
        $sources = [['contact', 'other', $this->additionalFields], ['order', 'other', $this->additionalFields], ['address', 'billing', $this->billingAddress], ['address', 'shipping', $this->shippingAddress]];
        foreach ($sources as [$location, $group, $values]) {
            $posted = array_intersect_key($values, $fields->get_fields_for_location($location));
            foreach ($posted as $key => $value) {
                $fields->persist_field_for_order((string) $key, $fields->sanitize_field((string) $key, $value), $order, $group, \false);
            }
        }
    }
    /**
     * The Store API checkout route's messages, one per invalid field or check.
     *
     * @return list<string>
     */
    private static function messages(WP_Error $invalid): array
    {
        $messages = [];
        foreach ((array) ($invalid->get_error_data()['details'] ?? []) as $detail) {
            $messages[] = (string) ($detail['message'] ?? '');
            foreach ((array) ($detail['additional_errors'] ?? []) as $more) {
                $messages[] = (string) ($more['message'] ?? '');
            }
        }
        return array_values(array_unique(array_filter($messages)));
    }
    /**
     * @return array<string, mixed>
     */
    private static function object($value): array
    {
        return is_array($value) ? $value : [];
    }
    private static function checkoutRoute(): ?Checkout
    {
        if (!class_exists(StoreApi::class) || !class_exists(RoutesController::class)) {
            return null;
        }
        try {
            $route = StoreApi::container()->get(RoutesController::class)->get('checkout');
        } catch (Throwable $missing) {
            return null;
        }
        return $route instanceof Checkout && method_exists($route, 'validate_callback') ? $route : null;
    }
    private static function checkoutFields(): ?CheckoutFields
    {
        if (!class_exists(Package::class) || !class_exists(CheckoutFields::class)) {
            return null;
        }
        try {
            $fields = Package::container()->get(CheckoutFields::class);
        } catch (Throwable $missing) {
            return null;
        }
        return $fields instanceof CheckoutFields ? $fields : null;
    }
}

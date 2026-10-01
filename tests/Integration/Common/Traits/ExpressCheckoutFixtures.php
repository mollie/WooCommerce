<?php
// kb-active

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Integration\Common\Traits;

use Mollie\WooCommerce\Adapter\WordPress\ExpressRoutes;
use Mollie\WooCommerceTests\Integration\Common\Doubles\CanaryData;
use Psr\Container\ContainerInterface;
use WC_Order;
use WP_REST_Response;

/**
 * The block checkout an express scenario starts from, for ExpressFlowTestCase subclasses.
 *
 * The shop: live, HTTPS, PayPal the only wallet with its checkout express button on. It takes its
 * address from the checkout form, like every wallet, so the shipping rules apply. Prices include 21% VAT; zone LU
 * has two flat rates ('standard' 5.00, 'express' 7.50), zone AT one ('austria' 9.00), zone MT none.
 * Every scenario is a new shopper with a full session budget.
 *
 * Call setUpExpressCheckout() at the end of setUp() and tearDownExpressCheckout() before
 * parent::tearDown().
 */
trait ExpressCheckoutFixtures
{
    /**
     * @var array<int, array{0: string, 1: callable, 2: int}>
     */
    private array $filters = [];

    /**
     * @var array<int, int>
     */
    private array $zoneIds = [];

    /**
     * @var array<int, int>
     */
    private array $taxRateIds = [];

    /**
     * Rate ids by name: 'standard' and 'express' ship to LU, 'austria' to AT.
     *
     * @var array<string, string>
     */
    private array $rates = [];

    /**
     * Hooks cleared by bootExpressOwning(), as they were before.
     *
     * @var array<string, mixed>
     */
    private array $hookBackups = [];

    private function setUpExpressCheckout(): void
    {
        $this->clearExpressRateLimits();
        $this->newShopper();
        $this->useHttps();
        $this->payPalIsTheOnlyExpressWallet();
        $this->taxedShippingZones();
    }

    private function tearDownExpressCheckout(): void
    {
        $this->restoreOwnedHooks();
        foreach ($this->filters as [$hook, $callback, $priority]) {
            remove_filter($hook, $callback, $priority);
        }
        $this->filters = [];

        // Leave no shopper behind: later test classes read the customer's location for their taxes.
        $this->newShopper();
        foreach ($this->zoneIds as $zoneId) {
            (new \WC_Shipping_Zone($zoneId))->delete();
        }
        $this->zoneIds = [];
        foreach ($this->taxRateIds as $taxRateId) {
            \WC_Tax::_delete_tax_rate($taxRateId);
        }
        $this->taxRateIds = [];
        \WC_Cache_Helper::get_transient_version('shipping', true);
        $this->clearExpressRateLimits();
    }

    /**
     * Registers a filter that tearDownExpressCheckout() removes again.
     */
    private function addTestFilter(string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1): void
    {
        add_filter($hook, $callback, $priority, $acceptedArgs);
        $this->filters[] = [$hook, $callback, $priority];
    }

    /**
     * @param array<string, callable> $serviceOverrides Passed to bootExpress().
     */
    private function readyGuestCheckout(array $serviceOverrides = []): void
    {
        $this->bootExpress($serviceOverrides);
        $this->actAsGuest();
        $this->cartWith(['simple'], 2);
        $this->fillCheckoutForm($this->billing(), $this->shipping('LU'));
        $this->chooseRate('standard');
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function startSession(array $extra = []): WP_REST_Response
    {
        return $this->restRequest('POST', '/mollie/v1/express/session', array_merge(['nonce' => wp_create_nonce(ExpressRoutes::NONCE_ACTION)], $extra));
    }

    private function token(WP_REST_Response $response): string
    {
        $this->assertSame(200, $response->get_status(), 'Expected a started session: ' . wp_json_encode($response->get_data()));

        return (string) ((array) $response->get_data())['clientAccessToken'];
    }

    private function cartTotal(): string
    {
        return $this->decimal((float) WC()->cart->get_total('edit'));
    }

    private function decimal(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }

    /**
     * @return array<int, array{level: string, message: string, context: array<mixed>}>
     */
    private function loggedEvents(string $event): array
    {
        return array_values(array_filter($this->logger()->records(), static function (array $record) use ($event): bool {
            return $record['message'] === $event;
        }));
    }

    /**
     * @return array<string, string>
     */
    private function billing(): array
    {
        return [
            'first_name' => CanaryData::GIVEN_NAME,
            'last_name' => CanaryData::FAMILY_NAME,
            'email' => CanaryData::EMAIL,
            'phone' => CanaryData::PHONE,
            'address_1' => CanaryData::STREET,
            'address_2' => CanaryData::STREET_ADDITIONAL,
            'postcode' => 'L-1234',
            'city' => 'Luxembourg',
            'country' => 'LU',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function shipping(string $country): array
    {
        return [
            'first_name' => CanaryData::GIVEN_NAME,
            'last_name' => CanaryData::FAMILY_NAME,
            'address_1' => CanaryData::STREET,
            'postcode' => $country === 'AT' ? '1010' : '1234',
            'city' => $country === 'AT' ? 'Wien' : 'Town',
            'country' => $country,
        ];
    }

    /**
     * What the shopper typed into the checkout form. Every field not given is emptied, so a
     * scenario never inherits the previous one's form.
     *
     * @param array<string, string> $billing
     * @param array<string, string> $shipping
     */
    private function fillCheckoutForm(array $billing, array $shipping): void
    {
        $customer = WC()->customer;
        foreach (['first_name', 'last_name', 'email', 'phone', 'address_1', 'address_2', 'postcode', 'city', 'state', 'country'] as $field) {
            $customer->{"set_billing_{$field}"}($billing[$field] ?? '');
        }
        foreach (['first_name', 'last_name', 'address_1', 'address_2', 'postcode', 'city', 'state', 'country'] as $field) {
            $customer->{"set_shipping_{$field}"}($shipping[$field] ?? '');
        }
        $customer->save();
    }

    private function chooseRate(string $name): void
    {
        WC()->session->set('chosen_shipping_methods', [$this->rates[$name]]);
        $this->recalculate();
    }

    private function recalculate(): void
    {
        WC()->cart->calculate_shipping();
        WC()->cart->calculate_totals();
    }

    /**
     * Every scenario is a different shopper. The fake Mollie starts empty for each test, but
     * WooCommerce keeps one session and one customer for the whole PHP process; without this a
     * scenario would be handed the session a previous one remembered, and the next test class would
     * inherit this one's checkout form.
     */
    private function newShopper(): void
    {
        if (!function_exists('WC') || !WC()->session instanceof \WC_Session_Handler) {
            return;
        }
        $this->withoutCookieWarnings(static function (): void {
            WC()->session->forget_session();
        });
        // A customer read from the now empty session: the store's default location, no form data.
        WC()->customer = new \WC_Customer(0, true);
    }

    private function useHttps(): void
    {
        $url = static function (): string {
            return 'https://shop.example';
        };
        add_filter('pre_option_home', $url, PHP_INT_MAX);
        add_filter('pre_option_siteurl', $url, PHP_INT_MAX);
        $this->filters[] = ['pre_option_home', $url, PHP_INT_MAX];
        $this->filters[] = ['pre_option_siteurl', $url, PHP_INT_MAX];
    }

    /**
     * PayPal alone, so the scenarios name one wallet. Every wallet takes its address from the checkout
     * form, so a cart that ships is blocked until the form is complete.
     */
    private function payPalIsTheOnlyExpressWallet(): void
    {
        $this->setGatewaySettingsForTest('paypal', ['enabled' => 'yes', 'mollie_paypal_button_enabled_checkout' => 'yes']);
        $this->setGatewaySettingsForTest('applepay', ['mollie_apple_pay_button_enabled_express_checkout' => 'no']);
    }

    private function taxedShippingZones(): void
    {
        $this->setOptionForTest('woocommerce_calc_taxes', 'yes');
        $this->setOptionForTest('woocommerce_prices_include_tax', 'yes');
        $this->setOptionForTest('woocommerce_tax_display_cart', 'incl');
        $this->setOptionForTest('woocommerce_shipping_tax_class', '');
        $this->setOptionForTest('woocommerce_ship_to_countries', '');
        $this->setOptionForTest('woocommerce_allowed_countries', 'all');
        $this->setOptionForTest('woocommerce_currency', 'EUR');

        foreach (['LU', 'AT', 'MT'] as $country) {
            $this->taxRateIds[] = \WC_Tax::_insert_tax_rate([
                'tax_rate_country' => $country,
                'tax_rate' => '21.0000',
                'tax_rate_name' => 'VAT',
                'tax_rate_priority' => 1,
                'tax_rate_compound' => 0,
                'tax_rate_shipping' => 1,
                'tax_rate_order' => 0,
                'tax_rate_class' => '',
            ]);
        }

        $this->rates = array_merge(
            $this->zone('Express test LU', 'LU', ['standard' => '5.00', 'express' => '7.50']),
            $this->zone('Express test AT', 'AT', ['austria' => '9.00']),
            $this->zone('Express test MT (no rates)', 'MT', [])
        );
        \WC_Cache_Helper::get_transient_version('shipping', true);

        // The site may have zones without locations, which match every address and would win over
        // these (zone_order cannot go below 0). Only this test's zones may match while it runs.
        $zoneIds = implode(',', array_map('intval', $this->zoneIds));
        $onlyTheseZones = static function (array $criteria) use ($zoneIds): array {
            $criteria[] = "AND zones.zone_id IN ({$zoneIds})";

            return $criteria;
        };
        add_filter('woocommerce_get_zone_criteria', $onlyTheseZones, PHP_INT_MAX);
        $this->filters[] = ['woocommerce_get_zone_criteria', $onlyTheseZones, PHP_INT_MAX];
    }

    /**
     * @param array<string, string> $costs Rate name => cost excluding tax.
     * @return array<string, string> Rate name => rate id.
     */
    private function zone(string $name, string $country, array $costs): array
    {
        $zone = new \WC_Shipping_Zone();
        $zone->set_zone_name($name);
        $zone->set_zone_order(0);
        $zone->add_location($country, 'country');
        $zone->save();
        $this->zoneIds[] = $zone->get_id();

        $rates = [];
        foreach ($costs as $rate => $cost) {
            $instanceId = $zone->add_shipping_method('flat_rate');
            update_option("woocommerce_flat_rate_{$instanceId}_settings", [
                'enabled' => 'yes',
                'title' => ucfirst($rate),
                'tax_status' => 'taxable',
                'cost' => $cost,
            ]);
            $rates[$rate] = 'flat_rate:' . $instanceId;
        }

        return $rates;
    }

    /**
     * The express budget is kept by WC_Rate_Limiter, in a table shared by every test, keyed on
     * something the caller cannot choose. Each scenario starts with a full budget.
     */
    private function clearExpressRateLimits(): void
    {
        global $wpdb;

        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}wc_rate_limits WHERE rate_limit_key LIKE %s",
            $wpdb->esc_like('mollie_express') . '%'
        ));
        // WC_Rate_Limiter also keeps each expiry in the object cache, under a per-key prefix that
        // WC_Rate_Limiter::cleanup() does not reach.
        if (wp_cache_supports('flush_group')) {
            wp_cache_flush_group(\WC_Rate_Limiter::CACHE_GROUP);
        } else {
            wp_cache_flush();
        }
    }

    private function startOrder(): WP_REST_Response
    {
        return $this->restRequest('POST', '/mollie/v1/express/order', ['nonce' => wp_create_nonce(ExpressRoutes::NONCE_ACTION)]);
    }

    /**
     * Starts the session the order request relies on.
     *
     * @return array{id: string, ref: string}
     */
    private function startedSession(): array
    {
        $this->token($this->startSession());
        $sessions = $this->fakeMollie()->sessions();
        $payloads = $this->sessionPayloads();
        $ref = (string) (end($payloads)['metadata']['express_ref'] ?? '');
        $this->assertNotSame('', $ref, 'The session must carry an express_ref.');

        return ['id' => (string) array_key_last($sessions), 'ref' => $ref];
    }

    private function sessionExpiresAt(string $sessionId): int
    {
        $expiresAt = strtotime((string) $this->fakeMollie()->sessions()[$sessionId]['expiresAt']);
        $this->assertIsInt($expiresAt);

        return $expiresAt;
    }

    private function assertAnsweredOk(WP_REST_Response $response): void
    {
        $data = (array) $response->get_data();
        $this->assertSame(200, $response->get_status(), 'Expected an order: ' . wp_json_encode($data));
        $this->assertTrue($data['ok'] ?? null, 'Expected ok=true: ' . wp_json_encode($data));
    }

    /**
     * Every order in the store, drafts and trash included, so "no order is left behind" means that.
     *
     * @return array<int, int>
     */
    private function allOrderIds(): array
    {
        $ids = wc_get_orders([
            'limit' => -1,
            'return' => 'ids',
            'type' => 'shop_order',
            'status' => array_merge(array_keys(wc_get_order_statuses()), ['trash', 'wc-checkout-draft']),
        ]);
        $ids = array_map('intval', $ids);
        sort($ids);

        return $ids;
    }

    /**
     * @return array<int, WC_Order>
     */
    private function ordersFor(string $ref): array
    {
        return wc_get_orders([
            'limit' => -1,
            'type' => 'shop_order',
            'status' => array_keys(wc_get_order_statuses()),
            'meta_key' => '_mollie_express_ref',
            'meta_value' => $ref,
        ]);
    }

    private function onlyOrderFor(string $ref): WC_Order
    {
        $orders = $this->ordersFor($ref);
        $this->assertCount(1, $orders);

        return $orders[0];
    }

    /**
     * Boots the plugin with only this boot's callbacks on the given hooks. Every boot adds another
     * generation of callbacks bound to its own container, and on a hook that redirects or cancels the
     * first generation would answer — with an earlier test's logger and clock. The hooks as they
     * were are restored by tearDownExpressCheckout().
     *
     * @param array<int, string> $hooks
     * @param array<string, callable> $serviceOverrides
     */
    private function bootExpressOwning(array $hooks, array $serviceOverrides = []): ContainerInterface
    {
        foreach ($hooks as $hook) {
            if (!array_key_exists($hook, $this->hookBackups)) {
                $this->hookBackups[$hook] = $GLOBALS['wp_filter'][$hook] ?? null;
            }
            unset($GLOBALS['wp_filter'][$hook]);
        }
        return $this->bootExpress($serviceOverrides);
    }

    private function restoreOwnedHooks(): void
    {
        foreach ($this->hookBackups as $hook => $backup) {
            if ($backup === null) {
                unset($GLOBALS['wp_filter'][$hook]);
            } else {
                $GLOBALS['wp_filter'][$hook] = $backup;
            }
        }
        $this->hookBackups = [];
    }
}

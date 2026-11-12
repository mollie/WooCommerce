<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Integration\spec\ExpressComponent;

use Mollie\WooCommerce\ExpressComponent\Rules\PricingFingerprint;
use Mollie\WooCommerce\ExpressComponent\WooCommerce\CartFactsBuilder;
use Mollie\WooCommerce\ExpressComponent\WooCommerce\ExpressSessionStore;
use Mollie\WooCommerce\ExpressComponent\WooCommerce\ExpressShippingSnapshot;
use Mollie\WooCommerceTests\Integration\Common\ExpressFlowTestCase;
use Mollie\WooCommerceTests\Integration\Common\Traits\ExpressCheckoutFixtures;
use WP_REST_Response;

/**
 * POST mollie/v1/express/shipping: Mollie asking, when the shopper picks an address in the wallet,
 * which shipping options the store offers. The call has no cookie; only the secret in its URL and the
 * session id in its body admit it. Only Mollie is faked.
 * Shop: zone LU has two flat rates, AT one, MT eleven; any other country has none.
 *
 * @group integration
 * @group ExpressComponent
 * @group ExpressShippingCallback
 */
class ExpressShippingCallbackTest extends ExpressFlowTestCase
{
    use ExpressCheckoutFixtures;

    private const ROUTE_BASE = 'mollie/v1/express/shipping';

    private const POSTCODE = 'ZZ-4711';

    private const CITY = 'Cityville';

    public function setUp(): void
    {
        parent::setUp();

        $this->setUpExpressCheckout();
    }

    public function tearDown(): void
    {
        $this->tearDownExpressCheckout();

        parent::tearDown();
    }

    /**
     * Scenario: Mollie is offered what WooCommerce charges that cart for that address
     *   Given a guest with a cart that ships and an empty checkout form, and the session started for it
     *   When Mollie posts shipping-address-changed for an address the store ships to
     *   Then the answer is 200 with at most 10 options, the first 10 in WooCommerce's order
     *   And each option's reference is the rate id, its description the rate's label
     *   And its amount is what WooCommerce charges that cart for that address, VAT included
     *   And the same URL answers again for the next address: the secret is not single-use
     *
     * @test
     * @dataProvider shippableAddresses
     * @group REQ-NEW-EXPRESS-SHIPPING-CALLBACK
     */
    public function it_offers_the_rates_woocommerce_charges_for_the_wallet_address(string $country, int $expectedCount): void
    {
        // Given
        $this->giveMtElevenRates();
        $session = $this->startedGuestSession();
        $expected = array_slice($this->whatWooCommerceCharges($country), 0, 10, true);

        // When
        $response = $this->mollieAsks($session, $country);

        // Then
        $this->assertSame(200, $response->get_status(), wp_json_encode($response->get_data()));
        $options = (array) ((array) $response->get_data())['options'];
        $this->assertCount($expectedCount, $options);
        $this->assertSame(array_keys($expected), array_column($options, 'reference'));
        foreach ($options as $option) {
            $this->assertSame($expected[$option['reference']]['label'], $option['description']);
            $this->assertSame(
                ['currency' => get_woocommerce_currency(), 'value' => $expected[$option['reference']]['amount']],
                $option['amount']
            );
        }
        $this->assertSame(200, $this->mollieAsks($session, 'AT')->get_status(), 'The secret must outlive one call.');
    }

    /**
     * Scenario: the options carry VAT when taxes follow the billing address, which Mollie's call does not have
     *   Given taxes based on the customer's billing address, a shop that sells to Austria only
     *         and no default customer location
     *   And a guest who gave an Austrian billing address, and the session started for the cart
     *   When Mollie posts shipping-address-changed for an Austrian address, with no cookie
     *   Then the option's amount is the rate with Austria's VAT: the wallet's address is the billing location too
     *
     * @test
     * @group REQ-NEW-EXPRESS-SHIPPING-CALLBACK
     */
    public function it_prices_the_options_with_vat_when_taxes_follow_the_billing_address(): void
    {
        // Given
        $this->setOptionForTest('woocommerce_tax_based_on', 'billing');
        $this->setOptionForTest('woocommerce_allowed_countries', 'specific');
        $this->setOptionForTest('woocommerce_specific_allowed_countries', ['AT']);
        $this->setOptionForTest('woocommerce_default_customer_address', '');
        $session = $this->startedGuestSession(['country' => 'AT'] + $this->billing());

        // When
        $response = $this->mollieAsks($session, 'AT');

        // Then
        $this->assertSame(200, $response->get_status(), wp_json_encode($response->get_data()));
        $options = (array) ((array) $response->get_data())['options'];
        $this->assertSame([$this->rates['austria']], array_column($options, 'reference'));
        $this->assertSame(['currency' => 'EUR', 'value' => '10.89'], $options[0]['amount'], 'Flat rate 9.00 plus 21% VAT.');
    }

    /**
     * Scenario: a cart saved by an earlier attempt that never finished does not block the next one
     *   Given a guest with a cart that ships
     *   And an earlier request saved that cart for the shipping callback and stopped before its session
     *       was remembered (an overlapping request, or one cut by a timeout)
     *   When the checkout asks for a session again, for the same cart
     *   Then the session is created: a cart already saved as it is counts as saved
     *
     * @test
     * @group REQ-NEW-EXPRESS-SHIPPING-CALLBACK
     */
    public function it_starts_a_session_for_a_cart_an_earlier_attempt_already_saved(): void
    {
        // Given
        $container = $this->bootExpress();
        $this->actAsGuest();
        $this->cartWith(['simple'], 2);
        $store = $container->get(ExpressSessionStore::class);
        $snapshot = $container->get(ExpressShippingSnapshot::class);
        $facts = $container->get(CartFactsBuilder::class)->fromCart();
        $secret = $snapshot->secretFor($store->expressRef(PricingFingerprint::of($facts), $store->nextAttempt()));
        $this->assertTrue($snapshot->save($secret, 900), 'The earlier attempt must have saved the cart.');

        // When
        $response = $this->startSession();

        // Then
        $this->assertSame(200, $response->get_status(), wp_json_encode($response->get_data()));
        $this->assertCount(1, $this->fakeMollie()->requests('POST', 'sessions'));
    }

    /**
     * @return array<string, array{0: string, 1: int}>
     */
    public function shippableAddresses(): array
    {
        return [
            'two rates' => ['LU', 2],
            'one rate' => ['AT', 1],
            'eleven rates are cut to ten' => ['MT', 10],
        ];
    }

    /**
     * Scenario: a call that does not come from Mollie holding this session's secret prices nothing
     *   Given a started session and its callback URL
     *   When the route is called with no secret, a wrong secret, another session id, another event, or after the saved cart expired
     *   Then it is refused with 403 and no options
     *   And neither the secret, the postcode nor the city is in any log line
     *
     * @test
     * @dataProvider refusedCalls
     * @group REQ-NEW-EXPRESS-SHIPPING-CALLBACK
     */
    public function it_refuses_a_callback_without_the_saved_secret_or_session(string $what): void
    {
        // Given
        $session = $this->startedGuestSession();
        $url = $session['callbackUrl'];
        $body = $this->body($session['id'], 'LU');
        switch ($what) {
            case 'no secret':
                $url = $this->withoutSecret($url);
                break;
            case 'a wrong secret':
                $url = $this->withSecret($url, str_repeat('0', 32));
                break;
            case 'another session id':
                $body['sessionId'] = 'ses_' . 'other';
                break;
            case 'another event':
                $body['event'] = 'shipping-method-changed';
                break;
            case 'an expired saved cart':
                $this->expireTheSavedCart();
                break;
        }

        // When
        $response = $this->post($url, $body);

        // Then
        $this->assertSame(403, $response->get_status(), $what);
        $this->assertArrayNotHasKey('options', (array) $response->get_data());
        $logged = (string) wp_json_encode($this->logger()->records());
        foreach ([$this->secretOf($session['callbackUrl']), self::POSTCODE, self::CITY] as $private) {
            $this->assertStringNotContainsString($private, $logged);
        }
    }

    /**
     * @return array<string, array{0: string}>
     */
    public function refusedCalls(): array
    {
        return [
            'no secret' => ['no secret'],
            'a wrong secret' => ['a wrong secret'],
            'another session id' => ['another session id'],
            'another event' => ['another event'],
            'an expired saved cart' => ['an expired saved cart'],
        ];
    }

    /**
     * Scenario: an address the store does not ship to is refused with a Mollie type, never an empty list
     *   Given a started session
     *   When Mollie posts an address in a country no zone covers
     *   Then the answer is 422 in Mollie's error shape: type is the URL of unsupported-country, unsupported-postal-code or unsupported-address, status 422, a detail
     *   And it has no options
     *
     * @test
     * @group REQ-NEW-EXPRESS-SHIPPING-CALLBACK
     */
    public function it_answers_422_for_an_address_the_store_does_not_ship_to(): void
    {
        // Given
        $session = $this->startedGuestSession();

        // When
        $response = $this->mollieAsks($session, 'FR');

        // Then
        $data = (array) $response->get_data();
        $this->assertSame(422, $response->get_status(), wp_json_encode($data));
        $this->assertArrayNotHasKey('options', $data);
        $this->assertMollieRefusal($data);
    }

    /**
     * Scenario: a cart WooCommerce splits into several shipments has no single list of options to offer
     *   Given a started session and a plugin that splits the cart into two shipping packages
     *   When Mollie posts an address the store otherwise ships to
     *   Then the answer is 422 unsupported-address, never options for one of the packages
     *
     * Mollie takes one option for the whole order, so a price the store cannot compute is not offered.
     *
     * @test
     * @group REQ-NEW-EXPRESS-SHIPPING-CALLBACK
     */
    public function it_answers_422_when_the_cart_splits_into_several_shipments(): void
    {
        // Given
        $session = $this->startedGuestSession();
        $this->addTestFilter('woocommerce_cart_shipping_packages', static function (array $packages): array {
            return array_merge($packages, $packages);
        });

        // When
        $response = $this->mollieAsks($session, 'LU');

        // Then
        $data = (array) $response->get_data();
        $this->assertSame(422, $response->get_status(), wp_json_encode($data));
        $this->assertArrayNotHasKey('options', $data);
        $this->assertMollieRefusal($data, 'unsupported-address');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * @param array<string, string> $billing What the guest typed as billing address; nothing by default.
     * @return array{id: string, callbackUrl: string}
     */
    private function startedGuestSession(array $billing = []): array
    {
        $this->bootExpress();
        $this->actAsGuest();
        $this->cartWith(['simple'], 2);
        if ($billing !== []) {
            $this->fillCheckoutForm($billing, []);
        }
        $session = $this->startedSession();
        $payload = $this->sessionPayloads();
        $callbackUrl = (string) (end($payload)['shipping']['callbackUrl'] ?? '');
        $this->assertNotSame('', $callbackUrl, 'The session must carry shipping.callbackUrl.');

        return ['id' => $session['id'], 'callbackUrl' => $callbackUrl];
    }

    /**
     * Mollie's error body: type is the URL of the error, status is 422, detail is free text for the log.
     *
     * @param array<string, mixed> $data
     */
    private function assertMollieRefusal(array $data, ?string $expectedType = null): void
    {
        $prefix = 'https://docs.mollie.com/reference/errors/';
        $type = (string) ($data['type'] ?? '');
        $this->assertStringStartsWith($prefix, $type);
        $name = substr($type, strlen($prefix));
        $this->assertContains($name, ['unsupported-country', 'unsupported-postal-code', 'unsupported-address']);
        if ($expectedType !== null) {
            $this->assertSame($expectedType, $name);
        }
        $this->assertSame(422, $data['status'] ?? null);
        $this->assertIsString($data['detail'] ?? null);
    }

    /**
     * @param array{id: string, callbackUrl: string} $session
     */
    private function mollieAsks(array $session, string $country): WP_REST_Response
    {
        return $this->post($session['callbackUrl'], $this->body($session['id'], $country));
    }

    /**
     * @return array<string, mixed>
     */
    private function body(string $sessionId, string $country): array
    {
        return [
            'event' => 'shipping-address-changed',
            'sessionId' => $sessionId,
            'shippingAddress' => ['country' => $country, 'postalCode' => self::POSTCODE, 'city' => self::CITY],
        ];
    }

    /**
     * Posts the way Mollie does: to the URL it was given, with no cookie and no nonce.
     *
     * @param array<string, mixed> $body
     */
    private function post(string $url, array $body): WP_REST_Response
    {
        $this->newShopper();
        $this->actAsGuest();
        $path = (string) strtok($url, '?');
        $route = '/' . ltrim(substr($path, strlen(rest_url())), '/');
        parse_str((string) wp_parse_url($url, PHP_URL_QUERY), $query);

        return $this->restRequest('POST', $route, $query + $body);
    }

    /**
     * What follows the route in the callback URL is the secret, wherever the implementation puts it.
     */
    private function secretOf(string $url): string
    {
        $rest = substr($url, strlen(rest_url(self::ROUTE_BASE)));
        $this->assertSame(1, preg_match('/[A-Za-z0-9_-]{16,}/', $rest, $found), 'The callback URL must carry a secret.');

        return $found[0];
    }

    private function withSecret(string $url, string $secret): string
    {
        return str_replace($this->secretOf($url), $secret, $url);
    }

    private function withoutSecret(string $url): string
    {
        return rest_url(self::ROUTE_BASE);
    }

    /**
     * What WooCommerce itself charges this cart for an address: the package's rates after the form
     * holds that address. Order as WooCommerce gives it.
     *
     * @return array<string, array{label: string, amount: string}>
     */
    private function whatWooCommerceCharges(string $country): array
    {
        $this->fillCheckoutForm($this->billing(), $this->shipping($country));
        $this->recalculate();
        $charged = [];
        foreach (WC()->shipping()->get_packages()[0]['rates'] ?? [] as $id => $rate) {
            $charged[(string) $id] = [
                'label' => $rate->get_label(),
                'amount' => $this->decimal((float) $rate->get_cost() + array_sum($rate->get_taxes())),
            ];
        }
        $this->assertNotSame([], $charged, "The scenario needs rates for {$country}.");

        return $charged;
    }

    /**
     * Zone MT (the third) holds eleven flat rates, one more than Mollie accepts.
     */
    private function giveMtElevenRates(): void
    {
        $zone = new \WC_Shipping_Zone($this->zoneIds[2]);
        for ($i = 1; $i <= 11; $i++) {
            $instanceId = $zone->add_shipping_method('flat_rate');
            update_option("woocommerce_flat_rate_{$instanceId}_settings", [
                'enabled' => 'yes',
                'title' => "Rate {$i}",
                'tax_status' => 'taxable',
                'cost' => (string) $i,
            ]);
        }
        \WC_Cache_Helper::get_transient_version('shipping', true);
    }

    /**
     * The cart is saved in a transient whose name holds 'mollie_express'; losing it is what an expired session is.
     */
    private function expireTheSavedCart(): void
    {
        global $wpdb;
        $deleted = (int) $wpdb->query(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_%mollie\\_express%' OR option_name LIKE '\\_transient\\_timeout\\_%mollie\\_express%'"
        );
        $this->assertGreaterThan(0, $deleted, 'The saved cart must be a transient named after mollie_express.');
        wp_cache_flush();
    }
}

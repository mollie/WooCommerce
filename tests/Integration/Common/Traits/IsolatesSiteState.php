<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Integration\Common\Traits;

use Mollie\WooCommerce\Shared\Data;
use WC_Order;

/**
 * What every flow test needs from the real site and must give back: options it overwrote, the
 * cached Mollie methods list, the locale order notes are written in. Shared by PaymentFlowTestCase
 * and ExpressFlowTestCase; the using class calls pinEnglishOrderNotes() in setUp() and
 * restoreSiteState() in tearDown().
 */
trait IsolatesSiteState
{
    /**
     * Option names a test overwrote, mapped to their previous value (null when unset).
     *
     * @var array<string, mixed>
     */
    private array $optionBackups = [];

    /**
     * Sets an option for the duration of one test, restoring the previous value in tearDown().
     *
     * @param mixed $value
     */
    protected function setOptionForTest(string $name, $value): void
    {
        if (!array_key_exists($name, $this->optionBackups)) {
            $existing = get_option($name, null);
            $this->optionBackups[$name] = $existing === false ? null : $existing;
        }

        update_option($name, $value);
    }

    /**
     * Overrides a gateway's stored settings for the duration of one test, merged over whatever the
     * site already has so unrelated keys (enabled, title, surcharges) keep their real values.
     *
     * @param array<string, mixed> $settings
     */
    protected function setGatewaySettingsForTest(string $methodId, array $settings): void
    {
        $optionName = 'mollie_wc_gateway_' . $methodId . '_settings';
        $existing = get_option($optionName, []);

        $this->setOptionForTest($optionName, array_merge(is_array($existing) ? $existing : [], $settings));
    }

    /**
     * Drops the cached Mollie methods list — both the transient and the process-wide static behind
     * it — so the test's Mollie is the single source of truth for which gateways register.
     */
    protected function flushMollieMethodsCache(): void
    {
        global $wpdb;

        $names = $wpdb->get_col(
            "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_%mollie-wc-%'"
        );

        foreach ($names as $name) {
            delete_transient(preg_replace('/^_transient_(timeout_)?/', '', $name));
        }

        $regularMethods = new \ReflectionProperty(Data::class, 'regular_api_methods');
        $regularMethods->setAccessible(true);
        $regularMethods->setValue(null, []);
    }

    /**
     * An unpaid order ready to be paid: pending, with one physical product, and with no Mollie
     * reference of its own — the order factory stamps a random transaction id that would otherwise
     * read as a payment attempt already in flight.
     */
    protected function pendingOrder(string $gatewayId): WC_Order
    {
        $order = $this->getConfiguredOrder($this->customer_id, $gatewayId, ['simple'], [], false);
        $order->set_transaction_id('');
        $order->set_status('pending');
        $order->save();

        return wc_get_order($order->get_id());
    }

    /**
     * Pins order-note rendering to the source language for the duration of a test.
     *
     * Order notes are written through __() against the site locale, which is not English on every
     * dev/CI box, so the note assertions compare against the msgids the source declares.
     */
    private function pinEnglishOrderNotes(): void
    {
        if (function_exists('switch_to_locale')) {
            switch_to_locale('en_US');
        }

        // switch_to_locale() reloads text domains for the new locale; a plugin translation already
        // resident in memory would otherwise keep translating. Unload both domains the notes use.
        unload_textdomain('mollie-payments-for-woocommerce');
        unload_textdomain('woocommerce');
    }

    /**
     * Gives back the options the test overwrote, drops the methods list it may have widened, and
     * restores the locale.
     */
    private function restoreSiteState(): void
    {
        foreach ($this->optionBackups as $name => $previous) {
            if ($previous === null) {
                delete_option($name);
                continue;
            }
            update_option($name, $previous);
        }
        $this->optionBackups = [];

        $this->flushMollieMethodsCache();

        if (function_exists('restore_previous_locale')) {
            restore_previous_locale();
        }
    }

    protected function assertOrderHasNoteContaining(WC_Order $order, string $needle): void
    {
        $notes = wc_get_order_notes(['order_id' => $order->get_id()]);
        $matching = array_filter($notes, static function ($note) use ($needle) {
            return strpos($note->content, $needle) !== false;
        });

        $this->assertNotCount(
            0,
            $matching,
            sprintf('Expected an order note containing "%s".', $needle)
        );
    }

    /**
     * The order total in the string form the Mollie request carries it in.
     */
    protected function formattedTotal(WC_Order $order): string
    {
        return number_format((float) $order->get_total(), 2, '.', '');
    }
}

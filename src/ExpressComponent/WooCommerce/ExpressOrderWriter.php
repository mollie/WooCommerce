<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\ExpressComponent\WooCommerce;

use Mollie\WooCommerce\Payment\ProcessRecordStore;
use Mollie\WooCommerce\ExpressComponent\Rules\ExpressOrderMatch;
use Mollie\WooCommerce\ExpressComponent\Rules\StartOrderDecision;
use Mollie\WooCommerce\ExpressComponent\Rules\Values\RememberedSession;
use Mollie\WooCommerce\ExpressComponent\Rules\Values\FirstSightData;
use Mollie\WooCommerce\ExpressComponent\Rules\Values\ShippingLine;
use WC_Order;
use WC_Order_Item_Shipping;
use WC_Tax;

/**
 * Writes only what differs and notes only once, so a retried webhook changes nothing.
 * Callers pass the order as OrderLock::withFreshOrder() read it.
 */
final class ExpressOrderWriter
{
    private const ADDRESS_FIELDS = [
        'billing' => [
            'first_name', 'last_name', 'company', 'address_1', 'address_2', 'city', 'state', 'postcode', 'country',
            'email', 'phone',
        ],
        'shipping' => [
            'first_name', 'last_name', 'company', 'address_1', 'address_2', 'city', 'state', 'postcode', 'country',
            'phone',
        ],
    ];

    private ProcessRecordStore $records;

    public function __construct(?ProcessRecordStore $records = null)
    {
        $this->records = $records ?? new ProcessRecordStore();
    }

    public function stampNewOrder(
        WC_Order $order,
        RememberedSession $session,
        string $mode,
        string $gatewayId,
        string $wallet
    ): void {

        $changed = $this->setCreatedVia($order, StartOrderDecision::CREATED_VIA);
        $changed = $this->setMeta($order, '_mollie_express_ref', $session->expressRef()) || $changed;
        $changed = $this->setMeta($order, '_mollie_express_session_id', $session->sessionId()) || $changed;
        $changed = $this->setMeta($order, '_mollie_express_expires_at', (string) $session->expiresAt()) || $changed;
        $changed = $this->setMeta($order, '_mollie_payment_mode', $mode) || $changed;
        $changed = $this->setStatus($order, 'pending') || $changed;
        $changed = $this->setPaymentMethod($order, $gatewayId) || $changed;

        $this->finish($order, $changed, [__('Express checkout started', 'mollie-payments-for-woocommerce')]);
    }

    public function recordFirstSight(WC_Order $order, FirstSightData $firstSight): void
    {
        $notes = [];
        $changed = $this->setMeta($order, '_mollie_payment_id', $firstSight->paymentId());
        $changed = $this->setTransactionId($order, $firstSight->paymentId()) || $changed;
        $changed = $this->setMeta($order, '_mollie_payment_mode', $firstSight->mode()) || $changed;

        $gatewayId = $firstSight->gatewayId();
        if ($gatewayId !== null) {
            $changed = $this->setPaymentMethod($order, $gatewayId) || $changed;
        } elseif ($firstSight->unmatchedMethod() !== null) {
            $notes[] = strtr(
                /* translators: {method} is the Mollie payment method id, e.g. creditcard. */
                __('Express checkout was paid with the Mollie method {method}, which has no payment method in this shop; the order keeps its payment method.', 'mollie-payments-for-woocommerce'),
                ['{method}' => $firstSight->unmatchedMethod()]
            );
        }

        $changed = $this->setAddress($order, 'billing', $firstSight->billing()) || $changed;
        $shipping = $firstSight->shipping();
        if ($shipping !== null) {
            $changed = $this->setAddress($order, 'shipping', $shipping) || $changed;
        }
        $line = $firstSight->shippingLine();
        if ($line !== null) {
            $this->addShippingLine($order, $line);
            $changed = true;
        }

        $this->finish($order, $changed, $notes);
    }

    /**
     * The order is made from the cart before the wallet's shipping option is known: whatever shipping the cart
     * carried (shipping lines, their cost and their tax) goes, so the order costs what the session was priced
     * for, items and their VAT. The first webhook adds the option the shopper chose.
     */
    public function dropShipping(WC_Order $order): void
    {
        $cost = (float) $order->get_shipping_total('edit');
        $tax = (float) $order->get_shipping_tax('edit');
        $lines = $order->get_items('shipping');
        if ($lines === [] && $cost === 0.0 && $tax === 0.0) {
            return;
        }

        foreach (array_keys($lines) as $itemId) {
            $order->remove_item($itemId);
        }
        foreach ($order->get_taxes() as $taxItem) {
            $taxItem->set_shipping_tax_total('0');
        }
        $order->set_shipping_total('0');
        $order->set_shipping_tax('0');
        $order->set_total(wc_format_decimal((float) $order->get_total('edit') - $cost - $tax, wc_get_price_decimals()));
        $order->save();
    }

    /**
     * The option the shopper chose in the wallet, as it was paid: cost and VAT as the rule split them, no
     * recalculation, so the total is exactly what Mollie took.
     */
    private function addShippingLine(WC_Order $order, ShippingLine $line): void
    {
        [$methodId, $instanceId] = array_pad(explode(':', $line->rateId(), 2), 2, '');
        $taxItem = $this->shippingTaxItem($order);
        $net = $line->net()->toDecimal();
        $tax = $line->tax()->toDecimal();

        $item = new WC_Order_Item_Shipping();
        $item->set_method_title($line->label());
        $item->set_method_id($methodId);
        $item->set_instance_id($instanceId);
        $item->set_total($net);
        if ($taxItem !== null && $line->tax()->minorUnits() > 0) {
            $item->set_taxes(['total' => [$taxItem->get_rate_id() => $tax]]);
            $taxItem->set_shipping_tax_total($tax);
        }
        $order->add_item($item);
        $order->set_shipping_total($net);
        $order->set_shipping_tax($tax);
        $order->set_total(wc_format_decimal((float) $order->get_total('edit') + (float) $line->gross()->toDecimal(), wc_get_price_decimals()));
    }

    private function shippingTaxItem(WC_Order $order): ?\WC_Order_Item_Tax
    {
        foreach ($order->get_taxes() as $taxItem) {
            $row = WC_Tax::_get_tax_rate($taxItem->get_rate_id(), OBJECT);
            if (is_object($row) && (int) $row->tax_rate_shipping === 1) {
                return $taxItem;
            }
        }

        return null;
    }

    /**
     * @param string $handledEvent "<session or payment id>:<status>"
     */
    public function cancelAbandoned(WC_Order $order, string $handledEvent = ''): void
    {
        // Before the status, so both go in the same save.
        $changed = $this->recordCancelledByCleanup($order, $handledEvent);
        $changed = $this->setStatus($order, 'cancelled') || $changed;

        $this->finish(
            $order,
            $changed,
            [__('Express checkout was started and not completed', 'mollie-payments-for-woocommerce')]
        );
    }

    public function recordRefusedPayment(
        WC_Order $order,
        string $paymentId,
        string $amount,
        string $currency,
        string $reason
    ): void {

        $changed = false;
        if ($reason === ExpressOrderMatch::PAID_AFTER_CANCEL) {
            $changed = $this->recordOpenQuestion($order, $reason, $paymentId);
        }

        $this->finish($order, $changed, [
            strtr(
                /* translators: {payment} payment id, {amount} {currency} its amount, {reason} reason code. */
                __('Mollie payment {payment} ({amount} {currency}) was not applied to this order ({reason}). Check it in your Mollie dashboard and refund it or handle it by hand.', 'mollie-payments-for-woocommerce'),
                ['{payment}' => $paymentId, '{amount}' => $amount, '{currency}' => $currency, '{reason}' => $reason]
            ),
        ]);
    }

    /**
     * @param array<int, string> $notes
     */
    private function finish(WC_Order $order, bool $changed, array $notes): void
    {
        if ($changed) {
            $order->save();
        }

        $existing = $notes === [] ? [] : $this->existingNotes($order->get_id());
        foreach ($notes as $text) {
            if (!in_array($text, $existing, true)) {
                $order->add_order_note($text);
                $existing[] = $text;
            }
        }
    }

    private function recordCancelledByCleanup(WC_Order $order, string $handledEvent): bool
    {
        $stored = $this->records->read($order);
        $record = $stored->withCancelledBy('cleanup');
        if ($handledEvent !== '') {
            $record = $record->withProcessed($handledEvent);
        }
        if ($order->meta_exists(ProcessRecordStore::META_KEY) && $record->toArray() === $stored->toArray()) {
            return false;
        }
        $this->records->write($order, $record);

        return true;
    }

    private function recordOpenQuestion(WC_Order $order, string $question, string $paymentId): bool
    {
        $stored = $this->records->read($order);
        $record = $stored->withOpen($question, $paymentId);
        if ($order->meta_exists(ProcessRecordStore::META_KEY) && $record->toArray() === $stored->toArray()) {
            return false;
        }
        $this->records->write($order, $record);

        return true;
    }

    private function setMeta(WC_Order $order, string $key, string $value): bool
    {
        if ($order->meta_exists($key) && (string) $order->get_meta($key) === $value) {
            return false;
        }
        $order->update_meta_data($key, $value);

        return true;
    }

    private function setTransactionId(WC_Order $order, string $transactionId): bool
    {
        if ($order->get_transaction_id() === $transactionId) {
            return false;
        }
        $order->set_transaction_id($transactionId);

        return true;
    }

    private function setStatus(WC_Order $order, string $status): bool
    {
        $status = (string) preg_replace('/^wc-/', '', $status);
        if ($order->get_status() === $status) {
            return false;
        }
        $order->set_status($status);

        return true;
    }

    private function setCreatedVia(WC_Order $order, string $createdVia): bool
    {
        if ($order->get_created_via() === $createdVia) {
            return false;
        }
        $order->set_created_via($createdVia);

        return true;
    }

    private function setPaymentMethod(WC_Order $order, string $gatewayId): bool
    {
        if ($order->get_payment_method() === $gatewayId) {
            return false;
        }

        $order->set_payment_method($gatewayId);
        $gateways = WC()->payment_gateways()->payment_gateways();
        if (isset($gateways[$gatewayId])) {
            $order->set_payment_method_title($gateways[$gatewayId]->get_title());
        }

        return true;
    }

    /**
     * Allowlisted fields only: a value from Mollie never reaches another order setter.
     *
     * @param array<string, string> $fields Field names without the billing_/shipping_ prefix.
     */
    private function setAddress(WC_Order $order, string $type, array $fields): bool
    {
        $changed = false;
        foreach ($fields as $field => $value) {
            if (!in_array($field, self::ADDRESS_FIELDS[$type], true)) {
                continue;
            }
            $getter = [$order, 'get_' . $type . '_' . $field];
            $setter = [$order, 'set_' . $type . '_' . $field];
            if (!is_callable($getter) || !is_callable($setter) || (string) $getter() === $value) {
                continue;
            }
            $setter($value);
            $changed = true;
        }

        return $changed;
    }

    /**
     * @return array<int, string>
     */
    private function existingNotes(int $orderId): array
    {
        return array_map(static function ($note): string {
            return (string) $note->content;
        }, wc_get_order_notes(['order_id' => $orderId]));
    }
}

<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\ExpressComponent\WooCommerce;

use Mollie\WooCommerce\Log\EventLog;
use Mollie\WooCommerce\Payment\ProcessRecordStore;
use Mollie\WooCommerce\ExpressComponent\Rules\StartOrderDecision;
use Mollie\WooCommerce\ExpressComponent\Rules\Values\RememberedSession;
use Mollie\WooCommerce\ExpressComponent\Rules\Values\FirstSightData;
use WC_Order;
/**
 * Values are written only when they differ and notes only when absent, so a retried webhook
 * changes nothing. Callers pass the order read under OrderLock::withFreshOrder().
 */
final class ExpressOrderWriter
{
    private const ADDRESS_FIELDS = ['billing' => ['first_name', 'last_name', 'company', 'address_1', 'address_2', 'city', 'state', 'postcode', 'country', 'email', 'phone'], 'shipping' => ['first_name', 'last_name', 'company', 'address_1', 'address_2', 'city', 'state', 'postcode', 'country', 'phone']];
    private ProcessRecordStore $records;
    public function __construct(private EventLog $log, ?ProcessRecordStore $records = null)
    {
        $this->records = $records ?? new ProcessRecordStore();
    }
    /** The payment method is provisional: the first webhook corrects it to the wallet that paid. */
    public function stampNewOrder(WC_Order $order, RememberedSession $session, string $mode, string $gatewayId, string $wallet): void
    {
        $started = microtime(\true);
        $changed = $this->setCreatedVia($order, StartOrderDecision::CREATED_VIA);
        $changed = $this->setMeta($order, '_mollie_express_ref', $session->expressRef()) || $changed;
        $changed = $this->setMeta($order, '_mollie_express_session_id', $session->sessionId()) || $changed;
        $changed = $this->setMeta($order, '_mollie_express_expires_at', (string) $session->expiresAt()) || $changed;
        $changed = $this->setMeta($order, '_mollie_payment_mode', $mode) || $changed;
        $changed = $this->setStatus($order, 'pending') || $changed;
        $changed = $this->setPaymentMethod($order, $gatewayId) || $changed;
        $this->finish($order, $changed, [__('Express checkout started', 'mollie-payments-for-woocommerce')], $started);
    }
    public function recordFirstSight(WC_Order $order, FirstSightData $firstSight): void
    {
        $started = microtime(\true);
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
        $this->finish($order, $changed, $notes, $started);
    }
    /**
     * @param string $handledEvent "<session or payment id>:<status>"
     */
    public function cancelAbandoned(WC_Order $order, string $handledEvent = ''): void
    {
        $started = microtime(\true);
        // Before the status, so both go in the same save.
        $changed = $this->recordCancelledByCleanup($order, $handledEvent);
        $changed = $this->setStatus($order, 'cancelled') || $changed;
        $this->finish($order, $changed, [__('Express checkout was started and not completed', 'mollie-payments-for-woocommerce')], $started);
    }
    /**
     * @param array<int, string> $notes
     */
    private function finish(WC_Order $order, bool $changed, array $notes, float $started): void
    {
        if ($changed) {
            $order->save();
        }
        $noted = \false;
        $existing = $notes === [] ? [] : $this->existingNotes($order->get_id());
        foreach ($notes as $text) {
            if (!in_array($text, $existing, \true)) {
                $order->add_order_note($text);
                $existing[] = $text;
                $noted = \true;
            }
        }
        if ($changed || $noted) {
            $this->log->info('order.written', ['order' => $order->get_id(), 'status' => $order->get_status(), 'ms' => (int) round((microtime(\true) - $started) * 1000)]);
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
            return \false;
        }
        $this->records->write($order, $record);
        return \true;
    }
    private function setMeta(WC_Order $order, string $key, string $value): bool
    {
        if ($order->meta_exists($key) && (string) $order->get_meta($key) === $value) {
            return \false;
        }
        $order->update_meta_data($key, $value);
        return \true;
    }
    private function setTransactionId(WC_Order $order, string $transactionId): bool
    {
        if ($order->get_transaction_id() === $transactionId) {
            return \false;
        }
        $order->set_transaction_id($transactionId);
        return \true;
    }
    private function setStatus(WC_Order $order, string $status): bool
    {
        $status = (string) preg_replace('/^wc-/', '', $status);
        if ($order->get_status() === $status) {
            return \false;
        }
        $order->set_status($status);
        return \true;
    }
    private function setCreatedVia(WC_Order $order, string $createdVia): bool
    {
        if ($order->get_created_via() === $createdVia) {
            return \false;
        }
        $order->set_created_via($createdVia);
        return \true;
    }
    private function setPaymentMethod(WC_Order $order, string $gatewayId): bool
    {
        if ($order->get_payment_method() === $gatewayId) {
            return \false;
        }
        $order->set_payment_method($gatewayId);
        $gateways = WC()->payment_gateways()->payment_gateways();
        if (isset($gateways[$gatewayId])) {
            $order->set_payment_method_title($gateways[$gatewayId]->get_title());
        }
        return \true;
    }
    /**
     * Allowlisted fields only: a value from Mollie never reaches another order setter.
     *
     * @param array<string, string> $fields Field names without the billing_/shipping_ prefix.
     */
    private function setAddress(WC_Order $order, string $type, array $fields): bool
    {
        $changed = \false;
        foreach ($fields as $field => $value) {
            if (!in_array($field, self::ADDRESS_FIELDS[$type], \true)) {
                continue;
            }
            $getter = [$order, 'get_' . $type . '_' . $field];
            $setter = [$order, 'set_' . $type . '_' . $field];
            if (!is_callable($getter) || !is_callable($setter) || (string) $getter() === $value) {
                continue;
            }
            $setter($value);
            $changed = \true;
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

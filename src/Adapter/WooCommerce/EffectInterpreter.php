<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Adapter\WooCommerce;

use InvalidArgumentException;
use Mollie\WooCommerce\Adapter\WordPress\EventLog;
use Mollie\WooCommerce\Adapter\WordPress\OrderLock;
use Mollie\WooCommerce\Adapter\WordPress\OrderLockTimeout;
use Mollie\WooCommerce\Core\Types\Effect;
use WC_Order;
/**
 * The only writer of order status, notes and _mollie_* meta for new code (blueprint chokepoint 3, ADR-007).
 *
 * It takes the per-order lock, re-reads the order inside it with the object cache bypassed, applies
 * the effects, saves once and logs one event. Applying the same effects again changes nothing, so a
 * webhook Mollie retries is harmless. If the lock cannot be taken, nothing is written and
 * OrderLockTimeout is thrown.
 */
final class EffectInterpreter
{
    private const ADDRESS_FIELDS = ['billing' => ['first_name', 'last_name', 'company', 'address_1', 'address_2', 'city', 'state', 'postcode', 'country', 'email', 'phone'], 'shipping' => ['first_name', 'last_name', 'company', 'address_1', 'address_2', 'city', 'state', 'postcode', 'country', 'phone']];
    private OrderLock $lock;
    private EventLog $log;
    public function __construct(OrderLock $lock, EventLog $log)
    {
        $this->lock = $lock;
        $this->log = $log;
    }
    /**
     * @param array<int, Effect> $effects
     * @return WC_Order The order as it was written, freshly read.
     *
     * @throws OrderLockTimeout Retryable; nothing was written.
     * @throws InvalidArgumentException For an unsaved order or an effect this interpreter cannot render.
     */
    public function apply(WC_Order $order, array $effects): WC_Order
    {
        $orderId = $order->get_id();
        if ($orderId <= 0) {
            throw new InvalidArgumentException('Effects can only be applied to a saved order.');
        }
        // Rendered before the lock: an unknown message key is a programming error, not a reason to hold it.
        $notes = $this->renderedNotes($effects);
        $started = microtime(\true);
        try {
            return $this->lock->withLock((string) $orderId, function () use ($orderId, $effects, $notes, $started): WC_Order {
                return $this->applyLocked($orderId, $effects, $notes, $started);
            });
        } catch (OrderLockTimeout $timeout) {
            $this->log->warning('effects.lock_timeout', ['order' => $orderId, 'ms' => $this->elapsed($started)]);
            throw $timeout;
        }
    }
    /**
     * @param array<int, Effect> $effects
     * @param array<int, string> $notes
     */
    private function applyLocked(int $orderId, array $effects, array $notes, float $started): WC_Order
    {
        $order = $this->freshOrder($orderId);
        $changed = \false;
        foreach ($effects as $effect) {
            $changed = $this->applyEffect($order, $effect) || $changed;
        }
        if ($changed) {
            $order->save();
        }
        $noted = \false;
        $existing = $this->existingNotes($orderId);
        foreach ($notes as $text) {
            if (!in_array($text, $existing, \true)) {
                $order->add_order_note($text);
                $existing[] = $text;
                $noted = \true;
            }
        }
        if ($changed || $noted) {
            $this->log->info('effects.applied', ['order' => $orderId, 'status' => $order->get_status(), 'ms' => $this->elapsed($started)]);
        }
        return $order;
    }
    /**
     * @return bool Whether the order object now differs from what is stored.
     */
    private function applyEffect(WC_Order $order, Effect $effect): bool
    {
        $data = $effect->data();
        return match ($effect->type()) {
            Effect::SET_META => $this->setMeta($order, $data['key'], $data['value']),
            Effect::DELETE_META => $this->deleteMeta($order, $data['key']),
            Effect::SET_TRANSACTION_ID => $this->setTransactionId($order, $data['transactionId']),
            Effect::SET_PAYMENT_METHOD => $this->setPaymentMethod($order, $data['gatewayId']),
            Effect::SET_STATUS => $this->setStatus($order, $data['status']),
            Effect::SET_ADDRESS => $this->setAddress($order, $data['addressType'], $data['fields']),
            Effect::ADD_NOTE => \false,
            // Notes are written after the save, see renderedNotes().
            default => throw new InvalidArgumentException(sprintf('Unknown effect type "%s".', $effect->type())),
        };
    }
    private function setMeta(WC_Order $order, string $key, string $value): bool
    {
        if ($order->meta_exists($key) && (string) $order->get_meta($key) === $value) {
            return \false;
        }
        $order->update_meta_data($key, $value);
        return \true;
    }
    private function deleteMeta(WC_Order $order, string $key): bool
    {
        if (!$order->meta_exists($key)) {
            return \false;
        }
        $order->delete_meta_data($key);
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
        $status = preg_replace('/^wc-/', '', $status);
        if ($order->get_status() === $status) {
            return \false;
        }
        $order->set_status($status);
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
     * @param array<string, string> $fields
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
     * The order notes this interpreter may write, keyed by message key. The core carries keys and
     * parameters; the words, and their translation, live here.
     *
     * @return array<string, string>
     */
    private function messages(): array
    {
        return ['express.order.created' => __('Order created by Mollie express checkout ({wallet}).', 'mollie-payments-for-woocommerce')];
    }
    /**
     * @param array<int, Effect> $effects
     * @return array<int, string>
     */
    private function renderedNotes(array $effects): array
    {
        $messages = $this->messages();
        $rendered = [];
        foreach ($effects as $effect) {
            if ($effect->type() !== Effect::ADD_NOTE) {
                continue;
            }
            $data = $effect->data();
            if (!isset($messages[$data['messageKey']])) {
                throw new InvalidArgumentException(sprintf('No message for note key "%s".', $data['messageKey']));
            }
            $replacements = [];
            foreach ($data['params'] as $name => $value) {
                $replacements['{' . $name . '}'] = (string) $value;
            }
            $rendered[] = strtr($messages[$data['messageKey']], $replacements);
        }
        return $rendered;
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
    /**
     * The order as stored, not as this request last saw it: another request may have written it
     * while this one waited for the lock.
     */
    private function freshOrder(int $orderId): WC_Order
    {
        clean_post_cache($orderId);
        wp_cache_delete(WC_Order::generate_meta_cache_key($orderId, 'orders'), 'orders');
        $order = wc_get_order($orderId);
        $dataStore = $order instanceof WC_Order ? $order->get_data_store() : null;
        if ($dataStore !== null && is_callable([$dataStore, 'clear_cached_data'])) {
            $dataStore->clear_cached_data([$orderId]);
            $order = wc_get_order($orderId);
        }
        if (!$order instanceof WC_Order) {
            throw new InvalidArgumentException('The order no longer exists.');
        }
        return $order;
    }
    private function elapsed(float $started): int
    {
        return (int) round((microtime(\true) - $started) * 1000);
    }
}

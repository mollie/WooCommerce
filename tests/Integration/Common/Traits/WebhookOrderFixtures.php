<?php

declare(strict_types=1);

namespace Mollie\WooCommerceTests\Integration\Common\Traits;

use Mollie\WooCommerceTests\Integration\Common\Doubles\CanaryData;
use Psr\Container\ContainerInterface;
use WC_Order;

/**
 * Needs ExpressCheckoutFixtures for addTestFilter().
 */
trait WebhookOrderFixtures
{
    private ?ContainerInterface $container = null;

    /**
     * @var array<int, string>
     */
    private array $hooksFired = [];

    private function recordWebhookHooks(): void
    {
        $this->hooksFired = [];
        $this->addTestFilter(self::PLUGIN_ID . '_before_webhook_payment_action', function (): void {
            $this->hooksFired[] = 'before_webhook_payment_action';
        });
        $this->addTestFilter(self::PLUGIN_ID . '_after_webhook_action', function (): void {
            $this->hooksFired[] = 'after_webhook_action';
        });
        $this->addTestFilter('woocommerce_payment_complete', function (): void {
            $this->hooksFired[] = 'woocommerce_payment_complete';
        });
        $this->addTestFilter('woocommerce_order_status_changed', function ($orderId, $from, $to): void {
            $this->hooksFired[] = "woocommerce_order_status_changed:{$from}>{$to}";
        }, 10, 3);
    }

    private function boot(): ContainerInterface
    {
        if ($this->container === null) {
            $this->removePluginListenersOfEarlierBoots();
            $this->container = $this->bootExpress();
        }

        return $this->container;
    }

    private function removePluginListenersOfEarlierBoots(): void
    {
        foreach ($GLOBALS['wp_filter'] as $hook => $listeners) {
            foreach ($listeners->callbacks as $priority => $callbacks) {
                foreach ($callbacks as $callback) {
                    if ($this->isPluginCallback($callback['function'])) {
                        remove_filter((string) $hook, $callback['function'], $priority);
                    }
                }
            }
        }
    }

    /**
     * @param mixed $function
     */
    private function isPluginCallback($function): bool
    {
        $owner = null;
        if (is_array($function) && is_object($function[0] ?? null)) {
            $owner = get_class($function[0]);
        } elseif ($function instanceof \Closure) {
            $closure = new \ReflectionFunction($function);
            $bound = $closure->getClosureThis();
            $scope = $closure->getClosureScopeClass();
            $owner = $bound !== null ? get_class($bound) : ($scope !== null ? $scope->getName() : null);
        }

        return $owner !== null
            && (strpos($owner, 'Mollie\\WooCommerce\\') === 0 || strpos($owner, 'Inpsyde\\PaymentGateway\\') === 0);
    }

    /**
     * @return array{0: WC_Order, 1: array<string, mixed>}
     */
    private function orderWithPayment(string $paymentStatus, string $gatewayId = 'mollie_wc_gateway_paypal'): array
    {
        $this->boot();
        $order = $this->pendingOrder($gatewayId);
        $payment = $this->paymentFor($order, ['status' => $paymentStatus, 'method' => 'paypal']);
        $order->update_meta_data('_mollie_payment_id', $payment['id']);
        $order->set_transaction_id($payment['id']);
        $order->save();
        $this->logger()->reset();
        $this->hooksFired = [];

        return [$this->fresh($order), $payment];
    }

    /**
     * @param array<string, mixed> $outcome
     * @return array<string, mixed>
     */
    private function paymentFor(WC_Order $order, array $outcome): array
    {
        $total = $this->formattedTotal($order);
        $payload = [
            'amount' => ['currency' => 'EUR', 'value' => $total],
            'description' => 'Order ' . $order->get_id(),
            'lines' => [[
                'description' => 'Everything',
                'quantity' => 1,
                'unitPrice' => ['currency' => 'EUR', 'value' => $total],
                'totalAmount' => ['currency' => 'EUR', 'value' => $total],
            ]],
            'redirectUrl' => 'https://shop.example/checkout/order-received/',
            'payment' => ['webhookUrl' => 'https://shop.example/wp-json/mollie/v1/webhook'],
            'metadata' => ['order_id' => $order->get_id()],
        ];
        $client = $this->boot()->get('SDK.api_helper')->getApiClient(CanaryData::LIVE_API_KEY);
        $session = $client->performHttpCall('POST', 'sessions', (string) wp_json_encode($payload));

        return $this->fakeMollie()->completeSession($session->id, $outcome);
    }

    private function fresh(WC_Order $order): WC_Order
    {
        clean_post_cache($order->get_id());
        wp_cache_delete(WC_Order::generate_meta_cache_key($order->get_id(), 'orders'), 'orders');
        $fresh = wc_get_order($order->get_id());
        $this->assertInstanceOf(WC_Order::class, $fresh);

        return $fresh;
    }

    /**
     * @return array{status: string, transaction_id: string, meta: array<string, array<int, mixed>>}
     */
    private function freshState(WC_Order $order): array
    {
        $fresh = $this->fresh($order);
        $meta = [];
        foreach ($fresh->get_meta_data() as $item) {
            $data = $item->get_data();
            $meta[$data['key']][] = $data['value'];
        }
        ksort($meta);

        return [
            'status' => $fresh->get_status(),
            'transaction_id' => $fresh->get_transaction_id(),
            'meta' => $meta,
        ];
    }

    /**
     * Skips the meta the order emails write.
     *
     * @param array<string, array<int, mixed>> $before
     * @param array<string, array<int, mixed>> $after
     * @return array<int, string>
     */
    private function metaKeysChanged(array $before, array $after): array
    {
        $changed = [];
        foreach (array_unique(array_merge(array_keys($before), array_keys($after))) as $key) {
            if ($key === '_mollie_payment_instructions') {
                continue;
            }
            if (($before[$key] ?? null) !== ($after[$key] ?? null)) {
                $changed[] = (string) $key;
            }
        }
        sort($changed);

        return $changed;
    }

    /**
     * @return array<int, int>
     */
    private function noteIds(WC_Order $order): array
    {
        return array_map(static function ($note): int {
            return (int) $note->id;
        }, wc_get_order_notes(['order_id' => $order->get_id()]));
    }

    /**
     * Oldest first, the payment id as {payment}.
     *
     * @param array<int, int> $idsBefore
     * @return array<int, string>
     */
    private function notesAddedSince(WC_Order $order, array $idsBefore, string $paymentId): array
    {
        $added = array_filter(wc_get_order_notes(['order_id' => $order->get_id()]), static function ($note) use ($idsBefore): bool {
            return !in_array((int) $note->id, $idsBefore, true);
        });
        usort($added, static function ($a, $b): int {
            return (int) $a->id <=> (int) $b->id;
        });

        $notes = array_map(static function ($note) use ($paymentId): string {
            return str_replace($paymentId, '{payment}', (string) $note->content);
        }, $added);

        return array_values(array_filter($notes, static function (string $note): bool {
            return !self::isEmailSentNote($note);
        }));
    }

    private static function isEmailSentNote(string $note): bool
    {
        return preg_match('/^Email ".+" sent\.$/', $note) === 1;
    }

    /**
     * @return array<int, string>
     */
    private function notes(WC_Order $order): array
    {
        return array_map(static function ($note): string {
            return (string) $note->content;
        }, wc_get_order_notes(['order_id' => $order->get_id()]));
    }

    /**
     * @return array<int, string>
     */
    private function notesContaining(WC_Order $order, string $needle): array
    {
        return array_values(array_filter($this->notes($order), static function (string $note) use ($needle): bool {
            return stripos($note, $needle) !== false;
        }));
    }

    private function fired(string $hook): int
    {
        return count(array_keys($this->hooksFired, $hook, true));
    }

    private function paymentFetches(string $paymentId): int
    {
        return count(array_filter($this->fakeMollie()->requests('GET', 'payments/' . $paymentId), static function (array $request) use ($paymentId): bool {
            return $request['path'] === 'payments/' . $paymentId;
        }));
    }
}

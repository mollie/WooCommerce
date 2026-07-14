<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Payment\Request\Middleware;

use Mollie\WooCommerce\Payment\Webhooks\RestApi;
use WC_Order;
/**
 * Class UrlMiddleware
 *
 * Middleware to handle URL modifications for payment requests.
 *
 * @package Mollie\WooCommerce\Payment\Request\Middleware
 */
class UrlMiddleware implements \Mollie\WooCommerce\Payment\Request\Middleware\RequestMiddlewareInterface
{
    /**
     * @var string The plugin ID.
     */
    private $pluginId;
    /**
     * @var mixed The logger instance.
     */
    private $logger;
    /**
     * UrlMiddleware constructor.
     *
     * @param string $pluginId The plugin ID.
     * @param mixed $logger The logger instance.
     */
    public function __construct($pluginId, $logger)
    {
        $this->pluginId = $pluginId;
        $this->logger = $logger;
    }
    /**
     * Invoke the middleware.
     *
     * @param array<string, mixed> $requestData The request data to be modified.
     * @param WC_Order $order The WooCommerce order object.
     * @param string $context Additional context for the middleware.
     * @param callable $next The next middleware to be called.
     * @return array<string, mixed> The modified request data.
     */
    public function __invoke(array $requestData, WC_Order $order, $context, $next): array
    {
        $gateway = wc_get_payment_gateway_by_order($order);
        if ($gateway) {
            $returnUrl = $gateway->get_return_url($order);
            $returnUrl = $this->getReturnUrl($order, $returnUrl);
            $webhookUrl = $this->getWebhookUrl($order, $gateway->id);
            $requestData['redirectUrl'] = $returnUrl;
            $requestData['webhookUrl'] = $webhookUrl;
        }
        return $next($requestData, $order, $context);
    }
    /**
     * Get the URL to return to on Mollie return.
     * Saves the return redirect and failed redirect, so we save the page language in case there is one set.
     * For example 'http://mollie-wc.docker.myhost/wc-api/mollie_return/?order_id=89&key=wc_order_eFZyH8jki6fge'.
     *
     * @param WC_Order $order The order processed.
     * @param string $returnUrl The base return URL.
     * @return string The URL with order ID and key as params.
     */
    private function getReturnUrl(WC_Order $order, string $returnUrl): string
    {
        $returnUrl = untrailingslashit($returnUrl);
        $returnUrl = $this->asciiDomainName($returnUrl);
        $orderId = $order->get_id();
        $orderKey = $order->get_order_key();
        $onMollieReturn = 'onMollieReturn';
        $returnUrl = $this->appendOrderArgumentsToUrl($orderId, $orderKey, $returnUrl, $onMollieReturn);
        $returnUrl = untrailingslashit($returnUrl);
        $this->logger->debug(" Order {$orderId} returnUrl: {$returnUrl}", [\true]);
        return apply_filters($this->pluginId . '_return_url', $returnUrl, $order);
    }
    /**
     * Get the webhook URL.
     * For example 'http://mollie-wc.docker.myhost/wc-api/mollie_return/mollie_wc_gateway_bancontact/?order_id=89&key=wc_order_eFZyH8jki6fge'.
     *
     * @param WC_Order $order The order processed.
     * @param string $gatewayId The payment gateway ID.
     * @return string The URL with gateway and order ID and key as params.
     */
    public function getWebhookUrl(WC_Order $order, string $gatewayId): string
    {
        $webhookUrl = get_rest_url(null, RestApi::ROUTE_NAMESPACE . '/' . RestApi::WEBHOOK_ROUTE);
        if (!$webhookUrl || !wc_is_valid_url($webhookUrl) || apply_filters('mollie_wc_gateway_disable_rest_webhook', \false)) {
            $webhookUrl = WC()->api_request_url($gatewayId);
            $webhookUrl = untrailingslashit($webhookUrl);
            $webhookUrl = $this->asciiDomainName($webhookUrl);
            $orderId = $order->get_id();
            $orderKey = $order->get_order_key();
            $webhookUrl = $this->appendOrderArgumentsToUrl($orderId, $orderKey, $webhookUrl);
            $webhookUrl = untrailingslashit($webhookUrl);
        }
        $this->logger->debug(" Order {$order->get_id()} webhookUrl: {$webhookUrl}", [\true]);
        return apply_filters($this->pluginId . '_webhook_url', $webhookUrl, $order);
    }
    /**
     * Convert the domain name in a URL to ASCII.
     *
     * @param string $url The URL to convert.
     * @return string The URL with the domain name in ASCII.
     */
    protected function asciiDomainName(string $url): string
    {
        $parsed = wp_parse_url($url);
        $scheme = isset($parsed['scheme']) ? $parsed['scheme'] : '';
        $domain = isset($parsed['host']) ? $parsed['host'] : \false;
        $query = isset($parsed['query']) ? $parsed['query'] : '';
        $path = isset($parsed['path']) ? $parsed['path'] : '';
        if (!$domain) {
            return $url;
        }
        if (function_exists('idn_to_ascii')) {
            $domain = $this->idnEncodeDomain($domain);
            $url = $scheme . "://" . $domain . $path . '?' . $query;
        }
        return $url;
    }
    /**
     * Append order arguments to a URL.
     *
     * @param int $order_id The order ID.
     * @param string $order_key The order key.
     * @param string $webhook_url The base webhook URL.
     * @param string $filterFlag An optional filter flag.
     * @return string The URL with appended order arguments.
     */
    protected function appendOrderArgumentsToUrl(int $order_id, string $order_key, string $webhook_url, string $filterFlag = ''): string
    {
        $webhook_url = add_query_arg(['order_id' => $order_id, 'key' => $order_key, 'filter_flag' => $filterFlag], $webhook_url);
        return $webhook_url;
    }
    /**
     * Encode a domain name to ASCII.
     *
     * @param string $domain The domain name to encode.
     * @return string The encoded domain name.
     */
    protected function idnEncodeDomain(string $domain): string
    {
        if (defined('IDNA_NONTRANSITIONAL_TO_ASCII') && defined('INTL_IDNA_VARIANT_UTS46')) {
            $domain = idn_to_ascii($domain, \IDNA_NONTRANSITIONAL_TO_ASCII, \INTL_IDNA_VARIANT_UTS46) ? idn_to_ascii($domain, \IDNA_NONTRANSITIONAL_TO_ASCII, \INTL_IDNA_VARIANT_UTS46) : $domain;
        } else {
            $domain = idn_to_ascii($domain) ? idn_to_ascii($domain) : $domain;
        }
        return $domain;
    }
}

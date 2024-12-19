<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\Payment\Request\Decorators;

use WC_Order;

class UrlDecorator implements RequestDecoratorInterface
{
    private $pluginId;
    /**
     * @var mixed
     */
    private $logger;

    public function __construct($pluginId, $logger)
    {
        $this->pluginId = $pluginId;
        $this->logger = $logger;
    }

    public function decorate(array $requestData, WC_Order $order, $context = null): array
    {
        $gateway = wc_get_payment_gateway_by_order($order);
        if ($gateway) {
            $returnUrl = $gateway->get_return_url($order);
            $returnUrl = $this->getReturnUrl($order, $returnUrl);
            $webhookUrl = $this->getWebhookUrl($order, $gateway->id);

            $requestData['redirectUrl'] = $returnUrl;
            $requestData['webhookUrl'] = $webhookUrl;
        }
        return $requestData;
    }

    /**
     * Get the url to return to on Mollie return
     * saves the return redirect and failed redirect, so we save the page language in case there is one set
     * For example 'http://mollie-wc.docker.myhost/wc-api/mollie_return/?order_id=89&key=wc_order_eFZyH8jki6fge'
     *
     * @param WC_Order $order The order processed
     *
     * @return string The url with order id and key as params
     */
    private function getReturnUrl($order, $returnUrl)
    {
        $returnUrl = untrailingslashit($returnUrl);
        $returnUrl = $this->asciiDomainName($returnUrl);
        $orderId = $order->get_id();
        $orderKey = $order->get_order_key();

        $onMollieReturn = 'onMollieReturn';
        $returnUrl = $this->appendOrderArgumentsToUrl(
            $orderId,
            $orderKey,
            $returnUrl,
            $onMollieReturn
        );
        $returnUrl = untrailingslashit($returnUrl);
        $this->logger->debug(" Order {$orderId} returnUrl: {$returnUrl}", [true]);

        return apply_filters($this->pluginId . '_return_url', $returnUrl, $order);
    }

    /**
     * Get the webhook url
     * For example 'http://mollie-wc.docker.myhost/wc-api/mollie_return/mollie_wc_gateway_bancontact/?order_id=89&key=wc_order_eFZyH8jki6fge'
     *
     * @param WC_Order $order The order processed
     *
     * @return string The url with gateway and order id and key as params
     */
    public function getWebhookUrl($order, $gatewayId)
    {
        $webhookUrl = WC()->api_request_url($gatewayId);
        $webhookUrl = untrailingslashit($webhookUrl);
        $webhookUrl = $this->asciiDomainName($webhookUrl);
        $orderId = $order->get_id();
        $orderKey = $order->get_order_key();
        $webhookUrl = $this->appendOrderArgumentsToUrl(
            $orderId,
            $orderKey,
            $webhookUrl
        );
        $webhookUrl = untrailingslashit($webhookUrl);

        $this->logger->debug(" Order {$orderId} webhookUrl: {$webhookUrl}", [true]);

        return apply_filters($this->pluginId . '_webhook_url', $webhookUrl, $order);
    }

    /**
     * @param $url
     *
     * @return string
     */
    protected function asciiDomainName($url): string
    {
        $parsed = wp_parse_url($url);
        $scheme = isset($parsed['scheme']) ? $parsed['scheme'] : '';
        $domain = isset($parsed['host']) ? $parsed['host'] : false;
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
     * @param $order_id
     * @param $order_key
     * @param $webhook_url
     * @param string $filterFlag
     *
     * @return string
     */
    protected function appendOrderArgumentsToUrl($order_id, $order_key, $webhook_url, $filterFlag = '')
    {
        $webhook_url = add_query_arg(
            [
                'order_id' => $order_id,
                'key' => $order_key,
                'filter_flag' => $filterFlag,
            ],
            $webhook_url
        );
        return $webhook_url;
    }
    /**
     * @param $domain
     * @return false|mixed|string
     */
    protected function idnEncodeDomain($domain)
    {
        if (
            defined('IDNA_NONTRANSITIONAL_TO_ASCII')
            && defined(
                'INTL_IDNA_VARIANT_UTS46'
            )
        ) {
            $domain = idn_to_ascii(
                $domain,
                IDNA_NONTRANSITIONAL_TO_ASCII,
                INTL_IDNA_VARIANT_UTS46
            ) ? idn_to_ascii(
                $domain,
                IDNA_NONTRANSITIONAL_TO_ASCII,
                INTL_IDNA_VARIANT_UTS46
            ) : $domain;
        } else {
            $domain = idn_to_ascii($domain) ? idn_to_ascii($domain) : $domain;
        }
        return $domain;
    }
}

<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Adapter\WordPress;

use Mollie\WooCommerce\Payment\Webhooks\RestApi;
use Mollie\WooCommerce\Payment\Webhooks\WebhookSecret;
/**
 * The URLs an express session carries. No order exists yet, so the return URL carries the
 * express_ref; the webhook URL is the plugin's existing REST webhook with the shop secret, exactly
 * as every other payment's (the per-order token of ADR-013 is out of scope).
 */
class ExpressUrls
{
    public const RETURN_API = 'mollie_express_return';
    public function __construct(private WebhookSecret $webhookSecret)
    {
    }
    public function returnUrl(string $expressRef): string
    {
        return add_query_arg('ref', rawurlencode($expressRef), WC()->api_request_url(self::RETURN_API));
    }
    /**
     * Carries the webhook secret: never log it or send it to the browser.
     */
    public function webhookUrl(): string
    {
        return add_query_arg('mollie_webhook_secret', $this->webhookSecret->getOrCreate(), get_rest_url(null, RestApi::ROUTE_NAMESPACE . '/' . RestApi::WEBHOOK_ROUTE));
    }
}

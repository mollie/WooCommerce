<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\ExpressComponent\Entry;

use Mollie\WooCommerce\Payment\Webhooks\RestApi;
use Mollie\WooCommerce\Payment\Webhooks\WebhookSecret;

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
     * The REST webhook URL, with the webhook secret query argument.
     */
    public function webhookUrl(): string
    {
        return add_query_arg(
            'mollie_webhook_secret',
            $this->webhookSecret->getOrCreate(),
            get_rest_url(null, RestApi::ROUTE_NAMESPACE . '/' . RestApi::WEBHOOK_ROUTE)
        );
    }

    /**
     * Where Mollie asks for shipping options. The secret is the session's, so only the holder of this URL can ask;
     * Mollie calls it on every address change, so it is valid until the session expires.
     */
    public function shippingCallbackUrl(string $secret): string
    {
        return add_query_arg(
            'secret',
            $secret,
            get_rest_url(null, RestApi::ROUTE_NAMESPACE . '/' . ExpressRoutes::SHIPPING_ROUTE)
        );
    }
}

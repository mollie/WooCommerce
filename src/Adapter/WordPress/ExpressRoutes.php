<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Adapter\WordPress;

use Mollie\WooCommerce\Core\Security\Admission;
use Mollie\WooCommerce\Core\Types\Refuse;
use Mollie\WooCommerce\Payment\Webhooks\RestApi;
use Mollie\WooCommerce\Workflow\StartExpressSession;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
/**
 * The Express Component's REST entry points, in the plugin's existing mollie/v1 namespace.
 *
 * REST rather than admin-ajax so the admission status codes are part of the contract. A route takes
 * a nonce and nothing else: no amount, currency, line, address or country is read from the caller
 * (REQ-B4). The permission callback parses the request once and asks Admission; it never admits
 * unconditionally. Failures answer a translatable message and a stable code, never Mollie's text.
 */
class ExpressRoutes
{
    /**
     * WooCommerce ties a logged-out shopper's nonce to their WooCommerce session only for actions
     * that start with 'woocommerce' (WC_Session_Handler::maybe_update_nonce_user_logged_out). Without
     * that prefix every guest would share one nonce (REQ-G3).
     */
    public const NONCE_ACTION = 'woocommerce-mollie-express-session';
    public const SESSION_ROUTE = 'express/session';
    private const SURFACE = 'checkout';
    public function __construct(private StartExpressSession $startSession, private \Mollie\WooCommerce\Adapter\WordPress\EventLog $log)
    {
    }
    public function register(): void
    {
        register_rest_route(RestApi::ROUTE_NAMESPACE, self::SESSION_ROUTE, [['methods' => 'POST', 'callback' => [$this, 'startSession'], 'permission_callback' => [$this, 'admitSession'], 'args' => ['nonce' => ['type' => 'string', 'required' => \false, 'sanitize_callback' => 'sanitize_text_field']]]]);
    }
    /**
     * @return true|WP_Error
     */
    public function admitSession(WP_REST_Request $request)
    {
        // The nonce of a logged-out shopper is bound to the WooCommerce session, which a REST request
        // does not load by itself.
        $this->loadWooCommerceSession();
        $nonce = (string) $request->get_param('nonce');
        $decision = Admission::decide(Admission::EXPRESS_SESSION, $nonce !== '', $nonce !== '' && wp_verify_nonce($nonce, self::NONCE_ACTION) !== \false);
        if (!$decision instanceof Refuse) {
            return \true;
        }
        $this->log->warning('express.session.refused', ['surface' => self::SURFACE, 'reason' => $decision->code()]);
        return new WP_Error('mollie_express_forbidden', __('Express checkout could not be started. Please reload the page and try again.', 'mollie-payments-for-woocommerce'), ['status' => $decision->httpStatus()]);
    }
    /**
     * @return WP_REST_Response|WP_Error
     */
    public function startSession(WP_REST_Request $request)
    {
        $result = $this->startSession->start(self::SURFACE);
        if (!$result->isStarted()) {
            return new WP_Error($result->code(), $this->messageFor($result->code()), ['status' => $result->httpStatus()]);
        }
        $response = new WP_REST_Response(['clientAccessToken' => $result->clientAccessToken(), 'expiresAt' => $result->expiresAt()], 200);
        // A per-shopper credential: never cached by a proxy or the browser.
        $response->header('Cache-Control', 'no-store, private');
        return $response;
    }
    private function messageFor(string $code): string
    {
        return match ($code) {
            'shipping_incomplete' => __('Your order contains items to ship. Please fill in the shipping details to use express checkout.', 'mollie-payments-for-woocommerce'),
            'budget_exhausted' => __('Express checkout was started too often. Please wait a few minutes, or use the regular checkout.', 'mollie-payments-for-woocommerce'),
            'session_refused', 'mollie_unavailable' => __('Express checkout is not available right now. Please use the regular checkout.', 'mollie-payments-for-woocommerce'),
            default => __('Express checkout is not available for this order. Please use the regular checkout.', 'mollie-payments-for-woocommerce'),
        };
    }
    private function loadWooCommerceSession(): void
    {
        if (function_exists('WC') && !WC()->session instanceof \WC_Session && did_action('woocommerce_init')) {
            wc_load_cart();
        }
    }
}

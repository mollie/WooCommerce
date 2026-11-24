<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\ExpressComponent\Entry;

use Automattic\WooCommerce\StoreApi\Utilities\RateLimits;
use Mollie\WooCommerce\ExpressComponent\Flow\ExpressOrderResult;
use Mollie\WooCommerce\ExpressComponent\Flow\ExpressSessionResult;
use Mollie\WooCommerce\ExpressComponent\Flow\PriceShippingOptions;
use Mollie\WooCommerce\ExpressComponent\Flow\StartExpressOrder;
use Mollie\WooCommerce\ExpressComponent\Flow\StartExpressSession;
use Mollie\WooCommerce\ExpressComponent\Rules\Admission;
use Mollie\WooCommerce\ExpressComponent\WooCommerce\ExpressShippingSnapshot;
use Mollie\WooCommerce\ExpressComponent\WooCommerce\FormPaymentMethod;
use Mollie\WooCommerce\Log\EventLog;
use Mollie\WooCommerce\Payment\Webhooks\RestApi;
use Mollie\WooCommerce\Shared\Values\Admit;
use Mollie\WooCommerce\Shared\Values\Refuse;
use WC_Geolocation;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
class ExpressRoutes
{
    /**
     * The 'woocommerce' prefix binds a guest's nonce to their WooCommerce session.
     */
    public const NONCE_ACTION = 'woocommerce-mollie-express-session';
    public const SESSION_ROUTE = 'express/session';
    public const ORDER_ROUTE = 'express/order';
    public const SHIPPING_ROUTE = 'express/shipping';
    private const ERROR_TYPE_BASE = 'https://docs.mollie.com/reference/errors/';
    /**
     * The pages a session can start from; the product page is not one.
     */
    private const SURFACES = ['checkout', 'cart'];
    public function __construct(private StartExpressSession $startSession, private StartExpressOrder $startOrder, private PriceShippingOptions $priceShipping, private ExpressShippingSnapshot $snapshot, private EventLog $log)
    {
    }
    public function register(): void
    {
        $this->registerShippingRoute();
        register_rest_route(RestApi::ROUTE_NAMESPACE, self::SESSION_ROUTE, [['methods' => 'POST', 'callback' => [$this, 'startSession'], 'permission_callback' => [$this, 'admitSession'], 'args' => [
            'nonce' => ['type' => 'string', 'required' => \false, 'sanitize_callback' => 'sanitize_text_field'],
            // Not required: WordPress checks arguments before the nonce, and a request without one must meet the nonce first.
            'surface' => ['type' => 'string', 'required' => \false, 'enum' => self::SURFACES],
        ]]]);
        register_rest_route(RestApi::ROUTE_NAMESPACE, self::ORDER_ROUTE, [['methods' => 'POST', 'callback' => [$this, 'startOrder'], 'permission_callback' => [$this, 'admitOrder'], 'args' => ['nonce' => ['type' => 'string', 'required' => \false, 'sanitize_callback' => 'sanitize_text_field'], 'token' => ['type' => 'string', 'required' => \false, 'sanitize_callback' => 'sanitize_text_field']]]]);
    }
    private function registerShippingRoute(): void
    {
        register_rest_route(RestApi::ROUTE_NAMESPACE, self::SHIPPING_ROUTE, [['methods' => 'POST', 'callback' => [$this, 'priceShippingOptions'], 'permission_callback' => [$this, 'admitShipping'], 'args' => ['secret' => ['type' => 'string', 'required' => \false, 'sanitize_callback' => 'sanitize_text_field'], 'event' => ['type' => 'string', 'required' => \false, 'sanitize_callback' => 'sanitize_text_field'], 'sessionId' => ['type' => 'string', 'required' => \false, 'sanitize_callback' => 'sanitize_text_field']]]]);
    }
    /**
     * Mollie's call: no cookie, no nonce. The secret in the URL, the session id and the event admit it.
     *
     * @return true|WP_Error
     */
    public function admitShipping(WP_REST_Request $request)
    {
        $secret = (string) $request->get_param('secret');
        $decision = Admission::admitShippingCallback($secret !== '', $this->snapshot->exists($secret), $this->snapshot->isBoundTo($secret, (string) $request->get_param('sessionId')), (string) $request->get_param('event'));
        // Neither the secret nor the address is logged.
        return $decision instanceof Refuse ? $this->forbidden($decision, 'express.shipping.refused') : \true;
    }
    public function priceShippingOptions(WP_REST_Request $request): WP_REST_Response
    {
        $this->loadWooCommerceSession();
        $verdict = $this->priceShipping->price((string) $request->get_param('secret'), $this->walletAddress($request));
        $refusal = $verdict->refusalType();
        $response = $refusal === null ? new WP_REST_Response(['options' => $verdict->options()], 200) : new WP_REST_Response([
            'type' => self::ERROR_TYPE_BASE . $refusal,
            'status' => 422,
            // For Mollie's log only: nothing of the address.
            'detail' => 'The store cannot ship to this address.',
        ], 422);
        $response->header('Cache-Control', 'no-store, private');
        return $response;
    }
    /**
     * Mollie sends a partial address. Input is parsed here, at the edge: a country that is not two letters
     * is no address the store can price.
     *
     * @return array{country: string, state: string, postcode: string, city: string}
     */
    private function walletAddress(WP_REST_Request $request): array
    {
        $given = $request->get_param('shippingAddress');
        $given = is_array($given) ? $given : [];
        $text = static fn(string $field): string => sanitize_text_field((string) ($given[$field] ?? ''));
        $country = strtoupper($text('country'));
        return ['country' => preg_match('/^[A-Z]{2}$/', $country) === 1 ? $country : '', 'state' => $text('region'), 'postcode' => $text('postalCode'), 'city' => $text('city')];
    }
    /**
     * @return true|WP_Error
     */
    public function admitSession(WP_REST_Request $request)
    {
        $surface = $this->surface($request);
        return $this->admit(Admission::EXPRESS_SESSION, $request, 'express.session.refused', $surface === null ? [] : ['surface' => $surface]);
    }
    /**
     * @return WP_REST_Response|WP_Error
     */
    public function startSession(WP_REST_Request $request)
    {
        $surface = $this->surface($request);
        if ($surface === null) {
            return new WP_Error('rest_missing_callback_param', self::messageFor('mollie_unavailable'), ['status' => 400, 'params' => ['surface']]);
        }
        $result = FormPaymentMethod::setAsideDuring(fn(): ExpressSessionResult => $this->startSession->start($surface, $this->callerAddress()));
        if (!$result->isStarted()) {
            return new WP_Error($result->code(), self::messageFor($result->code()), ['status' => $result->httpStatus()]);
        }
        $response = new WP_REST_Response(['clientAccessToken' => $result->clientAccessToken(), 'expiresAt' => $result->expiresAt()], 200);
        $response->header('Cache-Control', 'no-store, private');
        return $response;
    }
    /**
     * @return true|WP_Error
     */
    public function admitOrder(WP_REST_Request $request)
    {
        return $this->admit(Admission::EXPRESS_ORDER, $request, 'express.order.refused');
    }
    public function startOrder(WP_REST_Request $request): WP_REST_Response
    {
        $result = FormPaymentMethod::setAsideDuring(fn(): ExpressOrderResult => $this->startOrder->start((string) $request->get_param('token')));
        if ($result->isOk()) {
            $data = ['ok' => \true];
        } else {
            $data = ['ok' => \false, 'code' => $result->code(), 'message' => $result->reason() ?? self::messageFor($result->code())];
        }
        $response = new WP_REST_Response($data, $result->httpStatus());
        $response->header('Cache-Control', 'no-store, private');
        return $response;
    }
    /**
     * @param array<string, string> $fields
     * @return true|WP_Error
     */
    private function admit(string $entryPoint, WP_REST_Request $request, string $event, array $fields = [])
    {
        $decision = $this->admission($entryPoint, $request);
        return $decision instanceof Refuse ? $this->forbidden($decision, $event, $fields) : \true;
    }
    /**
     * @param array<string, string> $fields
     */
    private function forbidden(Refuse $decision, string $event, array $fields = []): WP_Error
    {
        // Anyone can cause this: info, not a warning.
        $this->log->info($event, $fields + ['reason' => $decision->code()]);
        return new WP_Error('mollie_express_forbidden', self::messageFor('mollie_express_forbidden'), ['status' => $decision->httpStatus()]);
    }
    /**
     * The page the shopper started from, as WordPress checked it against the route's list.
     */
    private function surface(WP_REST_Request $request): ?string
    {
        $surface = $request->get_param('surface');
        return is_string($surface) && in_array($surface, self::SURFACES, \true) ? $surface : null;
    }
    private function admission(string $entryPoint, WP_REST_Request $request): Admit|Refuse
    {
        // REST does not load the WooCommerce session that guest nonces are bound to.
        $this->loadWooCommerceSession();
        $nonce = (string) $request->get_param('nonce');
        return Admission::admit($entryPoint, $nonce !== '', $nonce !== '' && wp_verify_nonce($nonce, self::NONCE_ACTION) !== \false);
    }
    public static function messageFor(string $code): string
    {
        return match ($code) {
            'mollie_express_forbidden' => __('Express checkout could not be started. Please reload the page and try again.', 'mollie-payments-for-woocommerce'),
            'session_missing', 'session_expired' => __('Express checkout has expired. Please try again.', 'mollie-payments-for-woocommerce'),
            'cart_changed', 'session_replaced' => __('Your order changed after express checkout was started. Please try again.', 'mollie-payments-for-woocommerce'),
            'try_again' => __('Express checkout is busy with this order. Please try again in a moment.', 'mollie-payments-for-woocommerce'),
            'budget_exhausted' => __('Express checkout was started too often. Please wait a few minutes, or use the regular checkout.', 'mollie-payments-for-woocommerce'),
            'order_not_payable' => __('This express checkout was already completed. Please check your email for the order confirmation.', 'mollie-payments-for-woocommerce'),
            'session_refused', 'mollie_unavailable' => __('Express checkout is not available right now. Please use the regular checkout.', 'mollie-payments-for-woocommerce'),
            default => __('Express checkout is not available for this order. Please use the regular checkout.', 'mollie-payments-for-woocommerce'),
        };
    }
    /**
     * The forwarded address counts only when the shop enabled the Store API's proxy support.
     */
    private function callerAddress(): string
    {
        if (class_exists(RateLimits::class) && (bool) RateLimits::get_options()->proxy_support) {
            return WC_Geolocation::get_ip_address();
        }
        $address = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'] ?? ''));
        return filter_var($address, \FILTER_VALIDATE_IP) !== \false ? $address : '';
    }
    private function loadWooCommerceSession(): void
    {
        if (!function_exists('WC') || !did_action('woocommerce_init')) {
            return;
        }
        /** @var \WC_Session|null $session */
        $session = WC()->session;
        if (!$session instanceof \WC_Session) {
            wc_load_cart();
        }
    }
}

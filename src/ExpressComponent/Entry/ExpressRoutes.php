<?php

declare(strict_types=1);

namespace Mollie\WooCommerce\ExpressComponent\Entry;

use Mollie\WooCommerce\ExpressComponent\Flow\StartExpressOrder;
use Mollie\WooCommerce\ExpressComponent\Flow\StartExpressSession;
use Mollie\WooCommerce\ExpressComponent\Rules\Admission;
use Mollie\WooCommerce\Log\EventLog;
use Mollie\WooCommerce\Payment\Webhooks\RestApi;
use Mollie\WooCommerce\Shared\Values\Admit;
use Mollie\WooCommerce\Shared\Values\Refuse;
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

    private const SURFACE = 'checkout';

    public function __construct(
        private StartExpressSession $startSession,
        private StartExpressOrder $startOrder,
        private EventLog $log
    ) {
    }

    public function register(): void
    {
        register_rest_route(RestApi::ROUTE_NAMESPACE, self::SESSION_ROUTE, [
            [
                'methods' => 'POST',
                'callback' => [$this, 'startSession'],
                'permission_callback' => [$this, 'admitSession'],
                'args' => [
                    'nonce' => [
                        'type' => 'string',
                        'required' => false,
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                ],
            ],
        ]);

        // Called on Mollie's submit event, before the payment exists.
        register_rest_route(RestApi::ROUTE_NAMESPACE, self::ORDER_ROUTE, [
            [
                'methods' => 'POST',
                'callback' => [$this, 'startOrder'],
                'permission_callback' => [$this, 'admitOrder'],
                'args' => [
                    'nonce' => [
                        'type' => 'string',
                        'required' => false,
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                ],
            ],
        ]);
    }

    /**
     * @return true|WP_Error
     */
    public function admitSession(WP_REST_Request $request)
    {
        return $this->admit(Admission::EXPRESS_SESSION, $request, 'express.session.refused', ['surface' => self::SURFACE]);
    }

    /**
     * @return WP_REST_Response|WP_Error
     */
    public function startSession(WP_REST_Request $request)
    {
        $result = $this->startSession->start(self::SURFACE);
        if (!$result->isStarted()) {
            return new WP_Error($result->code(), self::messageFor($result->code()), ['status' => $result->httpStatus()]);
        }

        $response = new WP_REST_Response([
            'clientAccessToken' => $result->clientAccessToken(),
            'expiresAt' => $result->expiresAt(),
        ], 200);
        // Per-shopper credential.
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
        $result = $this->startOrder->start();
        if ($result->isOk()) {
            $data = ['ok' => true];
        } else {
            $data = [
                'ok' => false,
                'code' => $result->code(),
                'message' => $result->reason() ?? self::messageFor($result->code()),
            ];
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
        if (!$decision instanceof Refuse) {
            return true;
        }

        // Anyone can cause this, so it is info, not a warning.
        $this->log->info($event, $fields + ['reason' => $decision->code()]);

        return new WP_Error(
            'mollie_express_forbidden',
            __('Express checkout could not be started. Please reload the page and try again.', 'mollie-payments-for-woocommerce'),
            ['status' => $decision->httpStatus()]
        );
    }

    private function admission(string $entryPoint, WP_REST_Request $request): Admit|Refuse
    {
        // REST does not load the WooCommerce session that guest nonces are bound to.
        $this->loadWooCommerceSession();

        $nonce = (string) $request->get_param('nonce');

        return Admission::admit(
            $entryPoint,
            $nonce !== '',
            $nonce !== '' && wp_verify_nonce($nonce, self::NONCE_ACTION) !== false
        );
    }

    public static function messageFor(string $code): string
    {
        return match ($code) {
            'shipping_incomplete' => __(
                'Your order contains items to ship. Please fill in the shipping details to use express checkout.',
                'mollie-payments-for-woocommerce'
            ),
            'session_missing', 'session_expired' => __(
                'Express checkout has expired. Please try again.',
                'mollie-payments-for-woocommerce'
            ),
            'cart_changed' => __(
                'Your order changed after express checkout was started. Please try again.',
                'mollie-payments-for-woocommerce'
            ),
            'try_again' => __(
                'Express checkout is busy with this order. Please try again in a moment.',
                'mollie-payments-for-woocommerce'
            ),
            'budget_exhausted' => __(
                'Express checkout was started too often. Please wait a few minutes, or use the regular checkout.',
                'mollie-payments-for-woocommerce'
            ),
            'order_not_payable' => __(
                'This express checkout was already completed. Please check your email for the order confirmation.',
                'mollie-payments-for-woocommerce'
            ),
            'session_refused', 'mollie_unavailable' => __(
                'Express checkout is not available right now. Please use the regular checkout.',
                'mollie-payments-for-woocommerce'
            ),
            default => __(
                'Express checkout is not available for this order. Please use the regular checkout.',
                'mollie-payments-for-woocommerce'
            ),
        };
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

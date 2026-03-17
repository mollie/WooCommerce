<?php
/**
 * Plugin Name: Mollie Subscription Debug
 * Description: WP-CLI command to reproduce PIWOO-785 (duplicate payments on iDEAL subscriptions).
 *              Registers logging hooks for all outgoing Mollie API requests and incoming webhooks,
 *              writing them to a per-order log file.
 * Version: 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// Logging helpers
// ─────────────────────────────────────────────────────────────────────────────

function mollie_debug_log_dir(): string {
    return WP_CONTENT_DIR . '/mollie-debug-logs';
}

function mollie_debug_log_file( int $order_id ): string {
    return mollie_debug_log_dir() . '/order-' . $order_id . '.log';
}

/**
 * Write a timestamped entry to every active order log file.
 *
 * @param string   $message
 * @param int|null $order_id  When supplied, only write to that order's file.
 */
function mollie_debug_write( string $message, ?int $order_id = null ): void {
    $log_dir = mollie_debug_log_dir();
    if ( ! is_dir( $log_dir ) ) {
        mkdir( $log_dir, 0755, true );
    }

    $timestamp = date( 'Y-m-d H:i:s' );
    $line      = "[{$timestamp}] {$message}" . PHP_EOL;

    if ( $order_id !== null ) {
        file_put_contents( mollie_debug_log_file( $order_id ), $line, FILE_APPEND | LOCK_EX );
        return;
    }

    // Fall back to all monitored orders.
    foreach ( mollie_debug_monitored_orders() as $oid ) {
        file_put_contents( mollie_debug_log_file( $oid ), $line, FILE_APPEND | LOCK_EX );
    }
}

/** Returns the list of order IDs that should be logged. */
function mollie_debug_monitored_orders(): array {
    return (array) get_option( 'mollie_debug_monitored_orders', [] );
}

/** Adds an order to the monitored list. */
function mollie_debug_monitor_order( int $order_id ): void {
    $orders   = mollie_debug_monitored_orders();
    $orders[] = $order_id;
    update_option( 'mollie_debug_monitored_orders', array_unique( $orders ) );
}

/** Removes an order from the monitored list. */
function mollie_debug_unmonitor_order( int $order_id ): void {
    $orders = array_diff( mollie_debug_monitored_orders(), [ $order_id ] );
    update_option( 'mollie_debug_monitored_orders', array_values( $orders ) );
}

// ─────────────────────────────────────────────────────────────────────────────
// Logging hooks – registered on every request so webhooks are captured
// ─────────────────────────────────────────────────────────────────────────────

add_action( 'init', function () {
    // Outgoing Mollie API requests
    add_action( 'http_api_debug', 'mollie_debug_hook_http_api', 10, 5 );

    // Incoming REST webhook (POST /wp-json/mollie/v1/webhook)
    add_filter( 'rest_pre_dispatch', 'mollie_debug_hook_rest_webhook', 1, 3 );

    // Legacy WooCommerce API webhook (?wc-api=mollie_wc_gateway_ideal)
    add_action( 'woocommerce_api_mollie_wc_gateway_ideal', 'mollie_debug_hook_wc_api_webhook', 1 );
}, 1 );

/**
 * Log every outgoing wp_remote_* call that targets api.mollie.com.
 *
 * @param mixed  $response
 * @param string $context   Always 'response'.
 * @param string $class     Transport class name.
 * @param array  $args      Request arguments (method, body, headers, …).
 * @param string $url       Target URL.
 */
function mollie_debug_hook_http_api( $response, string $context, string $class, array $args, string $url ): void {
    if ( strpos( $url, 'api.mollie.com' ) === false ) {
        return;
    }

    if ( empty( mollie_debug_monitored_orders() ) ) {
        return;
    }

    $method      = strtoupper( $args['method'] ?? 'GET' );
    $request_body = $args['body'] ?? '';
    if ( is_array( $request_body ) ) {
        $request_body = wp_json_encode( $request_body );
    }

    $status   = is_wp_error( $response ) ? 'WP_Error: ' . $response->get_error_message() : wp_remote_retrieve_response_code( $response );
    $resp_body = is_wp_error( $response ) ? '' : wp_remote_retrieve_body( $response );

    $entry = implode( PHP_EOL, [
        '--- OUTGOING MOLLIE API REQUEST ---',
        "  Method  : {$method}",
        "  URL     : {$url}",
        '  Body    : ' . ( $request_body ?: '(empty)' ),
        "  Status  : {$status}",
        '  Response: ' . ( $resp_body ?: '(empty)' ),
        '-----------------------------------',
    ] );

    // Try to identify the order from the response (Mollie order/payment has metadata).
    $order_id = mollie_debug_order_from_response( $resp_body );
    mollie_debug_write( $entry, $order_id );
}

/**
 * Log incoming REST webhook calls on the Mollie namespace before dispatch.
 *
 * @param mixed            $result  Pre-dispatch result (null = proceed normally).
 * @param \WP_REST_Server  $server
 * @param \WP_REST_Request $request
 */
function mollie_debug_hook_rest_webhook( $result, $server, $request ) {
    $route = $request->get_route();
    if ( strpos( $route, 'mollie' ) === false ) {
        return $result;
    }

    if ( empty( mollie_debug_monitored_orders() ) ) {
        return $result;
    }

    $transaction_id = $request->get_param( 'id' ) ?? '';
    $params         = $request->get_params();

    $entry = implode( PHP_EOL, [
        '--- INCOMING MOLLIE REST WEBHOOK ---',
        "  Route          : {$route}",
        "  Transaction ID : {$transaction_id}",
        '  Params         : ' . wp_json_encode( $params ),
        '------------------------------------',
    ] );

    $order_id = $transaction_id ? mollie_debug_order_from_transaction( $transaction_id ) : null;
    mollie_debug_write( $entry, $order_id );

    return $result;
}

/**
 * Log the legacy WooCommerce API webhook call.
 */
function mollie_debug_hook_wc_api_webhook(): void {
    if ( empty( mollie_debug_monitored_orders() ) ) {
        return;
    }

    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    $transaction_id = sanitize_text_field( wp_unslash( $_POST['id'] ?? $_GET['id'] ?? '' ) );
    // phpcs:enable

    $entry = implode( PHP_EOL, [
        '--- INCOMING MOLLIE WC-API WEBHOOK ---',
        "  Transaction ID : {$transaction_id}",
        '  GET            : ' . wp_json_encode( $_GET ),
        '  POST           : ' . wp_json_encode( $_POST ),
        '---------------------------------------',
    ] );

    $order_id = $transaction_id ? mollie_debug_order_from_transaction( $transaction_id ) : null;
    mollie_debug_write( $entry, $order_id );
}

// ─────────────────────────────────────────────────────────────────────────────
// Helpers to correlate a log entry with a specific order
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Given a Mollie transaction/order ID, find the matching WooCommerce order ID
 * from the monitored list.
 */
function mollie_debug_order_from_transaction( string $transaction_id ): ?int {
    $monitored = mollie_debug_monitored_orders();
    if ( empty( $monitored ) ) {
        return null;
    }

    $orders = wc_get_orders( [
        'transaction_id' => $transaction_id,
        'limit'          => 1,
    ] );

    if ( ! $orders ) {
        $meta_key = ( substr( $transaction_id, 0, 4 ) === 'ord_' ) ? '_mollie_order_id' : '_mollie_payment_id';
        $orders   = wc_get_orders( [
            'limit'          => 1,
            'meta_key'       => $meta_key,
            'meta_compare'   => '=',
            'meta_value'     => $transaction_id,
        ] );
    }

    if ( $orders ) {
        $oid = $orders[0]->get_id();
        return in_array( $oid, $monitored, true ) ? $oid : null;
    }

    return null;
}

/**
 * Try to extract an order ID from a Mollie API JSON response body.
 * Falls back to null (= log to all monitored orders).
 */
function mollie_debug_order_from_response( string $body ): ?int {
    if ( empty( $body ) ) {
        return null;
    }

    $data = json_decode( $body, true );
    if ( ! is_array( $data ) ) {
        return null;
    }

    // Mollie embeds the WC order ID / order key in the metadata or description.
    $metadata = $data['metadata'] ?? [];
    if ( isset( $metadata['order_id'] ) ) {
        $oid = (int) $metadata['order_id'];
        if ( in_array( $oid, mollie_debug_monitored_orders(), true ) ) {
            return $oid;
        }
    }

    return null;
}

// ─────────────────────────────────────────────────────────────────────────────
// WP-CLI command
// ─────────────────────────────────────────────────────────────────────────────

if ( defined( 'WP_CLI' ) && WP_CLI ) {
    /**
     * Mollie subscription debug commands.
     */
    class Mollie_Debug_Command {

        /**
         * Set up a yearly iDEAL subscription to reproduce PIWOO-785.
         *
         * Creates a WooCommerce (Subscription) order for €34.95/year with
         * iDEAL as the payment method, initiates the Mollie payment, and
         * writes a log file named after the order number that captures all
         * subsequent webhooks and outgoing API requests.
         *
         * ## OPTIONS
         *
         * [--amount=<amount>]
         * : Subscription amount in euros. Default: 34.95
         *
         * [--customer-email=<email>]
         * : Customer e-mail address. A new WP user is created when no
         *   existing user with this e-mail is found. Default: debug@example.com
         *
         * [--keep-monitoring]
         * : Do not remove the order from the monitoring list after this run;
         *   webhooks will continue to be logged.
         *
         * ## EXAMPLES
         *
         *     wp mollie-debug setup-subscription
         *     wp mollie-debug setup-subscription --amount=34.95 --customer-email=test@example.com
         *
         * @when after_wp_load
         */
        public function setup_subscription( array $args, array $assoc_args ): void {
            $amount = (float) ( $assoc_args['amount'] ?? 34.95 );
            $email  = sanitize_email( $assoc_args['customer-email'] ?? 'debug@example.com' );

            WP_CLI::log( 'Checking dependencies…' );
            $this->require_woocommerce();

            $has_subscriptions = class_exists( 'WC_Subscriptions' ) || class_exists( 'WC_Subscriptions_Order' );
            if ( $has_subscriptions ) {
                WP_CLI::log( 'WooCommerce Subscriptions detected.' );
            } else {
                WP_CLI::warning( 'WooCommerce Subscriptions NOT active – creating a regular order instead.' );
            }

            // ── 1. Customer ───────────────────────────────────────────────
            $customer_id = $this->get_or_create_customer( $email );
            WP_CLI::log( "Customer ID: {$customer_id} ({$email})" );

            // ── 2. Subscription product ───────────────────────────────────
            $product_id = $has_subscriptions
                ? $this->get_or_create_subscription_product( $amount )
                : $this->get_or_create_simple_product( $amount );
            WP_CLI::log( "Product ID: {$product_id} (€{$amount}/year)" );

            // ── 3. Parent order ───────────────────────────────────────────
            $order = $this->create_order( $customer_id, $product_id );
            $order_id = $order->get_id();
            WP_CLI::log( "WooCommerce Order ID: {$order_id}" );

            // ── 4. Register order for logging ─────────────────────────────
            mollie_debug_monitor_order( $order_id );

            // Initialise the log file immediately.
            $log_file = mollie_debug_log_file( $order_id );
            mollie_debug_write( '=== MOLLIE SUBSCRIPTION DEBUG LOG ===', $order_id );
            mollie_debug_write( "Ticket        : PIWOO-785", $order_id );
            mollie_debug_write( "Order ID      : {$order_id}", $order_id );
            mollie_debug_write( "Customer ID   : {$customer_id} ({$email})", $order_id );
            mollie_debug_write( "Product ID    : {$product_id}", $order_id );
            mollie_debug_write( "Amount        : €{$amount}", $order_id );
            mollie_debug_write( "Frequency     : Yearly", $order_id );
            mollie_debug_write( "Payment method: iDEAL (mollie_wc_gateway_ideal)", $order_id );
            mollie_debug_write( '======================================', $order_id );

            // ── 5. WooCommerce Subscription object ────────────────────────
            if ( $has_subscriptions ) {
                try {
                    $subscription = $this->create_wcs_subscription( $order, $product_id );
                    mollie_debug_write( "WCS Subscription ID: " . $subscription->get_id(), $order_id );
                    WP_CLI::log( 'WCS Subscription ID: ' . $subscription->get_id() );
                } catch ( \Exception $e ) {
                    WP_CLI::warning( 'Could not create WCS subscription: ' . $e->getMessage() );
                    mollie_debug_write( 'WCS subscription creation failed: ' . $e->getMessage(), $order_id );
                }
            }

            // ── 6. Initiate Mollie payment ────────────────────────────────
            WP_CLI::log( 'Initiating Mollie iDEAL payment…' );
            mollie_debug_write( '--- Initiating Mollie payment ---', $order_id );

            try {
                $redirect_url = $this->initiate_mollie_payment( $order );
                mollie_debug_write( "Mollie redirect URL: {$redirect_url}", $order_id );
                WP_CLI::success( "Payment initiated. Open this URL in a browser to complete the iDEAL flow:" );
                WP_CLI::log( $redirect_url );
            } catch ( \Exception $e ) {
                mollie_debug_write( 'Payment initiation failed: ' . $e->getMessage(), $order_id );
                WP_CLI::error( 'Payment initiation failed: ' . $e->getMessage(), false );
            }

            WP_CLI::success( "Log file: {$log_file}" );
            WP_CLI::log( 'Webhooks and outgoing API calls will be appended to that file.' );

            if ( ! isset( $assoc_args['keep-monitoring'] ) ) {
                WP_CLI::log( '' );
                WP_CLI::log( 'Tip: once you are done, stop monitoring this order with:' );
                WP_CLI::log( "  wp mollie-debug stop-monitoring {$order_id}" );
            }
        }

        /**
         * Stop logging webhooks / API calls for a specific order.
         *
         * ## OPTIONS
         *
         * <order_id>
         * : The WooCommerce order ID to stop monitoring.
         *
         * ## EXAMPLES
         *
         *     wp mollie-debug stop-monitoring 42
         *
         * @when after_wp_load
         */
        public function stop_monitoring( array $args, array $assoc_args ): void {
            if ( empty( $args[0] ) ) {
                WP_CLI::error( 'Please provide an order ID.' );
            }
            $order_id = (int) $args[0];
            mollie_debug_unmonitor_order( $order_id );
            WP_CLI::success( "Order {$order_id} removed from monitoring." );
        }

        /**
         * List all currently monitored orders.
         *
         * @when after_wp_load
         */
        public function list_monitored( array $args, array $assoc_args ): void {
            $orders = mollie_debug_monitored_orders();
            if ( empty( $orders ) ) {
                WP_CLI::log( 'No orders are currently being monitored.' );
                return;
            }
            WP_CLI::log( 'Currently monitored orders: ' . implode( ', ', $orders ) );
        }

        // ── Private helpers ───────────────────────────────────────────────────

        private function require_woocommerce(): void {
            if ( ! function_exists( 'wc_create_order' ) ) {
                WP_CLI::error( 'WooCommerce is not active. Please activate it first.' );
            }
        }

        private function get_or_create_customer( string $email ): int {
            $user = get_user_by( 'email', $email );
            if ( $user ) {
                return $user->ID;
            }

            $username = 'mollie-debug-' . wp_generate_password( 6, false );
            $user_id  = wp_create_user( $username, wp_generate_password(), $email );
            if ( is_wp_error( $user_id ) ) {
                WP_CLI::error( 'Could not create customer: ' . $user_id->get_error_message() );
            }
            // Give the user the WooCommerce customer role.
            $user = new WP_User( $user_id );
            $user->set_role( 'customer' );

            return $user_id;
        }

        private function get_or_create_subscription_product( float $amount ): int {
            // Look for an existing yearly subscription product at the right price.
            $existing = wc_get_products( [
                'type'   => 'subscription',
                'limit'  => 1,
                'status' => 'publish',
            ] );

            if ( ! empty( $existing ) ) {
                $product = $existing[0];
                // Update price to match if necessary.
                if ( (float) $product->get_price() !== $amount ) {
                    $product->set_regular_price( $amount );
                    $product->save();
                }
                return $product->get_id();
            }

            // Create a new subscription product.
            $product = new \WC_Product_Subscription();
            $product->set_name( 'Mollie Debug Subscription (€' . number_format( $amount, 2 ) . '/year)' );
            $product->set_regular_price( $amount );
            $product->set_status( 'publish' );
            $product->update_meta_data( '_subscription_period', 'year' );
            $product->update_meta_data( '_subscription_period_interval', 1 );
            $product->update_meta_data( '_subscription_sign_up_fee', 0 );
            $product->update_meta_data( '_subscription_trial_length', 0 );
            $product->save();

            return $product->get_id();
        }

        private function get_or_create_simple_product( float $amount ): int {
            $existing = get_posts( [
                'post_type'   => 'product',
                'post_status' => 'publish',
                'meta_key'    => '_regular_price',
                'meta_value'  => (string) $amount,
                'numberposts' => 1,
            ] );

            if ( ! empty( $existing ) ) {
                return $existing[0]->ID;
            }

            $product = new \WC_Product_Simple();
            $product->set_name( 'Mollie Debug Product (€' . number_format( $amount, 2 ) . ')' );
            $product->set_regular_price( $amount );
            $product->set_status( 'publish' );
            $product->save();

            return $product->get_id();
        }

        private function create_order( int $customer_id, int $product_id ): \WC_Order {
            $order = wc_create_order( [ 'customer_id' => $customer_id ] );
            if ( is_wp_error( $order ) ) {
                WP_CLI::error( 'Could not create order: ' . $order->get_error_message() );
            }

            $product = wc_get_product( $product_id );
            if ( ! $product ) {
                WP_CLI::error( "Product {$product_id} not found." );
            }

            $item = new \WC_Order_Item_Product();
            $item->set_props( [
                'product_id' => $product_id,
                'quantity'   => 1,
                'subtotal'   => $product->get_price(),
                'total'      => $product->get_price(),
            ] );
            $order->add_item( $item );

            $order->set_billing_first_name( 'Debug' );
            $order->set_billing_last_name( 'User' );
            $order->set_billing_address_1( 'Keizersgracht 126' );
            $order->set_billing_city( 'Amsterdam' );
            $order->set_billing_postcode( '1015 CW' );
            $order->set_billing_country( 'NL' );
            $order->set_billing_email( get_user_by( 'id', $customer_id )->user_email );
            $order->set_billing_phone( '+31201234567' );

            $order->set_payment_method( 'mollie_wc_gateway_ideal' );
            $order->set_payment_method_title( 'iDEAL' );
            $order->set_status( 'pending' );
            $order->calculate_totals();
            $order->save();

            return $order;
        }

        private function create_wcs_subscription( \WC_Order $order, int $product_id ): \WC_Subscription {
            $subscription = wcs_create_subscription( [
                'order_id'         => $order->get_id(),
                'status'           => 'pending',
                'billing_period'   => 'year',
                'billing_interval' => 1,
            ] );

            if ( is_wp_error( $subscription ) ) {
                throw new \RuntimeException( $subscription->get_error_message() );
            }

            $product = wc_get_product( $product_id );
            $item    = new \WC_Order_Item_Product();
            $item->set_props( [
                'product_id' => $product_id,
                'quantity'   => 1,
                'subtotal'   => $product->get_price(),
                'total'      => $product->get_price(),
            ] );
            $subscription->add_item( $item );
            $subscription->set_payment_method( 'mollie_wc_gateway_ideal' );
            $subscription->calculate_totals();
            $subscription->save();

            return $subscription;
        }

        private function initiate_mollie_payment( \WC_Order $order ): string {
            // Bootstrap WooCommerce payment gateways.
            if ( ! did_action( 'woocommerce_init' ) ) {
                do_action( 'woocommerce_init' );
            }

            $gateways = WC()->payment_gateways()->payment_gateways();
            $gateway  = $gateways['mollie_wc_gateway_ideal'] ?? null;

            if ( ! $gateway ) {
                throw new \RuntimeException(
                    'mollie_wc_gateway_ideal not found. Is the Mollie plugin active and iDEAL enabled?'
                );
            }

            // process_payment() may need a fake session/notice sink.
            if ( ! isset( WC()->session ) || ! WC()->session ) {
                WC()->session = new \WC_Session_Handler();
                WC()->session->init();
            }

            $result = $gateway->process_payment( $order->get_id() );

            if ( empty( $result['redirect'] ) ) {
                throw new \RuntimeException(
                    'process_payment() did not return a redirect URL. Result: ' . wp_json_encode( $result )
                );
            }

            return $result['redirect'];
        }
    }

    WP_CLI::add_command( 'mollie-debug', 'Mollie_Debug_Command' );
}

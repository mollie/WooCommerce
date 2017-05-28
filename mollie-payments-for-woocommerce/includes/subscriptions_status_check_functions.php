<?php

class WC_Tools_Subscriptions_Status_Button {

	/**
	 * __construct function.
	 *
	 * @access public
	 * @return void
	 */
	function __construct() {

		add_filter( 'woocommerce_debug_tools', array ( $this, 'mollie_subscription_status_check_button' ) );
	}

	/**
	 * debug_button function.
	 *
	 * @access public
	 *
	 * @param mixed $old
	 *
	 * @return void
	 */
	function mollie_subscription_status_check_button( $old ) {
		$new   = array (
			'mollie_subscription_status_check_action' => array (
				'name'     => __( 'Check Mollie Subscriptions Status', 'mollie-payments-for-woocommerce' ),
				'button'   => __( 'Check subscriptions status', 'mollie-payments-for-woocommerce' ),
				'desc'     => __( 'Check subscriptions that are set to \'Manual renewal\' and \'On-Hold\'. If they have a valid mandate at Mollie, update them to \'Automatic renewal\'.', 'mollie-payments-for-woocommerce' ),
				'callback' => array ( $this, 'mollie_subscription_status_check_action' ),
			),
		);
		$tools = array_merge( $old, $new );

		return $tools;
	}

	/**
	 * debug_button_action function.
	 *
	 * @access public
	 * @return void
	 */
	function mollie_subscription_status_check_action() {

		// Get the next 10 subscriptions
		// Offset will be set if the tool was used in last 24 hours, to only get 'next' subscriptions
		$offset        = get_transient( 'mollie_subscription_status_offset' );
		$subscriptions = wcs_get_subscriptions( array ( 'subscriptions_per_page' => '10', 'offset' => $offset ) );

		// Loop through all subscriptions
		foreach ( $subscriptions as $subscription ) {

			$subscription = new WC_Subscription( $subscription->ID );

			// Only continue if the subscription is set to require manual renewal and status is On-Hold
			if ( ( $subscription->is_manual() ) && ( $subscription->status == 'on-hold' ) ) {

				// Try to find Mollie customer ID in subscription data
				$mollie_customer_id = $subscription->get_meta( '_mollie_customer_id' );

				// Skip to next subscription if no Mollie Customer ID is found (nothing we can do...)
				if ( empty( $mollie_customer_id ) ) {
					continue;
				}

				// Is test mode enabled?
				$settings_helper = Mollie_WC_Plugin::getSettingsHelper();
				$test_mode       = $settings_helper->isTestModeEnabled();

				// Get all mandates for this Mollie customer ID
				$mandates = Mollie_WC_Plugin::getApiHelper()->getApiClient( $test_mode )->customers_mandates->withParentId( $mollie_customer_id )->all();

				// Find one valid mandate for direct debit or credit card
				// Prefer using a direct debit mandate because transaction costs are lower
				$validMandate = false;
				foreach ( $mandates as $mandate ) {
					$method = $mandate->method;

					if ( $mandate->status == 'valid' && $mandate->method == 'directdebit' ) {
						$validMandate = true;
						break;
					}

					if ( $mandate->status == 'valid' && $mandate->method == 'creditcard' ) {
						$validMandate = true;
						break;
					}
				}

				// If one valid mandate is found, update the subscription to Automatic Renewal
				if ( $validMandate ) {

					update_post_meta( $subscription->get_id(), '_requires_manual_renewal', 'false' );

					if ( $method == 'directdebit' ) {
						update_post_meta( $subscription->get_id(), '_payment_method', 'mollie_wc_gateway_ideal' );
						update_post_meta( $subscription->get_id(), '_payment_method_title', 'iDEAL' );
					}

					if ( $method == 'creditcard' ) {
						update_post_meta( $subscription->get_id(), '_payment_method', 'mollie_wc_gateway_creditcard' );
						update_post_meta( $subscription->get_id(), '_payment_method_title', 'Credit Card' );
					}

					$subscription->add_order_note(
						__( 'Subscription updated using \'Check Mollie Subscriptions Status\' tool, switched from Manual Renewal to Automated renewal.', 'mollie-payments-for-woocommerce' )
					);

					echo '<div class="updated"><p>';
					echo 'Updated subscription ' . $subscription->get_id() . ' ' . 'for Mollie Customer ID ';
					echo $subscription->get_meta( '_mollie_customer_id' ) . '</p></div>';


				} else {

					$subscription->add_order_note(
						__( 'Subscription not updated using \'Check Mollie Subscriptions Status\' tool, because there was no valid mandate at Mollie.', 'mollie-payments-for-woocommerce' )
					);

				}

			}

			// Update the offset to only process the next subscriptions
			$offset = get_transient( 'mollie_subscription_status_offset' );
			set_transient( 'mollie_subscription_status_offset', $offset + 1, DAY_IN_SECONDS );

		}

		// Get current offset and check if there are any subscriptions left
		$current_offset     = get_transient( 'mollie_subscription_status_offset' );
		$subscriptions_left = wcs_get_subscriptions( array (
			'subscriptions_per_page' => '10',
			'offset'                 => $current_offset
		) );

		// Explain what to do if there are or are not subscriptions left
		if ( empty( $subscriptions_left ) ) {
			echo '<div class="updated notice"><p>' . __( 'No more subscriptions left to process!', 'mollie-payments-for-woocommerce' ) . '</p></div>';
		} else {
			echo '<div class="error notice"><p>' . __( 'There are still subscriptions left, use the tool again!', 'mollie-payments-for-woocommerce' ) . '</p></div>';
		}

	}

}

$GLOBALS['WC_Tools_Subscriptions_Status_Button'] = new WC_Tools_Subscriptions_Status_Button();
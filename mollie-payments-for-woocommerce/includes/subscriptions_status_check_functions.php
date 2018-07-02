<?php

class WC_Tools_Subscriptions_Status_Button {

	/**
	 * __construct function.
	 *
	 * @access public
	 */
	function __construct() {

		if ( !class_exists( 'WC_Subscriptions' ) ) {
			return;
		}

		add_filter( 'woocommerce_debug_tools', array ( $this, 'mollie_subscription_status_check_button' ) );
	}

	/**
	 * debug_button function.
	 *
	 * @access public
	 *
	 * @param mixed $old
	 *
	 * @return array
	 */
	function mollie_subscription_status_check_button( $old ) {

		$description = __( 'Checks for subscriptions that are incorrectly set to \'Manual renewal\'. First read the ', 'mollie-payments-for-woocommerce' );
		$description .= '<a href=\'https://github.com/mollie/WooCommerce/wiki/Mollie-Subscriptions-Status\'>instructions</a>.';

		$new   = array (
			'mollie_subscription_status_check_action' => array (
				'name'     => __( 'Mollie Subscriptions Status', 'mollie-payments-for-woocommerce' ),
				'button'   => __( 'Check subscriptions status', 'mollie-payments-for-woocommerce' ),
				'desc'     => $description,
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

		// Define a var that registers all updated subscriptions
		$updated_subscriptions = '';

		// Loop through all subscriptions
		foreach ( $subscriptions as $subscription ) {

			// Get all data in the correct way for WooCommerce 3.x or lower
			if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
				$subscription_is_manual = get_post_meta( $subscription->id, '_requires_manual_renewal', true );
				$subscription_status    = $subscription->status;

				$mollie_customer_id = get_post_meta( $subscription->id, '_mollie_customer_id', true );
				$subscription_id    = $subscription->id;
			} else {
				$subscription = new WC_Subscription( $subscription->ID );

				$subscription_is_manual = $subscription->is_manual();
				$subscription_status    = $subscription->get_status();

				$mollie_customer_id = $subscription->get_meta( '_mollie_customer_id' );
				$subscription_id    = $subscription->get_id();
			}

			// Only continue if the subscription is set to require manual renewal and status is On-Hold
			if ( ( $subscription_is_manual ) && ( $subscription_status == 'on-hold' ) ) {

				// Skip to next subscription if no Mollie Customer ID is found (nothing we can do...)
				if ( empty( $mollie_customer_id ) ) {
					continue;
				}

				// Is test mode enabled?
				$settings_helper = Mollie_WC_Plugin::getSettingsHelper();
				$test_mode       = $settings_helper->isTestModeEnabled();

				// Get all mandates for this Mollie customer ID
				try {
					$mandates = Mollie_WC_Plugin::getApiHelper()->getApiClient( $test_mode )->customers->get( $mollie_customer_id )->mandates();
				}
				catch
				( Mollie\Api\Exceptions\ApiException $e ) {
					if ( $e->getField() ) {
						echo '<div class="error notice"><p>' . $e->getMessage() . '</p></div>';
					}
				}

				// Find one valid mandate for direct debit or credit card
				// Prefer using a direct debit mandate because transaction costs are lower
				$validMandate = false;
				$method       = '';

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

				// If a valid mandate is found, update the subscription to Automatic Renewal
				if ( $validMandate ) {

					update_post_meta( $subscription_id, '_requires_manual_renewal', 'false' );

					if ( $method == 'directdebit' ) {
						update_post_meta( $subscription_id, '_payment_method', 'mollie_wc_gateway_ideal' );
						update_post_meta( $subscription_id, '_payment_method_title', 'iDEAL' );
					}

					if ( $method == 'creditcard' ) {
						update_post_meta( $subscription_id, '_payment_method', 'mollie_wc_gateway_creditcard' );
						update_post_meta( $subscription_id, '_payment_method_title', 'Credit Card' );
					}

					try {

						$subscription->update_status( 'active',
							__( 'Subscription updated to Automated renewal via Mollie, status set to Active. Processed by \'Mollie Subscriptions Status\' tool.', 'mollie-payments-for-woocommerce' ),
							true
						);
					}
					catch
					( Exception $e ) {
						echo '<div class="error notice"><p>' . $e->getMessage() . '</p></div>';
					}

					$updated_subscriptions[] = $subscription_id;

				} else {

					$subscription->add_order_note(
						__( 'Subscription not updated because there was no valid mandate at Mollie. Processed by \'Mollie Subscriptions Status\' tool.', 'mollie-payments-for-woocommerce' )
					);

				}

			}

			// Update the offset to only process the next subscriptions
			$offset = get_transient( 'mollie_subscription_status_offset' );
			set_transient( 'mollie_subscription_status_offset', $offset + 1, DAY_IN_SECONDS );

		}

		// Output (and log) a message about which (if any) subscriptions got updated
		if ( ! empty( $updated_subscriptions ) ) {
			echo '<div class="updated"><p>';
			echo 'The following subscriptions have been updated ' . implode( ', ', $updated_subscriptions ) . '. Manually check them as described in the ';
			echo '<a href=\'https://github.com/mollie/WooCommerce/wiki/Mollie-Subscriptions-Status\'>instructions</a>.';
			echo '</p></div>';

			Mollie_WC_Plugin::debug( 'Subscriptions updated by \'Check Mollie Subscriptions Status\': ' . implode( ', ', $updated_subscriptions ) . '. See https://github.com/mollie/WooCommerce/wiki/Mollie-Subscriptions-Status' );

		} else {
			echo '<div class="updated"><p>';
			echo __( 'No subscriptions updated in this batch.', 'mollie-payments-for-woocommerce' );
			echo '</p></div>';
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
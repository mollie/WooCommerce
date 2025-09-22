/* global wc */
import './store/index.js';
import { setUpMollieBlockCheckoutListeners } from './store/storeListeners';
import { MOLLIE_STORE_KEY } from './store';
import { registerAllPaymentMethods } from './registration/paymentRegistrar';
import { buildRegistrationContext } from './registration/contextBuilder';
import { mollieComponentsManager } from './services/MollieComponentsManager';

/**
 * Main Mollie WooCommerce Blocks initialization with mollieComponentsManager
 * @param root0
 * @param root0.mollieBlockData
 * @param root0.wc
 * @param root0._
 */
( function ( { mollieBlockData, wc, _ } ) {
	if ( _.isEmpty( mollieBlockData ) ) {
		console.warn( 'Mollie: No block data available' );
		return;
	}

	try {
		const { gatewayData } = mollieBlockData.gatewayData;
		const context = buildRegistrationContext( wc );

		registerAllPaymentMethods( gatewayData, context );
		setUpMollieBlockCheckoutListeners( MOLLIE_STORE_KEY );

		// Initialize mollieComponentsManager with global settings
		if ( mollieBlockData.gatewayData.componentData ) {
			const initmollieComponentsManager = async () => {
				try {
					const config = mollieBlockData.gatewayData.componentData;
					await mollieComponentsManager.initialize( {
						merchantProfileId: config.merchantProfileId,
						options: config.options || {},
					} );
					console.log( 'Mollie mollieComponentsManager initialized' );
				} catch ( error ) {
					console.error(
						'Failed to initialize mollieComponentsManager:',
						error
					);
				}
			};

			if ( document.readyState === 'loading' ) {
				document.addEventListener(
					'DOMContentLoaded',
					initmollieComponentsManager
				);
			} else {
				initmollieComponentsManager();
			}
		}

		window.addEventListener( 'beforeunload', () => {
			mollieComponentsManager.cleanup();
		} );
	} catch ( error ) {
		console.error( 'Mollie: Initialization failed:', error );
	}
} )( window, wc );

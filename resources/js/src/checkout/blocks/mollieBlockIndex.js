/* global wc */
import './store/index.js';
import { select } from '@wordpress/data';
import { setUpMollieBlockCheckoutListeners } from './store/storeListeners';
import { MOLLIE_STORE_KEY, PAYMENT_STORE_KEY } from './store';
import {registerAllContentHooks, registerGatewayRegistrationHooks} from './registration/libraryHooksRegistrar';
import { buildRegistrationContext } from './registration/contextBuilder';
import { mollieComponentsManager } from './services/MollieComponentsManager';

/**
 * Initialization with mollieComponentsManager
 * Hooks for content and shouldRegister
 * The main registration is done in the paymentGateway lib
 *
 */
( function ( { mollieBlockData, wc, _ } ) {
	if ( _.isEmpty( mollieBlockData ) ) {
		console.warn( 'Mollie: No block data available' );
		return;
	}

	const paymentStore = select( PAYMENT_STORE_KEY );
	if(! paymentStore ) {
		return;
	}

	try {
		const { gatewayData } = mollieBlockData.gatewayData;
		const context = buildRegistrationContext( wc );

		registerAllContentHooks( gatewayData, context );
		registerGatewayRegistrationHooks(gatewayData)
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

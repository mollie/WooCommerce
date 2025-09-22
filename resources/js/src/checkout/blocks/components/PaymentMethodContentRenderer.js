/* global mollieBlockData */
import { useEffect, useRef } from '@wordpress/element';
import { useSelect, useDispatch, select } from '@wordpress/data';
import { MOLLIE_STORE_KEY } from '../store';
import { createPaymentComponent } from './PaymentComponentFactory';
import { mollieComponentsManager } from '../services/MollieComponentsManager';

export const PaymentMethodContentRenderer = ( props ) => {
	const {
		activePaymentMethod,
		billing,
		item,
		emitResponse,
		eventRegistration,
		requiredFields,
		shippingData,
		shouldHidePhoneField,
	} = props;

	const { responseTypes } = emitResponse;
	const { onPaymentSetup } = eventRegistration;
	const containerRef = useRef( null );

	const dispatch = useDispatch( MOLLIE_STORE_KEY );
	useEffect( () => {
		if ( item ) {
			dispatch.setPaymentItemData( item );
		}
	}, [ dispatch, item ] );

	// Only UI state that affects rendering should subscribe here
	const { isComponentReady, componentError } = useSelect(
		( select ) => ( {
			isComponentReady: select( MOLLIE_STORE_KEY ).getIsComponentReady(),
			componentError: select( MOLLIE_STORE_KEY ).getComponentError(),
		} ),
		[]
	);

	// Initialize/unmount Mollie Components for credit card
	useEffect( () => {
		if (
			activePaymentMethod &&
			item.name === 'mollie_wc_gateway_creditcard'
		) {
			const initializeComponents = async () => {
				try {
					const mollieConfig =
						mollieBlockData.gatewayData.componentData || {};
					if ( ! mollieConfig.merchantProfileId ) {
						console.error( 'Mollie merchant profile ID not found' );
						return;
					}

					await mollieComponentsManager.initialize( {
						merchantProfileId: mollieConfig.merchantProfileId,
						options: mollieConfig.options || {},
					} );
					if (
						containerRef.current &&
						mollieConfig.componentsSettings
					) {
						await mollieComponentsManager.mountComponents(
							activePaymentMethod,
							mollieConfig.componentsAttributes,
							mollieConfig.componentsSettings,
							containerRef.current
						);
					}
				} catch ( error ) {
					console.error(
						'Failed to initialize Mollie components:',
						error
					);
				}
			};

			initializeComponents();
		}

		return () => {
			if (
				mollieComponentsManager.getActiveGateway() ===
				activePaymentMethod
			) {
				mollieComponentsManager.unmountComponents(
					activePaymentMethod
				);
			}
		};
	}, [ activePaymentMethod, item.name ] );

	useEffect( () => {
		const onProcessingPayment = async () => {
			// Non-Mollie: let WC handle it
			if ( ! activePaymentMethod.startsWith( 'mollie_wc_gateway_' ) ) {
				return responseTypes.SUCCESS;
			}

			try {
				const sel = select( MOLLIE_STORE_KEY );
				const {
					cardToken,
					getPaymentMethodData,
					getActivePaymentMethod, // in case it changed
				} = {
					cardToken: sel.getCardToken(),
					getPaymentMethodData: sel.getPaymentMethodData,
					getActivePaymentMethod: sel.getActivePaymentMethod,
				};

				let token = cardToken;

				if ( item.name === 'mollie_wc_gateway_creditcard' && ! token ) {
					token = await mollieComponentsManager.createToken();
				}

				const base = getPaymentMethodData();
				const paymentData = {
					...base,
					payment_method:
						getActivePaymentMethod() || activePaymentMethod,
					payment_method_title: item.title,
					cardToken: token || '',
				};

				return {
					type: responseTypes.SUCCESS,
					meta: { paymentMethodData: paymentData },
				};
			} catch ( error ) {
				console.error( 'Payment processing failed:', error );
				return {
					type: responseTypes.ERROR,
					message: error.message || 'Payment processing failed',
				};
			}
		};

		const unsubscribe = onPaymentSetup( onProcessingPayment );
		return unsubscribe;
	}, [
		onPaymentSetup,
		activePaymentMethod,
		item.title,
		item.name,
		responseTypes,
	] );

	const commonProps = {
		item,
		useEffect,
		billing,
		shippingData,
		eventRegistration,
		requiredFields,
		shouldHidePhoneField,
		activePaymentMethod,
		containerRef,
		mollieComponentsManager,
		isComponentReady,
		componentError,
	};

	return (
		<div ref={ containerRef } className="mollie-payment-method-container">
			{ createPaymentComponent( item, commonProps ) }
		</div>
	);
};

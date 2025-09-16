/* global mollieBlockData */
import { MOLLIE_STORE_KEY } from '../store';
import { createPaymentComponent } from './PaymentComponentFactory';
import { mollieComponentsManager } from '../services/MollieComponentsManager';

export const PaymentMethodContentRenderer = ( props ) => {
	const { useEffect, useRef } = wp.element;
	const { useSelect } = wp.data;

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

	// Redux store selectors
	const {
		selectedIssuer,
		inputPhone,
		inputBirthdate,
		inputCompany,
		cardToken,
		canCreateToken,
		isComponentReady,
		componentError,
	} = useSelect(
		( select ) => ( {
			selectedIssuer: select( MOLLIE_STORE_KEY ).getSelectedIssuer(),
			inputPhone: select( MOLLIE_STORE_KEY ).getInputPhone(),
			inputBirthdate: select( MOLLIE_STORE_KEY ).getInputBirthdate(),
			inputCompany: select( MOLLIE_STORE_KEY ).getInputCompany(),
			cardToken: select( MOLLIE_STORE_KEY ).getCardToken(),
			canCreateToken: select( MOLLIE_STORE_KEY ).getCanCreateToken(),
			isComponentReady: select( MOLLIE_STORE_KEY ).getIsComponentReady(),
			componentError: select( MOLLIE_STORE_KEY ).getComponentError(),
		} ),
		[]
	);

	// Initialize mollieComponentsManager when payment method changes
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
			// For non-Mollie gateways, return success immediately
			if ( ! activePaymentMethod.startsWith( 'mollie_wc_gateway_' ) ) {
				return responseTypes.SUCCESS;
			}

			try {
				let token = cardToken;

				// Create token for credit card payments if needed
				if (
					item.name === 'mollie_wc_gateway_creditcard' &&
					canCreateToken &&
					! token
				) {
					token = await mollieComponentsManager.createToken();
				}

				const paymentData = {
					payment_method: activePaymentMethod,
					payment_method_title: item.title,
					[ `mollie-payments-for-woocommerce_issuer_${ activePaymentMethod }` ]:
						selectedIssuer,
					billing_phone: inputPhone,
					billing_company_billie: inputCompany,
					billing_birthdate: inputBirthdate,
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

		const unsubscribePaymentProcessing =
			onPaymentSetup( onProcessingPayment );
		return unsubscribePaymentProcessing;
	}, [
		activePaymentMethod,
		item,
		selectedIssuer,
		inputPhone,
		inputCompany,
		inputBirthdate,
		cardToken,
		canCreateToken,
		onPaymentSetup,
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

/* global mollieBlockData */
import { useEffect, useRef } from '@wordpress/element';
import { useSelect, useDispatch, select } from '@wordpress/data';
import { MOLLIE_STORE_KEY } from '../store';
import { createPaymentComponent } from './PaymentComponentFactory';
import { mollieComponentsManager } from '../services/MollieComponentsManager';

export const PaymentMethodContentRenderer = ( props ) => {
    const {
        item,
        requiredFields,
        shouldHidePhoneField,
        props: {emitResponse, eventRegistration, billing, shippingData,}
    } = props;

	const { responseTypes } = emitResponse;
	const { onPaymentSetup } = eventRegistration;
	const containerRef = useRef( null );
    const { setComponentContainer, clearComponentContainer } = useDispatch( MOLLIE_STORE_KEY );

    // Only UI state that affects rendering should subscribe here
	const { isComponentReady, componentError } = useSelect(
		( select ) => ( {
			isComponentReady: select( MOLLIE_STORE_KEY ).getIsComponentReady(),
			componentError: select( MOLLIE_STORE_KEY ).getComponentError(),
		} ),
		[]
	);
    const activePaymentMethod = useSelect(
        ( select ) => select( MOLLIE_STORE_KEY ).getActivePaymentMethod(),
        []
    );
    useEffect( () => {
        if ( containerRef.current && activePaymentMethod ) {
            setComponentContainer( activePaymentMethod, containerRef.current );
        }

        return () => {
            if ( activePaymentMethod ) {
                clearComponentContainer( activePaymentMethod );
            }
        };
    }, [ activePaymentMethod, setComponentContainer, clearComponentContainer ] );

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
                const isComponentsEnabled = item.shouldLoadComponents || false;
                const isMultistepsCheckout = item.isMultiStepsCheckout || false;
                const canShowComponents = item.name === 'mollie_wc_gateway_creditcard'  &&  isComponentsEnabled && !isMultistepsCheckout
				if (canShowComponents && ! token ) {
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

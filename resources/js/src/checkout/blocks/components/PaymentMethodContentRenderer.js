import { MOLLIE_STORE_KEY } from '../store';
import { createPaymentComponent } from './PaymentComponentFactory';

/**
 * Main Mollie Component - Orchestrates payment method rendering
 * Handles common payment processing and delegates specific logic to child components
 * @param props
 */
export const PaymentMethodContentRenderer = ( props ) => {
	const { useEffect } = wp.element;
	const { useSelect } = wp.data;

	const {
		activePaymentMethod,
		billing,
		item,
		jQuery,
		emitResponse,
		eventRegistration,
		requiredFields,
		shippingData,
		isPhoneFieldVisible,
	} = props;

	const { responseTypes } = emitResponse;
	const { onPaymentSetup } = eventRegistration;

	// Redux store selectors - only for payment processing
	const selectedIssuer = useSelect(
		( select ) => select( MOLLIE_STORE_KEY ).getSelectedIssuer(),
		[]
	);
	const inputPhone = useSelect(
		( select ) => select( MOLLIE_STORE_KEY ).getInputPhone(),
		[]
	);
	const inputBirthdate = useSelect(
		( select ) => select( MOLLIE_STORE_KEY ).getInputBirthdate(),
		[]
	);
	const inputCompany = useSelect(
		( select ) => select( MOLLIE_STORE_KEY ).getInputCompany(),
		[]
	);

	const issuerKey =
		'mollie-payments-for-woocommerce_issuer_' + activePaymentMethod;

	// Main payment processing - stays centralized for all payment methods
	useEffect( () => {
		const onProcessingPayment = () => {
			const data = {
				payment_method: activePaymentMethod,
				payment_method_title: item.title,
				[ issuerKey ]: selectedIssuer,
				billing_phone: inputPhone,
				billing_company_billie: inputCompany,
				billing_birthdate: inputBirthdate,
				cardToken: '',
			};
			const tokenVal = jQuery( '.mollie-components > input' ).val();
			if ( tokenVal ) {
				data.cardToken = tokenVal;
			}
			return {
				type: responseTypes.SUCCESS,
				meta: {
					paymentMethodData: data,
				},
			};
		};

		const unsubscribePaymentProcessing =
			onPaymentSetup( onProcessingPayment );
		return () => {
			unsubscribePaymentProcessing();
		};
	}, [
		selectedIssuer,
		onPaymentSetup,
		inputPhone,
		inputCompany,
		inputBirthdate,
	] );

	// Prepare common props for child components
	const commonProps = {
		item,
		jQuery,
		useEffect,
		billing,
		shippingData,
		eventRegistration,
		requiredFields,
		isPhoneFieldVisible,
		activePaymentMethod,
	};

	return createPaymentComponent( item, commonProps );
};

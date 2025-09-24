import { PhoneField } from '../paymentFields/PhoneField';
import { BirthdateField } from '../paymentFields/BirthdateField';
import { CompanyField } from '../paymentFields/CompanyField';

/**
 * Generic Payment Fields Component
 * Handles field rendering and validation for different payment methods
 * @param {Object}   props                                 - The component props
 * @param {Object}   props.item                            - Payment method item configuration
 * @param {Function} props.jQuery                          - jQuery library function
 * @param {Function} props.useEffect                       - React useEffect hook
 * @param {Object}   props.billing                         - Billing data object
 * @param {Object}   props.shippingData                    - Shipping data object
 * @param {Object}   props.eventRegistration               - Event registration object
 * @param {Object}   props.requiredFields                  - Required field labels/strings
 * @param {boolean}  props.isPhoneFieldVisible             - Whether phone field is visible elsewhere
 * @param {string}   props.inputPhone                      - Current phone input value
 * @param {string}   props.inputBirthdate                  - Current birthdate input value
 * @param {string}   props.inputCompany                    - Current company input value
 * @param {string}   props.phonePlaceholder                - Phone field placeholder text
 * @param {Function} props.setInputPhone                   - Function to update phone input value
 * @param {Function} props.setInputBirthdate               - Function to update birthdate input value
 * @param {Function} props.setInputCompany                 - Function to update company input value
 * @param {Function} props.updatePhonePlaceholderByCountry - Function to update phone placeholder based on country
 * @param {Object}   [props.fieldConfig]                   - Optional field configuration object
 */
const PaymentFieldsComponent = ( {
	item,
	jQuery,
	useEffect,
	billing,
	shippingData,
	eventRegistration,
	requiredFields,
	isPhoneFieldVisible,
	inputPhone,
	inputBirthdate,
	inputCompany,
	phonePlaceholder,
	setInputPhone,
	setInputBirthdate,
	setInputCompany,
	updatePhonePlaceholderByCountry,
	fieldConfig = {},
} ) => {
	const { onCheckoutValidation } = eventRegistration;
	const { companyNameString, phoneString } = requiredFields;

	const {
		showCompany = false,
		showPhone = false,
		showBirthdate = false,
		companyRequired = false,
		phoneRequired = false,
		birthdateRequired = false,
		companyLabel = 'Company name',
		phoneLabel = 'Phone',
		birthdateLabel = 'Birthdate',
	} = fieldConfig;

	function getPhoneField() {
		const shippingPhone = document.getElementById( 'shipping-phone' );
		const billingPhone = document.getElementById( 'billing-phone' );
		return billingPhone || shippingPhone;
	}

	// Company field label update
	useEffect( () => {
		if ( ! showCompany ) {
			return;
		}

		const companyLabelElement = jQuery(
			'div.wc-block-components-text-input.wc-block-components-address-form__company > label'
		);
		if (
			companyLabelElement.length === 0 ||
			item.hideCompanyField === true
		) {
			return;
		}

		const labelText =
			item.companyPlaceholder || companyNameString || 'Company name';
		companyLabelElement.replaceWith(
			`<label htmlFor="shipping-company">${ labelText }</label>`
		);
	}, [
		showCompany,
		item.companyPlaceholder,
		item.hideCompanyField,
		companyNameString,
		jQuery,
	] );

	// Phone field label update
	useEffect( () => {
		if ( ! showPhone ) {
			return;
		}

		const phoneLabelElement = getPhoneField()?.labels?.[ 0 ] ?? null;
		if ( ! phoneLabelElement || phoneLabelElement.length === 0 ) {
			return;
		}

		const labelText = item.phonePlaceholder || phoneString || 'Phone';
		phoneLabelElement.innerText = labelText;
	}, [ showPhone, item.phonePlaceholder, phoneString ] );

	// Validation effect
	useEffect( () => {
		const unsubscribeProcessing = onCheckoutValidation( () => {
			// Company validation
			if ( companyRequired ) {
				const isCompanyEmpty =
					billing.billingData.company === '' &&
					shippingData.shippingAddress.company === '' &&
					inputCompany === '';

				if ( isCompanyEmpty ) {
					return {
						errorMessage:
							item.errorMessage || 'Company field is required',
					};
				}
			}

			// Phone validation
			if ( phoneRequired ) {
				const isPhoneEmpty =
					billing.billingData.phone === '' &&
					shippingData.shippingAddress.phone === '' &&
					inputPhone === '';

				if ( isPhoneEmpty ) {
					return {
						errorMessage:
							item.errorMessage || 'Phone field is required',
					};
				}
			}

			// Birthdate validation
			if ( birthdateRequired ) {
				if ( inputBirthdate === '' ) {
					return {
						errorMessage:
							item.errorMessage || 'Birthdate field is required',
					};
				}

				const today = new Date();
				const birthdate = new Date( inputBirthdate );
				if ( birthdate > today ) {
					return {
						errorMessage: item.errorMessage || 'Invalid birthdate',
					};
				}
			}
		} );

		return () => {
			unsubscribeProcessing();
		};
	}, [
		onCheckoutValidation,
		companyRequired,
		phoneRequired,
		birthdateRequired,
		billing.billingData,
		shippingData.shippingAddress,
		inputPhone,
		inputCompany,
		inputBirthdate,
		item.errorMessage,
	] );

	// Country-based phone placeholder update
	useEffect( () => {
		if ( ! showPhone ) {
			return;
		}

		const country = billing.billingData.country;
		if ( country ) {
			updatePhonePlaceholderByCountry( country );
		}
	}, [
		billing.billingData.country,
		updatePhonePlaceholderByCountry,
		showPhone,
	] );

	return (
		<>
			<div>{ item.content && <p>{ item.content }</p> }</div>

			{ showCompany && (
				<CompanyField
					label={ item.companyPlaceholder || companyLabel }
					value={ inputCompany }
					onChange={ setInputCompany }
				/>
			) }

			{ showBirthdate && (
				<BirthdateField
					label={ item.birthdatePlaceholder || birthdateLabel }
					value={ inputBirthdate }
					onChange={ setInputBirthdate }
				/>
			) }

			{ showPhone && ! isPhoneFieldVisible && (
				<PhoneField
					id={ `billing-phone-${ item.name }` }
					label={ item.phoneLabel || phoneLabel }
					value={ inputPhone }
					onChange={ setInputPhone }
					placeholder={ phonePlaceholder }
				/>
			) }
		</>
	);
};

export default PaymentFieldsComponent;

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
 * @param {boolean}  props.shouldHidePhoneField             - Whether phone field is visible elsewhere
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
	shouldHidePhoneField,
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

    console.log(fieldConfig);
	const {
		hasCustomCompanyField = false,
		hasCustomPhoneField = false,
		hasCustomBirthdateField = false,
		companyRequired = false,
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
		if ( ! hasCustomCompanyField ) {
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
		hasCustomCompanyField,
		item.companyPlaceholder,
		item.hideCompanyField,
		companyNameString,
		jQuery,
	] );

	// Phone field label update
	useEffect( () => {
		if ( ! hasCustomPhoneField ) {
			return;
		}

		const phoneLabelElement = getPhoneField()?.labels?.[ 0 ] ?? null;
		if ( ! phoneLabelElement || phoneLabelElement.length === 0 ) {
			return;
		}

		const labelText = item.phonePlaceholder || phoneString || 'Phone';
		phoneLabelElement.innerText = labelText;
	}, [ hasCustomPhoneField, item.phonePlaceholder, phoneString ] );

	// Validation effect
	useEffect( () => {
		const unsubscribeProcessing = onCheckoutValidation( () => {
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
		} );

		return () => {
			unsubscribeProcessing();
		};
	}, [
		onCheckoutValidation,
		companyRequired,
		billing.billingData,
		shippingData.shippingAddress,
		inputCompany,
		item.errorMessage,
	] );

	// Country-based phone placeholder update
	useEffect( () => {
		if ( ! hasCustomPhoneField ) {
			return;
		}

		const country = billing.billingData.country;
		if ( country ) {
			updatePhonePlaceholderByCountry( country );
		}
	}, [
		billing.billingData.country,
		updatePhonePlaceholderByCountry,
		hasCustomPhoneField,
	] );

    console.log( 'show phone 2', hasCustomPhoneField );
    console.log(shouldHidePhoneField)

	return (
		<>
			<div>{ item.content && <p>{ item.content }</p> }</div>

			{ hasCustomCompanyField && (
				<CompanyField
					label={ item.companyPlaceholder || companyLabel }
					value={ inputCompany }
					onChange={ setInputCompany }
				/>
			) }

			{ hasCustomBirthdateField && (
				<BirthdateField
					label={ item.birthdatePlaceholder || birthdateLabel }
					value={ inputBirthdate }
					onChange={ setInputBirthdate }
				/>
			) }

			{ hasCustomPhoneField && ! shouldHidePhoneField && (
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

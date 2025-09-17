import { ACTIONS } from './constants';

export const setSelectedIssuer = ( issuer ) => ( {
	type: ACTIONS.SET_SELECTED_ISSUER,
	payload: issuer,
} );

export const setInputPhone = ( phone ) => ( {
	type: ACTIONS.SET_INPUT_PHONE,
	payload: phone,
} );

export const setInputBirthdate = ( birthdate ) => ( {
	type: ACTIONS.SET_INPUT_BIRTHDATE,
	payload: birthdate,
} );

export const setInputCompany = ( company ) => ( {
	type: ACTIONS.SET_INPUT_COMPANY,
	payload: company,
} );

export const setActivePaymentMethod = ( method ) => ( {
	type: ACTIONS.SET_ACTIVE_PAYMENT_METHOD,
	payload: method,
} );

export const setPaymentItemData = ( itemData ) => ( {
	type: ACTIONS.SET_PAYMENT_ITEM_DATA,
	payload: itemData,
} );

export const setRequiredFields = ( fields ) => ( {
	type: ACTIONS.SET_REQUIRED_FIELDS,
	payload: fields,
} );

export const setPhoneFieldVisible = ( isVisible ) => ( {
	type: ACTIONS.SET_PHONE_FIELD_VISIBLE,
	payload: isVisible,
} );

export const setBillingData = ( billing ) => ( {
	type: ACTIONS.SET_BILLING_DATA,
	payload: billing,
} );

export const setShippingData = ( shipping ) => ( {
	type: ACTIONS.SET_SHIPPING_DATA,
	payload: shipping,
} );

export const setValidationState = ( isValid ) => ( {
	type: ACTIONS.SET_VALIDATION_STATE,
	payload: isValid,
} );

export const setPhonePlaceholder = ( placeholder ) => ( {
	type: ACTIONS.SET_PHONE_PLACEHOLDER,
	payload: placeholder,
} );

export const setCardToken = ( token ) => ( {
	type: ACTIONS.SET_CARD_TOKEN,
	payload: token,
} );

export const updatePhonePlaceholderByCountry = ( country ) => ( { dispatch } ) => {
	const countryCodes = {
		BE: '+32xxxxxxxxx',
		NL: '+316xxxxxxxx',
		DE: '+49xxxxxxxxx',
		AT: '+43xxxxxxxxx',
	};
	const placeholder = countryCodes[ country ] || countryCodes.NL;
	dispatch( setPhonePlaceholder( placeholder ) );
};

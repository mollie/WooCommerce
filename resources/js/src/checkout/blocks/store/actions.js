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

export const updatePhonePlaceholderByCountry =
	( country ) =>
	( { dispatch } ) => {
		const countryCodes = {
			BE: '+32xxxxxxxxx',
			NL: '+316xxxxxxxx',
			DE: '+49xxxxxxxxx',
			AT: '+43xxxxxxxxx',
            ES: '+34xxxxxxxxx'
		};
		const placeholder = countryCodes[ country ] || countryCodes.NL;
		dispatch( setPhonePlaceholder( placeholder ) );
	};

export const setComponentInitializing = ( isInitializing ) => ( {
	type: ACTIONS.SET_COMPONENT_INITIALIZING,
	payload: isInitializing,
} );

export const setComponentInitialized = ( isInitialized ) => ( {
	type: ACTIONS.SET_COMPONENT_INITIALIZED,
	payload: isInitialized,
} );

export const setComponentError = ( error ) => ( {
	type: ACTIONS.SET_COMPONENT_ERROR,
	payload: error,
} );

export const clearComponentError = () => ( {
	type: ACTIONS.CLEAR_COMPONENT_ERROR,
} );

// Component Mounting Actions
export const setComponentMounting = ( gateway, isMounting ) => ( {
	type: ACTIONS.SET_COMPONENT_MOUNTING,
	payload: { gateway, isMounting },
} );

export const setComponentMounted = ( gateway, isMounted ) => ( {
	type: ACTIONS.SET_COMPONENT_MOUNTED,
	payload: { gateway, isMounted },
} );

export const setComponentFocused = ( componentName, isFocused ) => ( {
	type: ACTIONS.SET_COMPONENT_FOCUSED,
	payload: { componentName, isFocused },
} );

// Token Management Actions
export const setTokenCreating = ( isCreating ) => ( {
	type: ACTIONS.SET_TOKEN_CREATING,
	payload: isCreating,
} );

export const setTokenCreated = ( isCreated ) => ( {
	type: ACTIONS.SET_TOKEN_CREATED,
	payload: isCreated,
} );

export const setTokenError = ( error ) => ( {
	type: ACTIONS.SET_TOKEN_ERROR,
	payload: error,
} );

export const clearTokenError = () => ( {
	type: ACTIONS.CLEAR_TOKEN_ERROR,
} );

export const clearTokenData = () => ( {
	type: ACTIONS.CLEAR_TOKEN_DATA,
} );

// Component State Actions
export const setComponentsReady = ( gateway, isReady ) => ( {
	type: ACTIONS.SET_COMPONENTS_READY,
	payload: { gateway, isReady },
} );

export const setGatewayComponents = ( gateway, components ) => ( {
	type: ACTIONS.SET_GATEWAY_COMPONENTS,
	payload: { gateway, components },
} );
export const setComponentContainer = ( gateway, container ) => ( {
    type: ACTIONS.SET_COMPONENT_CONTAINER,
    payload: { gateway, container },
} );

export const clearComponentContainer = ( gateway ) => ( {
    type: ACTIONS.CLEAR_COMPONENT_CONTAINER,
    payload: { gateway },
} );

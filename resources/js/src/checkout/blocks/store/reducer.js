import { ACTIONS } from './constants';

const initialState = {
	// Form fields
	selectedIssuer: '',
	inputPhone: '',
	inputBirthdate: '',
	inputCompany: '',

	// Payment method state
	activePaymentMethod: '',
	paymentItemData: {},

	// Configuration
	requiredFields: {
		companyNameString: '',
		phoneString: '',
	},
	isPhoneFieldVisible: true,
	billingData: {},
	shippingData: {},

	// Validation
	isValid: true,

	// Country settings
	phonePlaceholder: '+316xxxxxxxx',

	// Credit card
	cardToken: '',
	componentInitializing: false,
	componentInitialized: false,
	componentError: null,

	// Component Mounting State (per gateway)
	componentMounting: {}, // { gateway: boolean }
	componentMounted: {}, // { gateway: boolean }
	componentFocused: {}, // { componentName: boolean }

	// Token State
	tokenCreating: false,
	tokenCreated: false,
	tokenError: null,

	// Component Readiness (per gateway)
	componentsReady: {}, // { gateway: boolean }
	gatewayComponents: {}, // { gateway: Array<string> }
};

const reducer = ( state = initialState, action ) => {
	switch ( action.type ) {
		case ACTIONS.SET_SELECTED_ISSUER:
			return { ...state, selectedIssuer: action.payload };

		case ACTIONS.SET_INPUT_PHONE:
			return { ...state, inputPhone: action.payload };

		case ACTIONS.SET_INPUT_BIRTHDATE:
			return { ...state, inputBirthdate: action.payload };

		case ACTIONS.SET_INPUT_COMPANY:
			return { ...state, inputCompany: action.payload };

		case ACTIONS.SET_ACTIVE_PAYMENT_METHOD:
			return { ...state, activePaymentMethod: action.payload };

		case ACTIONS.SET_PAYMENT_ITEM_DATA:
			return { ...state, paymentItemData: action.payload };

		case ACTIONS.SET_REQUIRED_FIELDS:
			return {
				...state,
				requiredFields: { ...state.requiredFields, ...action.payload },
			};

		case ACTIONS.SET_PHONE_FIELD_VISIBLE:
			return { ...state, isPhoneFieldVisible: action.payload };

		case ACTIONS.SET_BILLING_DATA:
			return { ...state, billingData: action.payload };

		case ACTIONS.SET_SHIPPING_DATA:
			return { ...state, shippingData: action.payload };

		case ACTIONS.SET_VALIDATION_STATE:
			return { ...state, isValid: action.payload };

		case ACTIONS.SET_PHONE_PLACEHOLDER:
			return { ...state, phonePlaceholder: action.payload };

		case ACTIONS.SET_CARD_TOKEN:
			return { ...state, cardToken: action.payload };
		case ACTIONS.SET_COMPONENT_INITIALIZING:
			return { ...state, componentInitializing: action.payload };

		case ACTIONS.SET_COMPONENT_INITIALIZED:
			return { ...state, componentInitialized: action.payload };

		case ACTIONS.SET_COMPONENT_ERROR:
			return { ...state, componentError: action.payload };

		case ACTIONS.CLEAR_COMPONENT_ERROR:
			return { ...state, componentError: null };

		case ACTIONS.SET_COMPONENT_MOUNTING:
			return {
				...state,
				componentMounting: {
					...state.componentMounting,
					[ action.payload.gateway ]: action.payload.isMounting,
				},
			};

		case ACTIONS.SET_COMPONENT_MOUNTED:
			return {
				...state,
				componentMounted: {
					...state.componentMounted,
					[ action.payload.gateway ]: action.payload.isMounted,
				},
			};

		case ACTIONS.SET_COMPONENT_FOCUSED:
			return {
				...state,
				componentFocused: {
					...state.componentFocused,
					[ action.payload.componentName ]: action.payload.isFocused,
				},
			};

		case ACTIONS.SET_TOKEN_CREATING:
			return { ...state, tokenCreating: action.payload };

		case ACTIONS.SET_TOKEN_CREATED:
			return { ...state, tokenCreated: action.payload };

		case ACTIONS.SET_TOKEN_ERROR:
			return { ...state, tokenError: action.payload };

		case ACTIONS.CLEAR_TOKEN_ERROR:
			return { ...state, tokenError: null };

		case ACTIONS.CLEAR_TOKEN_DATA:
			return {
				...state,
				cardToken: '',
				tokenCreated: false,
				tokenCreating: false,
				tokenError: null,
			};

		case ACTIONS.SET_COMPONENTS_READY:
			return {
				...state,
				componentsReady: {
					...state.componentsReady,
					[ action.payload.gateway ]: action.payload.isReady,
				},
			};

		case ACTIONS.SET_GATEWAY_COMPONENTS:
			return {
				...state,
				gatewayComponents: {
					...state.gatewayComponents,
					[ action.payload.gateway ]: action.payload.components,
				},
			};

		default:
			return state;
	}
};

export default reducer;

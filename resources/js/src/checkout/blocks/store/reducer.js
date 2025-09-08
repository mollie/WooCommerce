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
        phoneString: ''
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
};

const reducer = (state = initialState, action) => {
    switch (action.type) {
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
            return { ...state, requiredFields: { ...state.requiredFields, ...action.payload } };

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
        default:
            return state;
    }
};

export default reducer;

const selectors = {
	// Form field selectors
	getSelectedIssuer: ( state ) => state.selectedIssuer,
	getInputPhone: ( state ) => state.inputPhone,
	getInputBirthdate: ( state ) => state.inputBirthdate,
	getInputCompany: ( state ) => state.inputCompany,

	// Payment method selectors
	getActivePaymentMethod: ( state ) => state.activePaymentMethod,
	getPaymentItemData: ( state ) => state.paymentItemData,

	// Configuration selectors
	getRequiredFields: ( state ) => state.requiredFields,
	getshouldHidePhoneField: ( state ) => state.shouldHidePhoneField,
	getBillingData: ( state ) => state.billingData,
	getShippingData: ( state ) => state.shippingData,

	// Validation selectors
	getIsValid: ( state ) => state.isValid,

	// Country settings
	getPhonePlaceholder: ( state ) => state.phonePlaceholder,

	// Credit card
	getCardToken: ( state ) => state.cardToken,

	// Computed selectors
	getIssuerKey: ( state ) =>
		`mollie-payments-for-woocommerce_issuer_${ state.activePaymentMethod }`,

	// Component Status
	getComponentInitializing: ( state ) => state.componentInitializing,
	getComponentInitialized: ( state ) => state.componentInitialized,
	getComponentError: ( state ) => state.componentError,

	// Component Mounting Status
	getComponentMounting: ( state, gateway ) =>
		state.componentMounting[ gateway ] || false,
	getComponentMounted: ( state, gateway ) =>
		state.componentMounted[ gateway ] || false,
	getComponentFocused: ( state, componentName ) =>
		state.componentFocused[ componentName ] || false,

	getTokenCreating: ( state ) => state.tokenCreating,
	getTokenCreated: ( state ) => state.tokenCreated,
	getTokenError: ( state ) => state.tokenError,

	getComponentsReady: ( state, gateway ) =>
		state.componentsReady[ gateway ] || false,
	getGatewayComponents: ( state, gateway ) =>
		state.gatewayComponents[ gateway ] || [],

	getIsComponentReady: ( state ) => {
		const activePaymentMethod = state.activePaymentMethod;
		return (
			state.componentInitialized &&
			state.componentMounted[ activePaymentMethod ] &&
			! state.componentError &&
			! state.componentInitializing
		);
	},

	getIsTokenReady: ( state ) => {
		return (
			state.tokenCreated &&
			state.cardToken &&
			! state.tokenError &&
			! state.tokenCreating
		);
	},

	getCanCreateToken: ( state ) => {
		const activePaymentMethod = state.activePaymentMethod;
		return (
			selectors.getIsComponentReady( state ) &&
			! state.tokenCreating &&
			activePaymentMethod &&
			state.componentMounted[ activePaymentMethod ]
		);
	},

	getPaymentMethodData: ( state ) => ( {
		payment_method: state.activePaymentMethod,
		payment_method_title: state.paymentItemData.title,
		[ `mollie-payments-for-woocommerce_issuer_${ state.activePaymentMethod }` ]:
			state.selectedIssuer,
		billing_phone: state.inputPhone,
		billing_company_billie: state.inputCompany,
		billing_birthdate: state.inputBirthdate,
		cardToken: state.cardToken,
		tokenCreated: state.tokenCreated,
		componentsReady: selectors.getIsComponentReady( state ),
	} ),
};

export default selectors;

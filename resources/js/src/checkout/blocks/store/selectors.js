const selectors = {
    // Form field selectors
    getSelectedIssuer: (state) => state.selectedIssuer,
    getInputPhone: (state) => state.inputPhone,
    getInputBirthdate: (state) => state.inputBirthdate,
    getInputCompany: (state) => state.inputCompany,

    // Payment method selectors
    getActivePaymentMethod: (state) => state.activePaymentMethod,
    getPaymentItemData: (state) => state.paymentItemData,

    // Configuration selectors
    getRequiredFields: (state) => state.requiredFields,
    getIsPhoneFieldVisible: (state) => state.isPhoneFieldVisible,
    getBillingData: (state) => state.billingData,
    getShippingData: (state) => state.shippingData,

    // Validation selectors
    getIsValid: (state) => state.isValid,

    // Country settings
    getPhonePlaceholder: (state) => state.phonePlaceholder,

    // Credit card
    getCardToken: (state) => state.cardToken,

    // Computed selectors
    getIssuerKey: (state) =>
        `mollie-payments-for-woocommerce_issuer_${state.activePaymentMethod}`,

    getPaymentMethodData: (state) => ({
        payment_method: state.activePaymentMethod,
        payment_method_title: state.paymentItemData.title,
        [selectors.getIssuerKey(state)]: state.selectedIssuer,
        billing_phone: state.inputPhone,
        billing_company_billie: state.inputCompany,
        billing_birthdate: state.inputBirthdate,
        cardToken: state.cardToken,
    }),

    // Validation computed selectors
    getIsCompanyEmpty: (state) =>
        (state.billingData.company === '' && state.shippingData.company === '') && state.inputCompany === '',

    getIsPhoneEmpty: (state) =>
        (state.billingData.phone === '' && state.shippingData.phone === '') && state.inputPhone === '',

    getIsBirthdateValid: (state) => {
        if (state.inputBirthdate === '') return false;
        const today = new Date();
        const birthdate = new Date(state.inputBirthdate);
        return birthdate <= today;
    },
};

export default selectors;

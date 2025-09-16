
/**
 * Build context for payment method registration
 * @param wc
 * @param jQuery
 */
export const buildRegistrationContext = ( wc, jQuery ) => {
	const { defaultFields } = wc.wcSettings.allSettings;

	return {
		wc,
		requiredFields: {
			companyNameString: defaultFields.company.label,
			phoneString: defaultFields.phone.label,
		},
	};
};

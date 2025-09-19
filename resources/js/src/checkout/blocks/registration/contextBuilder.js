
/**
 * Build context for payment method registration
 * @param wc
 */
export const buildRegistrationContext = ( wc ) => {
	const { defaultFields } = wc.wcSettings.allSettings;

	return {
		wc,
		requiredFields: {
			companyNameString: defaultFields.company.label,
			phoneString: defaultFields.phone.label,
		},
	};
};

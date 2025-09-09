import { getPhoneFieldVisibility } from '../../../shared/utils/paymentUtils';

/**
 * Build context for payment method registration
 * @param wc
 * @param jQuery
 */
export const buildRegistrationContext = ( wc, jQuery ) => {
	const { defaultFields } = wc.wcSettings.allSettings;

	return {
		wc,
		jQuery,
		requiredFields: {
			companyNameString: defaultFields.company.label,
			phoneString: defaultFields.phone.label,
		},
		isPhoneFieldVisible: getPhoneFieldVisibility(),
	};
};

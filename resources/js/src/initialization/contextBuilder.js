import { getPhoneFieldVisibility } from '../utils/paymentUtils';

/**
 * Build context for payment method registration
 */
export const buildRegistrationContext = (wc, jQuery) => {
    const { defaultFields } = wc.wcSettings.allSettings;

    return {
        wc,
        jQuery,
        requiredFields: {
            companyNameString: defaultFields.company.label,
            phoneString: defaultFields.phone.label,
        },
        isPhoneFieldVisible: getPhoneFieldVisibility()
    };
};

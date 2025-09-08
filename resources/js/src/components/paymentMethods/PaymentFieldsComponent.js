import { PhoneField } from '../paymentFields/PhoneField';
import { BirthdateField } from '../paymentFields/BirthdateField';
import { CompanyField } from '../paymentFields/CompanyField';

/**
 * Generic Payment Fields Component
 * Handles field rendering and validation for different payment methods
 */
const PaymentFieldsComponent = ({
                                    item,
                                    jQuery,
                                    useEffect,
                                    billing,
                                    shippingData,
                                    eventRegistration,
                                    requiredFields,
                                    isPhoneFieldVisible,
                                    // Store props injected by withMollieStore HOC
                                    inputPhone,
                                    inputBirthdate,
                                    inputCompany,
                                    phonePlaceholder,
                                    setInputPhone,
                                    setInputBirthdate,
                                    setInputCompany,
                                    updatePhonePlaceholderByCountry,
                                    fieldConfig = {}
                                }) => {
    const { onCheckoutValidation } = eventRegistration;
    const { companyNameString, phoneString } = requiredFields;

    const {
        showCompany = false,
        showPhone = false,
        showBirthdate = false,
        companyRequired = false,
        phoneRequired = false,
        birthdateRequired = false,
        companyLabel = "Company name",
        phoneLabel = "Phone",
        birthdateLabel = "Birthdate"
    } = fieldConfig;

    function getPhoneField() {
        const shippingPhone = document.getElementById('shipping-phone');
        const billingPhone = document.getElementById('billing-phone');
        return billingPhone || shippingPhone;
    }

    // Company field label update
    useEffect(() => {
        if (!showCompany) return;

        let companyLabel = jQuery('div.wc-block-components-text-input.wc-block-components-address-form__company > label');
        if (companyLabel.length === 0 || item.hideCompanyField === true) {
            return;
        }

        const labelText = item.companyPlaceholder || companyNameString || "Company name";
        companyLabel.replaceWith(`<label htmlFor="shipping-company">${labelText}</label>`);
    }, [showCompany, item.companyPlaceholder, item.hideCompanyField, companyNameString, jQuery]);

    // Phone field label update
    useEffect(() => {
        if (!showPhone) return;

        let phoneLabel = getPhoneField()?.labels?.[0] ?? null;
        if (!phoneLabel || phoneLabel.length === 0) {
            return;
        }

        const labelText = item.phonePlaceholder || phoneString || "Phone";
        phoneLabel.innerText = labelText;
    }, [showPhone, item.phonePlaceholder, phoneString]);

    // Validation effect
    useEffect(() => {
        const unsubscribeProcessing = onCheckoutValidation(() => {
            // Company validation
            if (companyRequired) {
                const isCompanyEmpty = (
                    billing.billingData.company === '' &&
                    shippingData.shippingAddress.company === ''
                ) && inputCompany === '';

                if (isCompanyEmpty) {
                    return {
                        errorMessage: item.errorMessage || 'Company field is required',
                    };
                }
            }

            // Phone validation
            if (phoneRequired) {
                const isPhoneEmpty = (
                    billing.billingData.phone === '' &&
                    shippingData.shippingAddress.phone === ''
                ) && inputPhone === '';

                if (isPhoneEmpty) {
                    return {
                        errorMessage: item.errorMessage || 'Phone field is required',
                    };
                }
            }

            // Birthdate validation
            if (birthdateRequired) {
                if (inputBirthdate === '') {
                    return {
                        errorMessage: item.errorMessage || 'Birthdate field is required',
                    };
                }

                const today = new Date();
                const birthdate = new Date(inputBirthdate);
                if (birthdate > today) {
                    return {
                        errorMessage: item.errorMessage || 'Invalid birthdate',
                    };
                }
            }
        });

        return () => {
            unsubscribeProcessing();
        };
    }, [
        onCheckoutValidation,
        companyRequired,
        phoneRequired,
        birthdateRequired,
        billing.billingData,
        shippingData.shippingAddress,
        inputPhone,
        inputCompany,
        inputBirthdate,
        item.errorMessage
    ]);

    // Country-based phone placeholder update
    useEffect(() => {
        if (!showPhone) return;

        const country = billing.billingData.country;
        if (country) {
            updatePhonePlaceholderByCountry(country);
        }
    }, [billing.billingData.country, updatePhonePlaceholderByCountry, showPhone]);

    return (
        <>
            <div>{item.content && <p>{item.content}</p>}</div>

            {showCompany && (
                <CompanyField
                    label={item.companyPlaceholder || companyLabel}
                    value={inputCompany}
                    onChange={setInputCompany}
                />
            )}

            {showBirthdate && (
                <BirthdateField
                    label={item.birthdatePlaceholder || birthdateLabel}
                    value={inputBirthdate}
                    onChange={setInputBirthdate}
                />
            )}

            {showPhone && !isPhoneFieldVisible && (
                <PhoneField
                    id={`billing-phone-${item.name}`}
                    label={item.phoneLabel || phoneLabel}
                    value={inputPhone}
                    onChange={setInputPhone}
                    placeholder={phonePlaceholder}
                />
            )}
        </>
    );
};

export default PaymentFieldsComponent;

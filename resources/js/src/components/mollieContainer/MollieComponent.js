import { IssuerSelect } from '../paymentFields/IssuerSelect';
import { PhoneField } from '../paymentFields/PhoneField';
import { BirthdateField } from '../paymentFields/BirthdateField';
import { CompanyField } from '../paymentFields/CompanyField';
import { CreditCardField } from '../paymentFields/CreditCardField';
import {MOLLIE_STORE_KEY} from "../../store";
export const MollieComponent = (props) => {
    const {useEffect} = wp.element;
    const {useSelect, useDispatch } = wp.data;
    let {activePaymentMethod, billing, item, jQuery, emitResponse, eventRegistration, requiredFields, shippingData, isPhoneFieldVisible} = props
    const {  responseTypes } = emitResponse;
    const {onPaymentSetup, onCheckoutValidation} = eventRegistration;

    const selectedIssuer = useSelect((select) =>
        select(MOLLIE_STORE_KEY).getSelectedIssuer(), []
    );
    const inputPhone = useSelect((select) =>
        select(MOLLIE_STORE_KEY).getInputPhone(), []
    );
    const inputBirthdate = useSelect((select) =>
        select(MOLLIE_STORE_KEY).getInputBirthdate(), []
    );
    const inputCompany = useSelect((select) =>
        select(MOLLIE_STORE_KEY).getInputCompany(), []
    );
    const phonePlaceholder = useSelect((select) =>
        select(MOLLIE_STORE_KEY).getPhonePlaceholder(), []
    );

    const { setSelectedIssuer, setInputPhone, setInputBirthdate, setInputCompany, updatePhonePlaceholderByCountry } = useDispatch(MOLLIE_STORE_KEY);

    const issuerKey = 'mollie-payments-for-woocommerce_issuer_' + activePaymentMethod
    const {companyNameString, phoneString} = requiredFields

    function getPhoneField()
    {
        const shippingPhone = document.getElementById('shipping-phone');
        const billingPhone = document.getElementById('billing-phone');
        return billingPhone || shippingPhone;
    }

    useEffect(() => {
        const onProcessingPayment = () => {
            let data = {
                payment_method: activePaymentMethod,
                payment_method_title: item.title,
                [issuerKey]: selectedIssuer,
                billing_phone: inputPhone,
                billing_company_billie: inputCompany,
                billing_birthdate: inputBirthdate,
                cardToken: '',
            };
            const tokenVal = jQuery('.mollie-components > input').val()
            if (tokenVal) {
                data.cardToken = tokenVal;
            }
            return {
                type: responseTypes.SUCCESS,
                meta: {
                    paymentMethodData: data
                },
            };
        }

        const unsubscribePaymentProcessing = onPaymentSetup(
            onProcessingPayment
        );
        return () => {
            unsubscribePaymentProcessing()
        };

    }, [selectedIssuer, onPaymentSetup, inputPhone, inputCompany, inputBirthdate])

    useEffect(() => {
        let companyLabel = jQuery('div.wc-block-components-text-input.wc-block-components-address-form__company > label')
        if (companyLabel.length === 0 || item.hideCompanyField === true) {
            return
        }

        if (activePaymentMethod === 'mollie_wc_gateway_billie') {
            let message = item.companyPlaceholder
            companyLabel.replaceWith('<label htmlFor="shipping-company">' + message + '</label>')
        } else {
            if (companyNameString !== false) {
                companyLabel.replaceWith('<label htmlFor="shipping-company">' + companyNameString + '</label>')
            }
        }
        let isCompanyEmpty = (billing.billingData.company === '' && shippingData.shippingAddress.company === '') && inputCompany === '';
        const unsubscribeProcessing = onCheckoutValidation(
            () => {
                if (activePaymentMethod === 'mollie_wc_gateway_billie' && isCompanyEmpty) {
                    return {
                        errorMessage: item.errorMessage,
                    };
                }
            }
        );
        return () => {
            unsubscribeProcessing()
        };

    }, [activePaymentMethod, onCheckoutValidation, billing.billingData, item, companyNameString, inputCompany]);

    useEffect(() => {
        let phoneLabel = getPhoneField()?.labels?.[0] ?? null;
        if (!phoneLabel || phoneLabel.length === 0) {
            return
        }
        if (activePaymentMethod === 'mollie_wc_gateway_in3') {
            phoneLabel.innerText = item.phonePlaceholder
        } else {
            if (phoneString !== false) {
                phoneLabel.innerText = phoneString
            }
        }
        let isPhoneEmpty = (billing.billingData.phone === '' && shippingData.shippingAddress.phone === '') && inputPhone === '';
        let isBirthdateValid = inputBirthdate === ''
        let today = new Date();
        let birthdate = new Date(inputBirthdate);
        if (birthdate > today) {
            isBirthdateValid = false
        }
        const unsubscribeProcessing = onCheckoutValidation(

            () => {
                if (activePaymentMethod === 'mollie_wc_gateway_in3' && (isPhoneEmpty || isBirthdateValid)) {
                    return {
                        errorMessage: item.errorMessage,
                    };
                }
            }
        );
        return () => {
            unsubscribeProcessing()
        };

    }, [activePaymentMethod, onCheckoutValidation, billing.billingData, shippingData.shippingAddress, item, phoneString, inputBirthdate, inputPhone]);

    useEffect(() => {
        const country = billing.billingData.country;
        if (country) {
            updatePhonePlaceholderByCountry(country);
        }
    }, [billing.billingData.country, updatePhonePlaceholderByCountry]);

    let itemContentP = item.content;
    if (item.content !== '') {
        itemContentP = <p>{item.content}</p>;
    }

    // Issuer select (banks, etc.)
    if (item.issuers && item.name !== "mollie_wc_gateway_creditcard") {
        return (
            <div>
                {itemContentP}
                <IssuerSelect
                    issuerKey={issuerKey}
                    issuers={item.issuers}
                    selectedIssuer={selectedIssuer}
                    updateIssuer={setSelectedIssuer}
                />
            </div>
        );
    }

    // Credit card
    if (item.name === "mollie_wc_gateway_creditcard") {
        return <CreditCardField content={item.content} />;
    }

    // Billie (company field)
    if (item.name === "mollie_wc_gateway_billie") {
        const billingCompanyField = document.querySelector('#billing-company');
        const shippingCompanyField = document.querySelector('#shipping-company');
        const isBillingCompanyRequired = billingCompanyField?.hasAttribute('required');
        const isShippingCompanyRequired = shippingCompanyField?.hasAttribute('required');

        if ((billingCompanyField && isBillingCompanyRequired) || (shippingCompanyField && isShippingCompanyRequired) || item.hideCompanyField === true) {
            return <div>{itemContentP}</div>;
        }

        return (
            <>
                <div>{itemContentP}</div>
                <CompanyField
                    label={item.companyPlaceholder || "Company name"}
                    value={inputCompany}
                    onChange={setInputCompany}
                />
            </>
        );
    }

    // In3 (birthdate + phone)
    if (item.name === "mollie_wc_gateway_in3") {
        const birthdateField = item.birthdatePlaceholder || "Birthdate";
        const phoneField = phonePlaceholder || "+316xxxxxxxx";
        const phoneLabel = item.phoneLabel || "Phone";

        return (
            <>
                <div>{itemContentP}</div>
                <BirthdateField
                    label={birthdateField}
                    value={inputBirthdate}
                    onChange={setInputBirthdate}
                />
                {!isPhoneFieldVisible && (
                    <PhoneField
                        id="billing-phone-in3"
                        label={phoneLabel}
                        value={inputPhone}
                        onChange={setInputPhone}
                        placeholder={phoneField}
                    />
                )}
            </>
        );
    }

    // Riverty (birthdate + phone)
    if (item.name === "mollie_wc_gateway_riverty") {
        const birthdateField = item.birthdatePlaceholder || "Birthdate";
        const phoneField = phonePlaceholder || "+316xxxxxxxx";
        const phoneLabel = item.phoneLabel || "Phone";

        return (
            <>
                <div>{itemContentP}</div>
                <BirthdateField
                    label={birthdateField}
                    value={inputBirthdate}
                    onChange={setInputBirthdate}
                />
                {!isPhoneFieldVisible && (
                    <PhoneField
                        id="billing-phone-riverty"
                        label={phoneLabel}
                        value={inputPhone}
                        onChange={setInputPhone}
                        placeholder={phoneField}
                    />
                )}
            </>
        );
    }
    return <div>{itemContentP}</div>
}

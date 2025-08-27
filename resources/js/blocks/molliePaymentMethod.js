
let onSubmitLocal
let creditCardSelected = new Event("mollie_creditcard_component_selected", {bubbles: true});
const MollieComponent = (props) => {
    let {onSubmit, activePaymentMethod, billing, item, useEffect, jQuery, emitResponse, eventRegistration, requiredFields, shippingData, isPhoneFieldVisible} = props
    const {  responseTypes } = emitResponse;
    const {onPaymentSetup, onCheckoutValidation} = eventRegistration;
    const [ selectedIssuer, selectIssuer ] = wp.element.useState('');
    const [ inputPhone, selectPhone ] = wp.element.useState('');
    const [ inputBirthdate, selectBirthdate ] = wp.element.useState('');
    const [ inputCompany, selectCompany ] = wp.element.useState('');
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

    onSubmitLocal = onSubmit
    const updateIssuer = (e) => selectIssuer(e.target.value);
    const updateCompany = (e) => selectCompany(e.target.value);
    const updatePhone = (e) => selectPhone(e.target.value);
    const updateBirthdate = (e) => selectBirthdate( e.target.value );

    let itemContentP = item.content;
    if (item.content !== '') {
        itemContentP = <p>{item.content}</p>;
    }

    function fieldMarkup(id, fieldType, label, action, value, placeholder = null) {
        const className = "wc-block-components-text-input wc-block-components-address-form__" + id;
        return <div class="custom-input">
            <label htmlFor={id} dangerouslySetInnerHTML={{__html: label}}></label>
            <input type={fieldType} className={className} name={id} id={id} value={value} onChange={action} placeholder={placeholder}></input>
        </div>
    }

    if (item.issuers && item.name !== "mollie_wc_gateway_creditcard"){
        return <div>{itemContentP}<select name={issuerKey} dangerouslySetInnerHTML={ {__html: item.issuers} } value={selectedIssuer} onChange={updateIssuer}></select></div>
    }

    if(item.name === "mollie_wc_gateway_creditcard"){
        return <div dangerouslySetInnerHTML={ {__html: item.content} }></div>;
    }

    if (item.name === "mollie_wc_gateway_billie") {
        const billingCompanyField = document.querySelector('#billing-company');
        const shippingCompanyField = document.querySelector('#shipping-company');
        const isBillingCompanyRequired = billingCompanyField?.hasAttribute('required');
        const isShippingCompanyRequired = shippingCompanyField?.hasAttribute('required');

        if ((billingCompanyField && isBillingCompanyRequired) || (shippingCompanyField && isShippingCompanyRequired) || item.hideCompanyField === true) {
            return;
        }

        const companyField = item.companyPlaceholder ? item.companyPlaceholder : "Company name";
        return (
            <>
                <div>{itemContentP}</div>
                {fieldMarkup("billing_company_billie","text", companyField, updateCompany, inputCompany)}
            </>
        );
    }

    useEffect(() => {
        const countryCodes = {
            BE: '+32xxxxxxxxx',
            NL: '+316xxxxxxxx',
            DE: '+49xxxxxxxxx',
            AT: '+43xxxxxxxxx',
        };
        const country = billing.billingData.country;
        item.phonePlaceholder = countryCodes[country] || countryCodes['NL'];
    }, [billing.billingData.country]);

    if (item.name === "mollie_wc_gateway_in3") {
        const birthdateField = item.birthdatePlaceholder || "Birthdate";
        const phoneField = item.phonePlaceholder || "+316xxxxxxxx";
        const phoneLabel = item.phoneLabel || "Phone";
        return (
            <>
                <div>{itemContentP}</div>
                {fieldMarkup("billing-birthdate", "date", birthdateField, updateBirthdate, inputBirthdate)}
                {!isPhoneFieldVisible && fieldMarkup("billing-phone-in3", "tel", phoneLabel, updatePhone, inputPhone, phoneField)}
            </>
        );
    }

    if (item.name === "mollie_wc_gateway_riverty") {
        const birthdateField = item.birthdatePlaceholder || "Birthdate";
        const phoneField = item.phonePlaceholder || "+316xxxxxxxx";
        const phoneLabel = item.phoneLabel || "Phone";
        return (
            <>
                <div>{itemContentP}</div>
                {fieldMarkup("billing-birthdate", "date", birthdateField, updateBirthdate, inputBirthdate)}
                {!isPhoneFieldVisible && fieldMarkup("billing-phone-riverty", "tel", phoneLabel, updatePhone, inputPhone, phoneField)}
            </>
        );
    }

    return <div>{itemContentP}</div>
}

const Label = ({ item }) => {
    return (
        <>
            <div dangerouslySetInnerHTML={{ __html: item.label }}/>
        </>
    );
};

const molliePaymentMethod = (useEffect, item, jQuery, requiredFields, isPhoneFieldVisible) =>{

    if (item.name === "mollie_wc_gateway_creditcard") {
        document.addEventListener('mollie_components_ready_to_submit', function () {
            onSubmitLocal()
        })
    }
    function creditcardSelectedEvent() {
        if (item.name === "mollie_wc_gateway_creditcard") {
            document.documentElement.dispatchEvent(creditCardSelected);
        }
    }

    return {
        name: item.name,
        label:<Label
            item={item}
        />,
        content: <MollieComponent
            item={item}
            useEffect={useEffect}
            jQuery={jQuery}
            requiredFields={requiredFields}
            isPhoneFieldVisible={isPhoneFieldVisible}/>,
        edit: <div>{item.edit}</div>,
        paymentMethodId: item.paymentMethodId,
        canMakePayment: () => {
            creditcardSelectedEvent();
            //only the methods that return is available on backend will be loaded here so we show them
            return true
        },
        ariaLabel: item.ariaLabel,
        supports: {
            features: item.supports,
        },
    };
}
export default molliePaymentMethod


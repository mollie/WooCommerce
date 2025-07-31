let cachedAvailableGateways = {};

function setAvailableGateways(country, currencyCode, data) {
    cachedAvailableGateways = {
        ...cachedAvailableGateways,
        ...data
    };
}
function useMollieAvailableGateways(billing, currencyCode, cartTotal, filters, ajaxUrl) {
    let country = billing.country;
    const code = currencyCode;
    const value = cartTotal;
    if (!country) {
        country = wcSettings?.baseLocation.country;
    }

    wp.element.useEffect(() => {
        if (!country) return;
        const currencyCode = code;
        const cartTotal = value;
        const currentFilterKey = currencyCode + "-" + country;
        if (cachedAvailableGateways.hasOwnProperty(currentFilterKey)) {
            return;
        }
        fetch(
            ajaxUrl,
            {
                method: 'POST',
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded",
                },
                body: new URLSearchParams({
                    action: 'mollie_checkout_blocks_canmakepayment',
                    currency: currencyCode,
                    billingCountry: country,
                    cartTotal,
                    paymentLocale: filters.paymentLocale
                }),
            }
        ).then(response => response.json()).then(data => {
            setAvailableGateways(country, currencyCode, data.data);
            const cartTotals = wp.data.select('wc/store/cart').getCartTotals();
            // Dispatch them again to trigger a re-render:
            wp.data.dispatch('wc/store/cart').setCartData({...cartTotals});
        });
    }, [billing, currencyCode, filters.paymentLocale]);

    return cachedAvailableGateways;
}

// Component that runs the hook but does not render anything.
function MollieGatewayUpdater({ billing, currencyCode, cartTotal, filters, ajaxUrl}) {

    useMollieAvailableGateways(billing, currencyCode, cartTotal, filters, ajaxUrl);
    return null;
}

let onSubmitLocal
let activePaymentMethodLocal
let creditCardSelected = new Event("mollie_creditcard_component_selected", {bubbles: true});
const MollieComponent = (props) => {
    let {onSubmit, activePaymentMethod, billing, item, useEffect, ajaxUrl, jQuery, emitResponse, eventRegistration, requiredFields, shippingData, isPhoneFieldVisible} = props
    const {  responseTypes } = emitResponse;
    const {onPaymentSetup, onCheckoutValidation} = eventRegistration;
    if (!item || !item.name) {
        return <div>Loading payment methods...</div>;
    }
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
            <input type={fieldType} name={id} id={id} value={value} onChange={action} placeholder={placeholder}></input>
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

const Label = ({ item, filters, ajaxUrl }) => {
    const cartData = wp.data.useSelect((select) =>
            select('wc/store/cart').getCartData(),
        []
    );
    const cartTotals = wp.data.useSelect( (select) => select('wc/store/cart').getCartTotals(), [ ] );
    const cartTotal = cartTotals?.total_price || 0;
    return (
        <>
            <div dangerouslySetInnerHTML={{ __html: item.label }}/>
            <MollieGatewayUpdater
                billing={cartData.billingAddress}
                currencyCode={wcSettings.currency.code}
                filters={filters}
                ajaxUrl={ajaxUrl}
                cartTotal={cartTotal}
            />
        </>
    );
};

const molliePaymentMethod = (useEffect, ajaxUrl, filters, gatewayData, availableGateways, item, jQuery, requiredFields, isPhoneFieldVisible) =>{

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
            ajaxUrl={ajaxUrl}
            filters={filters}
        />,
        content: <MollieComponent
            item={item}
            useEffect={useEffect}
            ajaxUrl={ajaxUrl}
            jQuery={jQuery}
            requiredFields={requiredFields}
            isPhoneFieldVisible={isPhoneFieldVisible}/>,
        edit: <div>{item.edit}</div>,
        paymentMethodId: item.paymentMethodId,
        canMakePayment: ({cartTotals, billingData}) => {
            if (!_.isEmpty(item.allowedCountries) && !(item.allowedCountries.includes(billingData.country))) {
                return false
            }
            if (cartTotals <= 0) {
                return true
            }
            const currencyCode = cartTotals?.currency_code;
            let country = billingData?.country;
            if (!country) {
                country = wcSettings?.baseLocation.country;
            }
            const currentFilterKey = currencyCode + "-" + country;

            creditcardSelectedEvent();

            if (!cachedAvailableGateways.hasOwnProperty(currentFilterKey)) {
                cachedAvailableGateways = {
                    ...cachedAvailableGateways,
                    ...availableGateways
                };
            }

            if (availableGateways.hasOwnProperty(currentFilterKey) && availableGateways[currentFilterKey].hasOwnProperty(item.name)) {
                return true;
            }

            if (cachedAvailableGateways.hasOwnProperty(currentFilterKey) && cachedAvailableGateways[currentFilterKey].hasOwnProperty(item.name)) {
                return true;
            }

            return false;
        },
        ariaLabel: item.ariaLabel,
        supports: {
            features: item.supports,
        },
    };
}
export default molliePaymentMethod


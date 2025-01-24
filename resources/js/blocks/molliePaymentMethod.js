let cachedAvailableGateways = {};

function loadCachedAvailableGateways() {
    const storedData = localStorage.getItem('cachedAvailableGateways');
    if (storedData) {
        try {
            cachedAvailableGateways = JSON.parse(storedData);
        } catch (e) {
            console.warn('Error parsing cachedAvailableGateways from localStorage:', e);
            cachedAvailableGateways = {};
        }
    }
}

function saveCachedAvailableGateways() {
    localStorage.setItem('cachedAvailableGateways', JSON.stringify(cachedAvailableGateways));
}

loadCachedAvailableGateways();
function setAvailableGateways(country, currencyCode, data) {
    cachedAvailableGateways = {
        ...cachedAvailableGateways,
        ...data
    };
    saveCachedAvailableGateways();
}
function useMollieAvailableGateways(billing, currencyCode, cartTotal, filters, ajaxUrl, jQuery, item) {
    const country = billing.country;
    const code = currencyCode;
    const value = cartTotal;


    wp.element.useEffect(() => {
        if (!country || !item) return;
        const currencyCode = code;
        const cartTotal = value;
        const currentFilterKey = currencyCode + "-" + country;
        if (cachedAvailableGateways.hasOwnProperty(currentFilterKey)) {
            return;
        }
        jQuery.ajax({
            url: ajaxUrl,
            method: 'POST',
            data: {
                action: 'mollie_checkout_blocks_canmakepayment',
                currentGateway: item,
                currency: currencyCode,
                billingCountry: country,
                cartTotal,
                paymentLocale: filters.paymentLocale
            },
            success: (response) => {
                setAvailableGateways(country, currencyCode, response.data);
                const cartTotals = wp.data.select('wc/store/cart').getCartTotals();
                // Dispatch them again to trigger a re-render:
                wp.data.dispatch('wc/store/cart').setCartData({...cartTotals});
            },
            error: (jqXHR, textStatus, errorThrown) => {
                console.warn('Failed to fetch available gateways:', textStatus, errorThrown);
            },
        });
    }, [billing, currencyCode, filters.paymentLocale, ajaxUrl, jQuery, item]);

    return cachedAvailableGateways;
}

// Component that runs the hook but does not render anything.
function MollieGatewayUpdater({ billing, currencyCode, cartTotal, filters, ajaxUrl, jQuery, item }) {

    useMollieAvailableGateways(billing, currencyCode, cartTotal, filters, ajaxUrl, jQuery, item);
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
    function updateTotalLabel(newTotal, currency) {
        let feeText = newTotal + " " + currency
        let totalSpan = "<span class='wc-block-formatted-money-amount wc-block-components-formatted-money-amount wc-block-components-totals-item__value'>" + feeText + "</span>"
        let total = jQuery('.wc-block-components-totals-footer-item .wc-block-formatted-money-amount:first')
        total.replaceWith(totalSpan)
    }
    function updateTaxesLabel(newTotal, currency) {
        let feeText = newTotal + " " + currency

        let totalSpan = "<span class='wc-block-formatted-money-amount wc-block-components-formatted-money-amount wc-block-components-totals-item__value'>" + feeText + "</span>"
        let total = jQuery('div.wp-block-woocommerce-checkout-order-summary-taxes-block.wc-block-components-totals-wrapper > div > span.wc-block-formatted-money-amount.wc-block-components-formatted-money-amount.wc-block-components-totals-item__value:first')
        total.replaceWith(totalSpan)
    }

    function hideFee(fee, response) {
        fee?.hide()
        updateTotalLabel(response.data.newTotal.toFixed(2).replace('.', ','), response.data.currency);
        updateTaxesLabel(response.data.totalTax.toFixed(2).replace('.', ','), response.data.currency);
    }

    function feeMarkup(response) {
        return "<div class='wc-block-components-totals-item wc-block-components-totals-fees'>" +
            "<span class='wc-block-components-totals-item__label'>"
            + response.data.name
            + "</span>" +
            "<span class='wc-block-formatted-money-amount wc-block-components-formatted-money-amount wc-block-components-totals-item__value'>"
            + response.data.amount.toFixed(2).replace('.', ',') + " " + response.data.currency
            + "</span>" +
            "<div class='wc-block-components-totals-item__description'>" +
            "</div>" +
            "</div>";
    }

    function replaceFee(fee, newFee, response) {
        fee.replaceWith(newFee)
        updateTotalLabel(response.data.newTotal.toFixed(2).replace('.', ','), response.data.currency);
        updateTaxesLabel(response.data.totalTax.toFixed(2).replace('.', ','), response.data.currency);
    }

    function insertNewFee(newFee, response) {
        const subtotal = jQuery('.wc-block-components-totals-item:first')
        subtotal.after(newFee)
        updateTotalLabel(response.data.newTotal.toFixed(2).replace('.', ','), response.data.currency);
        updateTaxesLabel(response.data.totalTax.toFixed(2).replace('.', ','), response.data.currency);
    }

    function handleFees(response) {
        const fee = jQuery('.wc-block-components-totals-fees')
        if (!response.data.amount) {
            hideFee(fee, response);
            return
        }

        let newFee = feeMarkup(response);
        if (fee.length) {
            replaceFee(fee, newFee, response);
            return
        }
        insertNewFee(newFee, response);
    }

    useEffect(() => {
        if(activePaymentMethodLocal !== activePaymentMethod && activePaymentMethod === 'mollie_wc_gateway_creditcard'){
            document.documentElement.dispatchEvent(creditCardSelected);
        }
        activePaymentMethodLocal = activePaymentMethod
        jQuery.ajax({
            url: ajaxUrl,
            method: 'POST',
            data: {
                action: 'mollie_checkout_blocks_surchage',
                method: activePaymentMethod
            },
            complete: (jqXHR, textStatus) => {
            },
            success: (response, textStatus, jqXHR) => {
                handleFees(response)
            },
            error: (jqXHR, textStatus, errorThrown) => {
                console.warn(textStatus, errorThrown)
            },
        })
    }, [activePaymentMethod, billing.cartTotal])

    useEffect(() => {
        const onProcessingPayment = () => {
            const tokenVal = jQuery('.mollie-components > input').val()
            return {
                type: responseTypes.SUCCESS,
                meta: {
                    paymentMethodData: {
                        payment_method: activePaymentMethod,
                        payment_method_title: item.title,
                        [issuerKey]: selectedIssuer,
                        billing_phone: inputPhone,
                        billing_company_billie: inputCompany,
                        billing_birthdate: inputBirthdate,
                        cardToken: tokenVal,
                    }
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

    function fieldMarkup(id, fieldType, label, action, value, placeholder = null) {
        const className = "wc-block-components-text-input wc-block-components-address-form__" + id;
        return <div class="custom-input">
            <label htmlFor={id} dangerouslySetInnerHTML={{__html: label}}></label>
            <input type={fieldType} name={id} id={id} value={value} onChange={action} placeholder={placeholder}></input>
        </div>
    }

    if (item.issuers && item.name !== "mollie_wc_gateway_creditcard"){
        return <div><p>{item.content}</p><select name={issuerKey} dangerouslySetInnerHTML={ {__html: item.issuers} } value={selectedIssuer} onChange={updateIssuer}></select></div>
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
                <div><p>{item.content}</p></div>
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
                <div><p>{item.content}</p></div>
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
                <div><p>{item.content}</p></div>
                {fieldMarkup("billing-birthdate", "date", birthdateField, updateBirthdate, inputBirthdate)}
                {!isPhoneFieldVisible && fieldMarkup("billing-phone-riverty", "tel", phoneLabel, updatePhone, inputPhone, phoneField)}
            </>
        );
    }

    return <div><p>{item.content}</p></div>
}

const Label = ({ item, filters, ajaxUrl, jQuery }) => {
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
                jQuery={jQuery}
                item={item}
                cartTotal={cartTotal}
            />
        </>
    );
};

const molliePaymentMethod = (useEffect, ajaxUrl, filters, gatewayData, availableGateways, item, jQuery, requiredFields, isCompanyFieldVisible, isPhoneFieldVisible) =>{

    document.addEventListener('mollie_components_ready_to_submit', function () {
        onSubmitLocal()
    })
    function creditcardSelectedEvent() {
        if (item.name === "mollie_wc_gateway_creditcard") {
            document.documentElement.dispatchEvent(creditCardSelected);
        }
    }

    // On first load, if availableGateways is not empty, store it
    if (_.isEmpty(cachedAvailableGateways) && !_.isEmpty(availableGateways)) {
        cachedAvailableGateways = availableGateways;
        saveCachedAvailableGateways();
    }
    return {
        name: item.name,
        label:<Label
            item={item}
            ajaxUrl={ajaxUrl}
            jQuery={jQuery}
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
            loadCachedAvailableGateways();
            const currencyCode = cartTotals?.currency_code;
            const country = billingData?.country;
            const currentFilterKey = currencyCode + "-" + country;

            creditcardSelectedEvent();
            if (!cachedAvailableGateways.hasOwnProperty(currentFilterKey)) {
                return false;
            }

            return cachedAvailableGateways[currentFilterKey].hasOwnProperty(item.name);
        },
        ariaLabel: item.ariaLabel,
        supports: {
            features: item.supports,
        },
    };
}

export default molliePaymentMethod


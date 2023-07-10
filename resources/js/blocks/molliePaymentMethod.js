
let onSubmitLocal
let activePaymentMethodLocal
let cachedAvailableGateways
let creditCardSelected = new Event("mollie_creditcard_component_selected", {bubbles: true});
const MollieComponent = (props) => {
    let {onSubmit, activePaymentMethod, billing, item, useEffect, ajaxUrl, jQuery, emitResponse, eventRegistration, requiredFields, shippingData, isCompanyFieldVisible, isPhoneFieldVisible} = props
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
    function updateTotalLabel(newTotal, currency) {
        let feeText = newTotal + " " + currency
        let totalSpan = "<span class='wc-block-formatted-money-amount wc-block-components-formatted-money-amount wc-block-components-totals-item__value'>" + feeText + "</span>"
        let total = jQuery('.wc-block-components-totals-footer-item .wc-block-formatted-money-amount:first')
        total.replaceWith(totalSpan)
    }

    function hideFee(fee, response) {
        fee?.hide()
        updateTotalLabel(response.data.newTotal, '');
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
    }

    function insertNewFee(newFee, response) {
        const subtotal = jQuery('.wc-block-components-totals-item:first')
        subtotal.after(newFee)
        updateTotalLabel(response.data.newTotal.toFixed(2).replace('.', ','), response.data.currency);
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
                        billing_company: inputCompany,
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
        if (companyLabel.length === 0) {
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
        let isBirthdateEmpty = inputBirthdate === ''
        const unsubscribeProcessing = onCheckoutValidation(

            () => {
                if (activePaymentMethod === 'mollie_wc_gateway_in3' && (isPhoneEmpty || isBirthdateEmpty)) {
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
    const updateIssuer = ( changeEvent ) => {
        selectIssuer( changeEvent.target.value )
    };
    const updateCompany = ( changeEvent ) => {
        selectCompany( changeEvent.target.value )
    };
    const updatePhone = ( changeEvent ) => {
        selectPhone( changeEvent.target.value )
    }
    const updateBirthdate = ( changeEvent ) => {
        selectBirthdate( changeEvent.target.value )
    }

    if (item.issuers && item.name !== "mollie_wc_gateway_creditcard"){
        return <div><p>{item.content}</p><select name={issuerKey} dangerouslySetInnerHTML={ {__html: item.issuers} } value={selectedIssuer} onChange={updateIssuer}></select></div>
    }

    if(item.name === "mollie_wc_gateway_creditcard"){
        return <div dangerouslySetInnerHTML={ {__html: item.content} }></div>;
    }

    function fieldMarkup(id, fieldType, label, action, value) {
        return <div><label htmlFor={id} dangerouslySetInnerHTML={{ __html: label }}></label><input type={fieldType} name={id} id={id} value={value} onChange={action}/></div>
    }

    if (item.name === "mollie_wc_gateway_billie"){
        if(isCompanyFieldVisible) {
           return;
        }
        const companyField = item.companyPlaceholder ? item.companyPlaceholder : "Company name";
        return fieldMarkup("billing-company","text", companyField, updateCompany, inputCompany);
    }

    if (item.name === "mollie_wc_gateway_in3"){
        let fields = [];
        const birthdateField = item.birthdatePlaceholder ? item.birthdatePlaceholder : "Birthdate";
        fields.push(fieldMarkup("billing-birthdate", "date", birthdateField, updateBirthdate, inputBirthdate));
        if (!isPhoneFieldVisible) {
            const phoneField = item.phonePlaceholder ? item.phonePlaceholder : "Phone";
            fields.push(fieldMarkup("billing-phone", "tel", phoneField, updatePhone, inputPhone));
        }

        return <>{fields}</>;
    }

    return
}


const molliePaymentMethod = (useEffect, ajaxUrl, filters, gatewayData, availableGateways, item, jQuery, requiredFields, isCompanyFieldVisible, isPhoneFieldVisible) =>{
    let billingCountry = filters.billingCountry
    let cartTotal = filters.cartTotal
    cachedAvailableGateways = availableGateways
    let changedBillingCountry = filters.billingCountry
    document.addEventListener('mollie_components_ready_to_submit', function () {
        onSubmitLocal()
    })
    return {
        name: item.name,
        label: <div dangerouslySetInnerHTML={{__html: item.label}}/>,
        content: <MollieComponent item={item} useEffect={useEffect} ajaxUrl={ajaxUrl} jQuery={jQuery} requiredFields={requiredFields} isCompayFieldVisible={isCompanyFieldVisible} isPhoneFieldVisible={isPhoneFieldVisible}/>,
        edit: <div>{item.edit}</div>,
        paymentMethodId: item.paymentMethodId,
        canMakePayment: ({cartTotals, billingData}) => {
            if (!_.isEmpty(item.allowedCountries) && !(item.allowedCountries.includes(billingData.country))) {
                return false
            }
            if (cartTotals <= 0) {
                return true
            }

            cartTotal = cartTotals?.total_price
            if(billingData?.country && billingData.country !== ''){
                billingCountry = billingData?.country
            }
            let currencyCode = cartTotals?.currency_code
            let currentFilterKey = currencyCode + "-" + filters.paymentLocale + "-" + billingCountry

            function creditcardSelectedEvent() {
                if (item.name === "mollie_wc_gateway_creditcard") {
                    document.documentElement.dispatchEvent(creditCardSelected);
                }
            }

            if (billingCountry !== changedBillingCountry) {
                changedBillingCountry = billingCountry
                if (!cachedAvailableGateways.hasOwnProperty(currentFilterKey)) {
                    jQuery.ajax({
                        url: ajaxUrl,
                        method: 'POST',
                        data: {
                            action: 'mollie_checkout_blocks_canmakepayment',
                            currentGateway: item,
                            currency: currencyCode,
                            billingCountry: billingCountry,
                            cartTotal: cartTotal,
                            paymentLocale: filters.paymentLocale
                        },
                        complete: (jqXHR, textStatus) => {
                        },
                        success: (response, textStatus, jqXHR) => {
                            cachedAvailableGateways = {...cachedAvailableGateways, ...response.data}
                            if (!cachedAvailableGateways.hasOwnProperty(currentFilterKey)) {
                                return false
                            }
                            return cachedAvailableGateways[currentFilterKey].hasOwnProperty(item.name)
                        },
                        error: (jqXHR, textStatus, errorThrown) => {
                            console.warn(textStatus, errorThrown)
                        },
                    })
                }
            }

            if (!cachedAvailableGateways.hasOwnProperty(currentFilterKey)) {
                return false
            }
            creditcardSelectedEvent();

            return cachedAvailableGateways[currentFilterKey].hasOwnProperty(item.name)
        },
        ariaLabel: item.ariaLabel,
        supports: {
            features: item.supports,
        },
    };
}

export default molliePaymentMethod


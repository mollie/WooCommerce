
let onSubmitLocal
let activePaymentMethodLocal
let cachedAvailableGateways
let creditCardSelected = new Event("mollie_creditcard_component_selected", {bubbles: true});

const MollieComponent = (props) => {

    const {onSubmit, activePaymentMethod, item, useEffect, ajaxUrl, jQuery, emitResponse, eventRegistration} = props
    const {  responseTypes } = emitResponse;
    const {onPaymentProcessing} = eventRegistration;
    const [ selectedIssuer, selectIssuer ] = wp.element.useState('');
    const issuerKey = 'mollie-payments-for-woocommerce_issuer_' + activePaymentMethod

    function updateTotalLabel(newTotal, currency) {
        let feeText = newTotal + " " + currency
        let totalSpan = "<span class='wc-block-formatted-money-amount wc-block-components-formatted-money-amount wc-block-components-totals-item__value'>" + feeText + "</span>"
        let total = jQuery('.wc-block-components-totals-footer-item .wc-block-formatted-money-amount:first')
        total.replaceWith(totalSpan)
    }

    useEffect(() => {
        if(activePaymentMethodLocal !== activePaymentMethod && activePaymentMethod === 'mollie_wc_gateway_creditcard'){
            document.documentElement.dispatchEvent(creditCardSelected);
        }
        activePaymentMethodLocal = activePaymentMethod
        let isAppliedFee = props.billing.cartTotalItems[1]?.value > 0
        const updateSurcharge = (isAppliedFee || item.hasSurcharge)
        if (updateSurcharge) {

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
                    const fee = jQuery('.wc-block-components-totals-fees')
                    if (!response.data.amount) {
                        fee?.hide()
                        updateTotalLabel(response.data.newTotal, '');
                        return
                    }
                    //change fee
                    let newFee = "<div class='wc-block-components-totals-item wc-block-components-totals-fees'>" +
                        "<span class='wc-block-components-totals-item__label'>"
                        + response.data.name
                        + "</span>" +
                        "<span class='wc-block-formatted-money-amount wc-block-components-formatted-money-amount wc-block-components-totals-item__value'>"
                        + response.data.amount.toFixed(2).replace('.', ',') + " " + response.data.currency
                        + "</span>" +
                        "<div class='wc-block-components-totals-item__description'>" +
                        "</div>" +
                        "</div>"
                    if (fee.length) {
                        fee.replaceWith(newFee)
                        updateTotalLabel(response.data.newTotal.toFixed(2).replace('.', ','), response.data.currency);
                        return
                    }
                    //add fee as new
                    const subtotal = jQuery('.wc-block-components-totals-item:first')
                    subtotal.after(newFee)
                    updateTotalLabel(response.data.newTotal.toFixed(2).replace('.', ','), response.data.currency);
                },
                error: (jqXHR, textStatus, errorThrown) => {
                    console.warn(textStatus, errorThrown)
                },
            })
        }


    }, [activePaymentMethod])

    useEffect(() => {
        const onProcessingPayment = () => {
            return {
                type: responseTypes.SUCCESS,
                meta: {
                    paymentMethodData: {
                        payment_method: activePaymentMethod,
                        [issuerKey]: selectedIssuer
                    }
                },
            };
        }

        const unsubscribePaymentProcessing = onPaymentProcessing(
            onProcessingPayment
        );
        return () => {
            unsubscribePaymentProcessing()
        };

    }, [selectedIssuer, onPaymentProcessing])
    onSubmitLocal = onSubmit

    const updateIssuer = ( changeEvent ) => {
        selectIssuer( changeEvent.target.value )
    };

    if (item.issuers){
        return <div><p>{item.content}</p><select name={issuerKey} dangerouslySetInnerHTML={ {__html: item.issuers} } value={selectedIssuer} onChange={updateIssuer}></select></div>
    }

    return <div dangerouslySetInnerHTML={ {__html: item.content} }/>

}


const molliePaymentMethod = (useEffect, ajaxUrl, filters, gatewayData, availableGateways, item, jQuery) =>{
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
        content: <MollieComponent item={item} useEffect={useEffect} ajaxUrl={ajaxUrl} jQuery={jQuery}/>,
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
            billingCountry = billingData?.country
            let currencyCode = cartTotals?.currency_code
            let currentFilterKey = currencyCode + "-" + filters.paymentLocale + "-" + billingCountry

            function creditcardSelectedEvent() {
                if (item.name === "mollie_wc_gateway_creditcard") {
                    document.documentElement.dispatchEvent(creditCardSelected);
                }
            }

            if (billingCountry !== changedBillingCountry) {
                changedBillingCountry = billingCountry
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


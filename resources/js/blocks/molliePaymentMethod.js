let onSubmitLocal
let activePaymentMethodLocal
let creditCardSelected = new Event("mollie_creditcard_component_selected", {bubbles: true});

const MollieComponent = (props) => {

    const {onSubmit, activePaymentMethod, item, useEffect, emitResponse, eventRegistration, isProcessing} = props
    const {  responseTypes } = emitResponse;
    const {onPaymentProcessing} = eventRegistration;
    const [ selectedIssuer, selectIssuer ] = wp.element.useState('test');

    const issuerKey = 'mollie-payments-for-woocommerce_issuer_' + activePaymentMethod

    useEffect(() => {
        if(activePaymentMethodLocal !== activePaymentMethod && activePaymentMethod === 'mollie_wc_gateway_creditcard'){
            document.documentElement.dispatchEvent(creditCardSelected);
        }
        activePaymentMethodLocal = activePaymentMethod
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
    if (item.content.startsWith('<option')){
        return <select name={issuerKey} dangerouslySetInnerHTML={ {__html: item.content} } value={selectedIssuer} onChange={updateIssuer}></select>
    }

    return <div dangerouslySetInnerHTML={ {__html: item.content} }/>

}

function updateAvailableGateways(item, currencyCode, filters, ajaxUrl, cachedAvailableGateways, billingCountry, cartTotal) {
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
        },
        error: (jqXHR, textStatus, errorThrown) => {
            console.warn(textStatus, errorThrown)
        },
    })
}

const molliePaymentMethod = (useEffect, ajaxUrl, filters, gatewayData, availableGateways, item, jQuery) =>{
    let billingCountry = filters.billingCountry
    let cartTotal = filters.cartTotal
    let cachedAvailableGateways = availableGateways
    let changedBillingCountry = filters.billingCountry

    document.addEventListener('mollie_components_ready_to_submit', function () {
        onSubmitLocal()
    })
    return {
        name: item.name,
        label: <div dangerouslySetInnerHTML={{__html: item.label}}/>,
        content: <MollieComponent item={item} useEffect={useEffect}/>,
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
            if (billingData?.country !== changedBillingCountry) {
                changedBillingCountry = billingData.country
                updateAvailableGateways(item, currencyCode, filters, ajaxUrl, cachedAvailableGateways, billingCountry, cartTotal);
            }


            let currentFilterKey = currencyCode + "-" + filters.paymentLocale + "-" + billingCountry

            if (cachedAvailableGateways.hasOwnProperty(currentFilterKey)) {
                if (item.name === "mollie_wc_gateway_creditcard") {
                    document.documentElement.dispatchEvent(creditCardSelected);
                }

                return cachedAvailableGateways[currentFilterKey].hasOwnProperty(item.name)
            }

            return false
        },
        ariaLabel: item.ariaLabel,
        supports: {
            features: item.supports,
        },
    };
}

export default molliePaymentMethod


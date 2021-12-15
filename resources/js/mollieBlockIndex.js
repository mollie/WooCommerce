(
    function ({ mollieBlockData, wc, _, jQuery}) {
        if (_.isEmpty(mollieBlockData)) {
            return
        }
        const { registerPaymentMethod } = wc.wcBlocksRegistry;
        const { ajaxUrl, filters, gatewayData, availableGateways } = mollieBlockData.gatewayData;

        let billingCountry = filters.billingCountry
        let cartTotal = filters.cartTotal
        let cachedAvailableGateways = availableGateways
        let changedBillingCountry = filters.billingCountry
        let onSubmitLocal
        let activePaymentMethodLocal
        let creditCardSelected = new Event("mollie_creditcard_component_selected", {bubbles: true});

        const Content = (props) => {
            const {onSubmit, activePaymentMethod, item} = props
            onSubmitLocal = onSubmit
            if(activePaymentMethodLocal !== activePaymentMethod && activePaymentMethod === 'mollie_wc_gateway_creditcard'){
                document.documentElement.dispatchEvent(creditCardSelected);
            }
            activePaymentMethodLocal = activePaymentMethod
            return <div dangerouslySetInnerHTML={ {__html: item.content} }/>
        }

        function updateAvailableGateways(item) {
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

        gatewayData.forEach(item=>{
            let paymentMethod = {
                name: item.name,
                label: <div dangerouslySetInnerHTML={ {__html: item.label} }/>,
                content: <Content item={item}/>,
                edit:  <div>{item.edit}</div>,
                paymentMethodId: item.paymentMethodId,
                canMakePayment: ( { cartTotals, billingData } ) => {
                    if(!_.isEmpty(item.allowedCountries) && !(item.allowedCountries.includes(billingData.country))){
                        return false
                    }
                    if(cartTotals <= 0) {
                        return true
                    }

                    cartTotal = cartTotals?.total_price
                    billingCountry = billingData?.country
                    if(billingData?.country !== changedBillingCountry){
                        changedBillingCountry = billingData.country
                        updateAvailableGateways(item);
                    }
                    currencyCode = cartTotals?.currency_code

                    let currentFilterKey = currencyCode+"-"+filters.paymentLocale+"-"+billingCountry

                    if (cachedAvailableGateways.hasOwnProperty(currentFilterKey)) {
                        if(item.name === "mollie_wc_gateway_creditcard"){
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

            registerPaymentMethod( paymentMethod );
        })

        document.addEventListener('mollie_components_ready_to_submit', function () {
            onSubmitLocal()
        })
    }
)
(
    window, wc
)

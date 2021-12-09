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
                content: <div dangerouslySetInnerHTML={ {__html: item.content} }/>,
                edit:  <div>{item.edit}</div>,
                paymentMethodId: item.paymentMethodId,
                canMakePayment: ( { cartTotals, billingData } ) => {
                    if(item.allowedCountries !== "" && !(item.allowedCountries.includes(billingData.country))){
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
                            let updated = new Event("mollie_creditcard_component_selected", {bubbles: true});
                            document.documentElement.dispatchEvent(updated);
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
    }
)
(
    window, wc
)

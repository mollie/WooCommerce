import molliePaymentMethod from './blocks/molliePaymentMethod'

(
    function ({ mollieBlockData, wc, _, jQuery}) {
        if (_.isEmpty(mollieBlockData)) {
            return
        }
        const { registerPaymentMethod } = wc.wcBlocksRegistry;
        const { ajaxUrl, filters, gatewayData, availableGateways } = mollieBlockData.gatewayData;
        const {useEffect} = wp.element;
        const isAppleSession = typeof window.ApplePaySession === "function"

        gatewayData.forEach(item=>{
            if(item.name !== 'mollie_wc_gateway_applepay'){
                registerPaymentMethod(molliePaymentMethod(useEffect, ajaxUrl, filters, gatewayData, availableGateways, item, jQuery))
            }
            if(item.name === 'mollie_wc_gateway_applepay' &&  isAppleSession && window.ApplePaySession.canMakePayments()){
                registerPaymentMethod(molliePaymentMethod(useEffect, ajaxUrl, filters, gatewayData, availableGateways, item, jQuery))
            }
        })
    }
)
(
    window, wc
)

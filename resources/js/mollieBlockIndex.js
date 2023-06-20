import molliePaymentMethod from './blocks/molliePaymentMethod'

(
    function ({ mollieBlockData, wc, _, jQuery}) {
        if (_.isEmpty(mollieBlockData)) {
            return;
        }
        window.onload = (event) => {
            const { registerPaymentMethod } = wc.wcBlocksRegistry;
            const { ajaxUrl, filters, gatewayData, availableGateways } = mollieBlockData.gatewayData;
            const {useEffect} = wp.element;
            const isAppleSession = typeof window.ApplePaySession === "function"

            gatewayData.forEach(item => {
                let register = () => registerPaymentMethod(molliePaymentMethod(useEffect, ajaxUrl, filters, gatewayData, availableGateways, item, jQuery));
                if (item.name === 'mollie_wc_gateway_applepay' ) {
                    if (isAppleSession && window.ApplePaySession.canMakePayments()) {
                        register();
                    }
                    return;
                }
                register();
            });
        };

    }
)(window, wc)

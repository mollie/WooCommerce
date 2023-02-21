import molliePaymentMethod from './blocks/molliePaymentMethod'

(
    function ({ mollieBlockData, wc, _, jQuery}) {
        if (_.isEmpty(mollieBlockData)) {
            return;
        }
        const { registerPaymentMethod } = wc.wcBlocksRegistry;
        const { ajaxUrl, filters, gatewayData, availableGateways } = mollieBlockData.gatewayData;
        const {useEffect} = wp.element;
        const isAppleSession = typeof window.ApplePaySession === "function"
        let companyLabel = jQuery('div.wc-block-components-text-input.wc-block-components-address-form__company > label');
        let companyNameString = companyLabel.text();
        let isCompanyFieldVisible = companyNameString.length > 0;
        gatewayData.forEach(item => {
            let register = () => registerPaymentMethod(molliePaymentMethod(useEffect, ajaxUrl, filters, gatewayData, availableGateways, item, jQuery, companyNameString));
            if (item.name === 'mollie_wc_gateway_billie') {
                if (isCompanyFieldVisible) {
                    register();
                }
                return;
            }
            if (item.name === 'mollie_wc_gateway_applepay' ) {
                if (isAppleSession && window.ApplePaySession.canMakePayments()) {
                    register();
                }
                return;
            }
            register();
        });
    }
)(window, wc)

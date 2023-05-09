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

            function getCompanyField()
            {
                let shippingCompany = document.getElementById('shipping-company');
                let billingCompany = document.getElementById('billing-company');
                return shippingCompany ? shippingCompany : billingCompany;
            }

            function isFieldVisible(companyField)
            {
                return companyField && companyField.style.display !== 'none';
            }

            let companyField = getCompanyField();
            let companyNameString = companyField && companyField.parentNode.querySelector("label[for='" + companyField.id + "']").innerHTML;
            gatewayData.forEach(item => {
                let register = () => registerPaymentMethod(molliePaymentMethod(useEffect, ajaxUrl, filters, gatewayData, availableGateways, item, jQuery, companyNameString));
                if (item.name === 'mollie_wc_gateway_billie') {
                    if (isFieldVisible(companyField)) {
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
        };

    }
)(window, wc)

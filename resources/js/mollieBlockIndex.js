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

            function getPhoneField()
            {
                const shippingPhone = document.getElementById('shipping-phone');
                const billingPhone = document.getElementById('billing-phone');
                return billingPhone || shippingPhone;
            }
            function isFieldVisible(field)
            {
                return field && field.style.display !== 'none';
            }
            let companyField = getCompanyField();
            const isCompanyFieldVisible = companyField && isFieldVisible(companyField);
            const companyNameString = companyField && companyField.parentNode.querySelector('label') ? companyField.parentNode.querySelector('label').innerHTML : false;
            let phoneField = getPhoneField();
            const isPhoneFieldVisible = phoneField && isFieldVisible(phoneField);
            const phoneString = phoneField && phoneField.parentNode.querySelector('label') ? phoneField.parentNode.querySelector('label').innerHTML : false;
            let requiredFields = {
                'companyNameString': companyNameString,
                'phoneString': phoneString,
            }
            gatewayData.forEach(item => {
                let register = () => registerPaymentMethod(molliePaymentMethod(useEffect, ajaxUrl, filters, gatewayData, availableGateways, item, jQuery, requiredFields, isCompanyFieldVisible, isPhoneFieldVisible));
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

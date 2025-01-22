import molliePaymentMethod from './blocks/molliePaymentMethod'
import ApplePayButtonComponent from './blocks/ApplePayButtonComponent'
import ApplePayButtonEditorComponent from './blocks/ApplePayButtonEditorComponent'

(
    function ({mollieBlockData, wc, _, jQuery}) {
        if (_.isEmpty(mollieBlockData)) {
            return;
        }

        const {registerPaymentMethod} = wc.wcBlocksRegistry;
        const {defaultFields} = wc.wcSettings.allSettings;
        const {ajaxUrl, filters, gatewayData, availableGateways} = mollieBlockData.gatewayData;
        const {useEffect} = wp.element;
        const isAppleSession = typeof window.ApplePaySession === "function"
        localStorage.removeItem('cachedAvailableGateways');

        function getPhoneField() {
            const phoneFieldDataset = document.querySelector('[data-show-phone-field]');
            if (!phoneFieldDataset) {
                return true;
            }
            return phoneFieldDataset.dataset.showPhoneField !== "false"
        }

        const companyNameString = defaultFields.company.label
        const isPhoneFieldVisible = getPhoneField();
        const phoneString = defaultFields.phone.label
        let requiredFields = {
            'companyNameString': companyNameString,
            'phoneString': phoneString,
        }
        gatewayData.forEach(item => {
            let register = () => registerPaymentMethod(molliePaymentMethod(useEffect, ajaxUrl, filters, gatewayData, availableGateways, item, jQuery, requiredFields, isPhoneFieldVisible));
            if (item.name === 'mollie_wc_gateway_applepay') {
                const {isExpressEnabled} = item;
                if ((isAppleSession && window.ApplePaySession.canMakePayments())) {
                    register();
                    if (isExpressEnabled !== true) {
                        return;
                    }
                    const {registerExpressPaymentMethod} = wc.wcBlocksRegistry;
                    registerExpressPaymentMethod({
                        name: 'mollie_wc_gateway_applepay_express',
                        title: 'Apple Pay Express button',
                        description: 'Apple Pay Express button',
                        content: <ApplePayButtonComponent/>,
                        edit: <ApplePayButtonEditorComponent/>,
                        ariaLabel: 'Apple Pay',
                        canMakePayment: () => true,
                        paymentMethodId: 'mollie_wc_gateway_applepay',
                        gatewayId: 'mollie_wc_gateway_applepay',
                        supports: {
                            features: ['products'],
                            style: ['height', 'borderRadius']
                        },
                    })
                }
                return;
            }
            register();
        });
    }
)(window, wc)

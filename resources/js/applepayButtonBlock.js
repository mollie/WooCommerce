import {ApplePayButtonComponent} from "./blocks/ApplePayButtonComponent";
import {ApplePayButtonEditorComponent} from "./blocks/ApplePayButtonEditorComponent";

(
    function ({mollieApplePayBlockDataCart}) {
        if (mollieApplePayBlockDataCart.length === 0) {
            return
        }
        const {ApplePaySession} = window;
        if (!(ApplePaySession && ApplePaySession.canMakePayments())) {
            return null;
        }

        const {registerExpressPaymentMethod} = wc.wcBlocksRegistry;

        registerExpressPaymentMethod({
            name: 'mollie_wc_gateway_applepay_express',
            content: < ApplePayButtonComponent/>,
            edit: < ApplePayButtonEditorComponent/>,
            ariaLabel: 'Apple Pay',
            canMakePayment: () => true,
            paymentMethodId: 'mollie_wc_gateway_applepay',
            supports: {
                features: ['products'],
            },
        });
    }
)
(
    window
)

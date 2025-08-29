import {MollieComponent} from "../src/components/mollieContainer/MollieComponent";
import {Label} from "../src/components/mollieContainer/Label";
let creditCardSelected = new Event("mollie_creditcard_component_selected", {bubbles: true});

const molliePaymentMethod = (item, jQuery, requiredFields, isPhoneFieldVisible) =>{

    if (item.name === "mollie_wc_gateway_creditcard") {
        document.addEventListener('mollie_components_ready_to_submit', function () {
            onSubmitLocal()
        })
    }
    function creditcardSelectedEvent() {
        if (item.name === "mollie_wc_gateway_creditcard") {
            document.documentElement.dispatchEvent(creditCardSelected);
        }
    }

    return {
        name: item.name,
        label:<Label
            item={item}
        />,
        content: <MollieComponent
            item={item}
            jQuery={jQuery}
            requiredFields={requiredFields}
            isPhoneFieldVisible={isPhoneFieldVisible}/>,
        edit: <div>{item.edit}</div>,
        paymentMethodId: item.paymentMethodId,
        canMakePayment: () => {
            creditcardSelectedEvent();
            //only the methods that return is available on backend will be loaded here so we show them
            return true
        },
        ariaLabel: item.ariaLabel,
        supports: {
            features: item.supports,
        },
    };
}
export default molliePaymentMethod


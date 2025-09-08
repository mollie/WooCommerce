import {PaymentMethodContentRenderer} from "../src/components/PaymentMethodContentRenderer";
import {Label} from "../src/components/Label";

const molliePaymentMethod = (item, jQuery, requiredFields, isPhoneFieldVisible) =>{

    return {
        name: item.name,
        label:<Label
            item={item}
        />,
        content: <PaymentMethodContentRenderer
            item={item}
            jQuery={jQuery}
            requiredFields={requiredFields}
            isPhoneFieldVisible={isPhoneFieldVisible}/>,
        edit: <div>{item.edit}</div>,
        paymentMethodId: item.paymentMethodId,
        canMakePayment: () => {
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


import { addFilter } from '@wordpress/hooks';
import { PaymentMethodContentRenderer } from '../components/PaymentMethodContentRenderer';
import {ApplePayUtils} from "../../../shared/utils/applePayUtils";
import ApplePayButtonComponent from "../components/expressPayments/ApplePayButtonComponent";
import ApplePayButtonEditorComponent from "../components/expressPayments/ApplePayButtonEditorComponent";
import {isEditorContext} from "../../../shared/utils/paymentUtils";

export const registerGatewayRegistrationHooks = (gatewayData) => {
    const applePayGateway = gatewayData.find((gateway) => {
		return gateway.name === ApplePayUtils.GATEWAY_NAME
	});

    if (applePayGateway) {
        addFilter(
            `${applePayGateway.name}_should_register_payment_method`,
            'mollie/apple-pay-conditional-registration',
            (shouldRegister, PaymentMethodArgs, settings) => {
                if (isEditorContext()) {
                    return true;
                }
                return ApplePayUtils.canRegisterApplePay();
            }
        );
    }
}

export const registerExpressPaymentMethodHooks = (gatewayData) => {
    const applePayGateway = gatewayData.find((gateway) => {
        return gateway.name === ApplePayUtils.GATEWAY_NAME
    });

    if (applePayGateway && applePayGateway.isExpressEnabled) {

        addFilter(
            `${applePayGateway.name}_express_payment_method_args`,
            'mollie/apple-pay-express-args',
            (PaymentMethodArgs, settings) => {
                const isAppleSession = ApplePayUtils.isAppleSessionAvailable();

                return {
                    name: 'mollie_wc_gateway_applepay_express',
                    title: 'Apple Pay Express button',
                    description: 'Apple Pay Express button',
                    content: <ApplePayButtonComponent/>,
                    edit: <ApplePayButtonEditorComponent/>,
                    ariaLabel: 'Apple Pay',
                    canMakePayment: () => {
                        if (isEditorContext()) {
                            return true;
                        }
                        return isAppleSession && window.ApplePaySession.canMakePayments();
                    },
                    paymentMethodId: 'mollie_wc_gateway_applepay',
                    gatewayId: 'mollie_wc_gateway_applepay',
                    supports: {
                        features: ['products'],
                        style: ['height', 'borderRadius']
                    },
                };
            }
        );

        addFilter(
            `${applePayGateway.name}_express_payment_methods`,
            'mollie/apple-pay-express-registration',
            (shouldRegister, PaymentMethodArgs, settings) => {
                if (isEditorContext()) {
                    return true;
                }
                return ApplePayUtils.canRegisterApplePay();
            }
        );
    }
};
export const registerAllContentHooks = (gatewayData, context) => {
	if (typeof gatewayData !== 'undefined' && gatewayData.length > 0) {
        gatewayData.forEach((gateway) => {
			const checkoutFieldsHookName = `${gateway.name}_checkout_fields`;
			let item = gatewayData.find((item) => item.name === gateway.name);
			addFilter(
				checkoutFieldsHookName,
				'mollie/register-payment-content-renderer',
				(components) => {
					const MollieComponent = (props) => {
						const mappedProps = {
							props,
							item,
							requiredFields : context.requiredFields,
							shouldHidePhoneField: context.shouldHidePhoneField
						};

						return <PaymentMethodContentRenderer {...mappedProps} />;
					};

					return [...components, MollieComponent];
				}
			);
		});
	}
};

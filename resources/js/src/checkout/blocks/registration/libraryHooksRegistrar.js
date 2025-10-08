import { addFilter } from '@wordpress/hooks';
import { PaymentMethodContentRenderer } from '../components/PaymentMethodContentRenderer';
import {ApplePayUtils} from "../../../shared/utils/applePayUtils";

export const registerGatewayRegistrationHooks = (gatewayData) => {
    const applePayGateway = gatewayData.find((gateway) => {
		return gateway.name === ApplePayUtils.GATEWAY_NAME
	});

    if (applePayGateway) {
        addFilter(
            `${applePayGateway.name}_should_register_payment_method`,
            'mollie/apple-pay-conditional-registration',
            (shouldRegister, PaymentMethodArgs, settings) => {
				console.log('applePayGateway', ApplePayUtils.canRegisterApplePay())
                return ApplePayUtils.canRegisterApplePay();
            }
        );
    }
}
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

import { PaymentMethodContentRenderer } from './PaymentMethodContentRenderer';
import { Label } from './Label';

const molliePaymentMethod = (
	item,
	jQuery,
	requiredFields,
	shouldHidePhoneField
) => {
	return {
		name: item.name,
		label: <Label item={ item } />,
		content: (
			<PaymentMethodContentRenderer
				item={ item }
				jQuery={ jQuery }
				requiredFields={ requiredFields }
				shouldHidePhoneField={ shouldHidePhoneField }
			/>
		),
		edit: <div>{ item.edit }</div>,
		paymentMethodId: item.paymentMethodId,
		canMakePayment: () => {
			//only the methods that return is available on backend will be loaded here so we show them
			return true;
		},
		ariaLabel: item.ariaLabel,
		supports: {
			features: item.supports,
		},
	};
};
export default molliePaymentMethod;

import CreditCardComponent from './paymentMethods/CreditCardComponent';
import DefaultComponent from './paymentMethods/DefaultComponent';
import PaymentFieldsComponent from './paymentMethods/PaymentFieldsComponent';
import withMollieStore from '../hoc/withMollieStore';

/**
 * Factory function to create appropriate payment component with store connection
 * Maps payment method names to their corresponding components with proper configuration
 * @param item
 * @param commonProps
 */
export const createPaymentComponent = ( item, commonProps ) => {
	if ( ! item || ! item.name ) {
		return <div>Loading payment methods...</div>;
	}

	switch ( item.name ) {
		case 'mollie_wc_gateway_creditcard':
			const CreditCardWithStore = withMollieStore( CreditCardComponent );
			return <CreditCardWithStore { ...commonProps } />;

		case 'mollie_wc_gateway_billie':
			const BillieFieldsWithStore = withMollieStore(
				PaymentFieldsComponent
			);
			return (
				<BillieFieldsWithStore
					{ ...commonProps }
					fieldConfig={ {
						showCompany: true,
						companyRequired: true,
						companyLabel: item.companyPlaceholder || 'Company name',
					} }
				/>
			);

		case 'mollie_wc_gateway_in3':
			const In3FieldsWithStore = withMollieStore(
				PaymentFieldsComponent
			);
			return (
				<In3FieldsWithStore
					{ ...commonProps }
					fieldConfig={ {
						showPhone: true,
						showBirthdate: true,
						phoneRequired: true,
						birthdateRequired: true,
						phoneLabel: item.phoneLabel || 'Phone',
						birthdateLabel:
							item.birthdatePlaceholder || 'Birthdate',
					} }
				/>
			);

		case 'mollie_wc_gateway_riverty':
			const RivertyFieldsWithStore = withMollieStore(
				PaymentFieldsComponent
			);
			return (
				<RivertyFieldsWithStore
					{ ...commonProps }
					fieldConfig={ {
						showPhone: true,
						showBirthdate: true,
						phoneRequired: true,
						birthdateRequired: true,
						phoneLabel: item.phoneLabel || 'Phone',
						birthdateLabel:
							item.birthdatePlaceholder || 'Birthdate',
					} }
				/>
			);

		default:
			const DefaultWithStore = withMollieStore( DefaultComponent );
			return <DefaultWithStore { ...commonProps } />;
	}
};

import CreditCardComponent from './paymentMethods/CreditCardComponent';
import DefaultComponent from './paymentMethods/DefaultComponent';
import PaymentFieldsComponent from './paymentMethods/PaymentFieldsComponent';
import withMollieStore from '../hoc/withMollieStore';

/**
 * Factory function to create appropriate payment component with store connection
 * Maps payment method names to their corresponding components with proper configuration
 * @param {Object} item
 * @param {Object} commonProps
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
						hasCustomCompanyField: true,
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
						hasCustomPhoneField: true,
						hasCustomBirthdateField: true,
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
						hasCustomPhoneField: true,
						hasCustomBirthdateField: true,
						phoneLabel: item.phoneLabel || 'Phone',
						birthdateLabel:
							item.birthdatePlaceholder || 'Birthdate',
					} }
				/>
			);

        case 'mollie_wc_gateway_bizum':
            const BizumFieldsWithStore = withMollieStore(
                PaymentFieldsComponent
            );
            return (
                <BizumFieldsWithStore
                    { ...commonProps }
                    fieldConfig={ {
                        hasCustomPhoneField: true,
                        hasCustomBirthdateField: false,
                        phoneLabel: item.phoneLabel || 'Phone',
                    } }
                />
            );
        case 'mollie_wc_gateway_vipps':
            const VippsFieldsWithStore = withMollieStore(
                PaymentFieldsComponent
            );
            return (
                <VippsFieldsWithStore
                    { ...commonProps }
                    fieldConfig={ {
                        hasCustomPhoneField: true,
                        hasCustomBirthdateField: false,
                        phoneLabel: item.phoneLabel || 'Phone',
                    } }
                />
            );
        case 'mollie_wc_gateway_mobilepay':
            const MobilepayFieldsWithStore = withMollieStore(
                PaymentFieldsComponent
            );
            return (
                <MobilepayFieldsWithStore
                    { ...commonProps }
                    fieldConfig={ {
                        hasCustomPhoneField: true,
                        hasCustomBirthdateField: false,
                        phoneLabel: item.phoneLabel || 'Phone',
                    } }
                />
            );

		default:
			const DefaultWithStore = withMollieStore( DefaultComponent );
			return <DefaultWithStore { ...commonProps } />;
	}
};

export const CompanyField = ( { label, value, onChange } ) => {
	const handleChange = ( e ) => onChange( e.target.value );
	const className =
		'wc-block-components-text-input wc-block-components-address-form__billing_company_billie';

	return (
		<div className="custom-input">
			<label htmlFor="billing_company_billie">{ label }</label>
			<input
				type="text"
				className={ className }
				name="billing_company_billie"
				id="billing_company_billie"
				value={ value }
				onChange={ handleChange }
			/>
		</div>
	);
};

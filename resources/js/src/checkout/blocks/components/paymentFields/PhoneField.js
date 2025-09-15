export const PhoneField = ( { id, label, value, onChange, placeholder } ) => {
	const handleChange = ( e ) => onChange( e.target.value );
	const className = `wc-block-components-address-form__${ id }`;

	return (
		<div className="custom-input wc-block-components-text-input wc-block-components-address-form__phone is-active">
			<label htmlFor={ id }>{ label }</label>
			<input
				type="tel"
				className={ className }
				name={ id }
				id={ id }
				value={ value }
				onChange={ handleChange }
				placeholder={ placeholder }
			/>
		</div>
	);
};

export const PhoneField = ( { id, label, value, onChange, placeholder } ) => {
	const handleChange = ( e ) => onChange( e.target.value );
	const className = `wc-block-components-text-input wc-block-components-address-form__${ id }`;

	return (
		<div className="custom-input">
			<label
				htmlFor={ id }
				dangerouslySetInnerHTML={ { __html: label } }
			></label>
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

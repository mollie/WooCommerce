import { useCallback } from '@wordpress/element';

export const PhoneField = ( { id, label, value, onChange, placeholder } ) => {
	const handleChange = useCallback( ( e ) => {
		onChange( e.target.value );
	}, [ onChange ] );
	const className = ` wc-block-components-address-form__${ id }`;

	return (
		<div className="wc-block-components-text-input wc-block-components-address-form__phone">
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

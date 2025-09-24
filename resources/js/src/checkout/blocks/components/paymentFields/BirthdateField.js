import { useCallback } from '@wordpress/element';

export const BirthdateField = ( { label, value, onChange } ) => {
    const handleChange = useCallback( ( e ) => {
        onChange( e.target.value );
    }, [ onChange ] );
	const className =
		'wc-block-components-text-input wc-block-components-address-form__billing-birthdate';

	return (
		<div className="custom-input">
			<label htmlFor="billing-birthdate">{ label }</label>
			<input
				type="date"
				className={ className }
				name="billing-birthdate"
				id="billing-birthdate"
				value={ value }
				onChange={ handleChange }
			/>
		</div>
	);
};

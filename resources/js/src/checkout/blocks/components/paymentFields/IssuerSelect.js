import { useCallback } from '@wordpress/element';

export const IssuerSelect = ( {
	issuerKey,
	issuers,
	selectedIssuer,
	updateIssuer,
} ) => {
    const handleChange = useCallback( ( e ) => {
        updateIssuer( e.target.value );
    }, [ updateIssuer ] );
	return (
		<select
			name={ issuerKey }
			dangerouslySetInnerHTML={ { __html: issuers } }
			value={ selectedIssuer }
			onChange={ handleChange }
		/>
	);
};

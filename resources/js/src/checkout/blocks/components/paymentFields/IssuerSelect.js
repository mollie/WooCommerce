export const IssuerSelect = ( {
	issuerKey,
	issuers,
	selectedIssuer,
	updateIssuer,
} ) => {
	const handleChange = ( e ) => updateIssuer( e.target.value );

	return (
		<select
			name={ issuerKey }
			dangerouslySetInnerHTML={ { __html: issuers } }
			value={ selectedIssuer }
			onChange={ handleChange }
		/>
	);
};

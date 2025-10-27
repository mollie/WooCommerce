export function createAppleErrors( errors ) {
	const errorList = [];
	for ( const error of errors ) {
		const { contactField = null, code = null, message = null } = error;
		const appleError = contactField
			? new ApplePayError( code, contactField, message )
			: new ApplePayError( code );
		errorList.push( appleError );
	}

	return errorList;
}

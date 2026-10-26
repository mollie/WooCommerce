/**
 * Posts to the store's express routes. Sends only the nonce: the store reads the cart itself.
 * apiFetch keeps a logged-in shopper logged in. Always answers an object, never throws:
 *   { ok: true, data }
 *   { ok: false, code, message }  code 'network' when the store was not reached
 */
/**
 * WordPress dependencies
 */
import apiFetch from '@wordpress/api-fetch';

export const SESSION_ROUTE = 'express/session';
export const ORDER_ROUTE = 'express/order';

/**
 * @param {{restUrl: string, nonce: string}} data  The localized mollieExpressData.
 * @param {string}                           route SESSION_ROUTE or ORDER_ROUTE.
 * @return {Promise<Object>} The answer, as described above.
 */
export async function postToStore( { restUrl, nonce }, route ) {
	let response;
	try {
		response = await apiFetch( {
			url: restUrl + route,
			method: 'POST',
			data: { nonce },
			parse: false,
		} );
	} catch ( thrown ) {
		// apiFetch throws the Response of a non-2xx answer, and an Error when the store was not reached.
		response = thrown;
	}
	if ( ! response || typeof response.json !== 'function' ) {
		return { ok: false, code: 'network', message: '' };
	}

	let body = null;
	try {
		body = await response.json();
	} catch ( e ) {
		body = null;
	}
	if ( response.ok && body && body.ok !== false ) {
		return { ok: true, data: body };
	}

	return {
		ok: false,
		code: body?.code ?? `http_${ response.status }`,
		message: typeof body?.message === 'string' ? body.message : '',
	};
}

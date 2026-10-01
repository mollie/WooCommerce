/**
 * The one place that talks to the store's two express routes.
 *
 * It sends the nonce and nothing else: the store reads the cart, the prices and the addresses
 * itself (REQ-B4). apiFetch is used for its REST nonce middleware, which keeps a logged-in shopper
 * logged in on the REST request. The answer is always an object, never a throw:
 *   { ok: true, data }            the route answered 2xx and did not say ok: false
 *   { ok: false, code, message }  a refusal with the store's code and shopper-facing message, or
 *                                 code 'network' and an empty message when the store was not reached
 *
 * Written so the Apple Pay and PayPal buttons can adopt it later.
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

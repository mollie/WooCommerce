/**
 * While ready it remounts with a new session when the price settles on a new value. A session
 * about to expire is replaced once; after that the component is idle until the shopper is back.
 * A failure shows nothing and leaves the normal payment methods alone.
 */
/**
 * WordPress dependencies
 */
import { useEffect, useRef, useState } from '@wordpress/element';
import { dispatch, select as selectStore, useSelect } from '@wordpress/data';
/**
 * Internal dependencies
 */
import { expressReadiness } from './expressReadiness';
import { MollieCheckoutManager } from './MollieCheckoutManager';
import { ORDER_ROUTE, SESSION_ROUTE, postToStore } from './expressTransport';

const CART_STORE = 'wc/store/cart';
const PAYMENT_STORE = 'wc/store/payment';
/** The price the store opened no session for: Blocks drops the express area until it changes. */
let refusedFingerprint = null;
const SETTLE_MS = 1000;
/** A session is replaced this long before it expires. */
const EXPIRY_MARGIN_MS = 30000;
const SHOPPER_IS_BACK = [ 'pointermove', 'pointerdown', 'keydown', 'focus' ];
/** Refusals after which one fresh session is tried. */
const REMOUNT_AFTER = [
	'cart_changed',
	'session_expired',
	'session_missing',
	'session_replaced',
];
/** WordPress's refusal when the shopper logged in in another tab: its own text is not for shoppers. */
const LOGIN_CHANGED = 'rest_cookie_invalid_nonce';
const HEIGHT = '48px';
const isSubscription = ( item ) =>
	Boolean( item?.extensions?.subscriptions?.billing_period );

/**
 * Whether the cart can be paid this way at all; Blocks hides the express area otherwise. Not for
 * the price the store opened no session for. The shipping form never decides: the wallet gives the
 * address. Blocks asks again when the cart store changes.
 *
 * @param {Object} args Blocks' canMakePayment argument.
 * @return {boolean} Whether to offer the method.
 */
export function canMakePayment( args ) {
	const items = args?.cart?.cartItems ?? [];
	if (
		! MollieCheckoutManager.isAvailable() ||
		items.length === 0 ||
		items.some( isSubscription )
	) {
		return false;
	}
	const cart = readCart( selectStore );
	return (
		refusedFingerprint === null || cart.fingerprint !== refusedFingerprint
	);
}

/**
 * The state and the price, as primitives, so the component re-renders only when one changes.
 *
 * @param {Function} select @wordpress/data select.
 * @return {{status: string, fingerprint: string}} Readiness and price.
 */
function readCart( select ) {
	const store = select( CART_STORE );
	const cart = store.getCartData();
	const totals = store.getCartTotals();
	// The session is priced without shipping, and Mollie adds the option the shopper picks in the wallet:
	// what PricingFingerprint::of() hashes server-side. The rate and the address never reprice it.
	const withoutShipping =
		Number( totals.total_price ) -
		Number( totals.total_shipping ?? 0 ) -
		Number( totals.total_shipping_tax ?? 0 );
	const { status } = expressReadiness( {
		itemCount: cart.items?.length ?? 0,
		hasSubscription: ( cart.items ?? [] ).some( isSubscription ),
		nothingToPay: withoutShipping === 0,
	} );

	return {
		status,
		fingerprint: [
			totals.currency_code,
			withoutShipping,
			...( cart.items ?? [] ).map(
				( item ) => `${ item.key }:${ item.quantity }`
			),
		].join( '|' ),
	};
}

/**
 * The live value, once it has held for SETTLE_MS. The first value counts at once.
 *
 * @param {string} status      Readiness.
 * @param {string} fingerprint Price.
 * @return {{status: string, fingerprint: string}} The settled value.
 */
function useSettled( status, fingerprint ) {
	const [ settled, setSettled ] = useState( { status, fingerprint } );
	useEffect( () => {
		if (
			settled.status === status &&
			settled.fingerprint === fingerprint
		) {
			return undefined;
		}
		const timer = setTimeout(
			() => setSettled( { status, fingerprint } ),
			SETTLE_MS
		);
		return () => clearTimeout( timer );
	}, [ status, fingerprint, settled.status, settled.fingerprint ] );

	return settled;
}

export default function MollieExpressComponent( {
	data,
	onClick,
	onClose,
	onError,
} ) {
	const live = useSelect( ( select ) => readCart( select ), [] );
	const settled = useSettled( live.status, live.fingerprint );
	const [ failedAt, setFailedAt ] = useState( null );
	const [ refusedShippingAt, setRefusedShippingAt ] = useState( null );
	const [ attempt, setAttempt ] = useState( 0 );
	const [ idle, setIdle ] = useState( false );
	const [ notice, setNotice ] = useState( '' );
	const mountRef = useRef( null );
	const interacted = useRef( false );
	const sessionIsAReplacement = useRef( false );
	const mountedToken = useRef( '' );
	// The submit handler outlives renders: it reads the latest props and price from here.
	const latest = useRef( {} );
	latest.current = {
		data,
		onClick,
		onClose,
		onError,
		fingerprint: settled.fingerprint,
	};

	const managerRef = useRef( null );
	if ( managerRef.current === null ) {
		managerRef.current = new MollieCheckoutManager(
			{ locale: data.locale, buttons: data.buttons },
			( event ) => submit( event ) // eslint-disable-line no-use-before-define
		);
	}

	// The order route can still refuse a submit for the shipping form: not retried at this price.
	const blocked = refusedShippingAt === settled.fingerprint;
	const failed = failedAt === settled.fingerprint;
	const ready = settled.status === 'ready' && ! blocked && ! failed && ! idle;

	async function submit( event ) {
		// First and synchronously: without defer() Mollie creates the payment before this handler answers.
		event.defer();

		const current = latest.current;
		interacted.current = true;
		setNotice( '' );
		current.onClick?.();

		const answer = await postToStore( current.data, ORDER_ROUTE, {
			token: mountedToken.current,
		} );
		if ( answer.ok ) {
			// Nothing is passed: details given to resolve() would override the wallet's.
			event.resolve();
			return;
		}

		const message =
			answer.code === LOGIN_CHANGED
				? current.data.messages.reload
				: answer.message || current.data.messages.unavailable;
		event.reject( message );
		current.onClose?.();
		if ( answer.code === 'network' ) {
			current.onError?.( message );
		}
		if ( answer.code === 'shipping_incomplete' ) {
			setRefusedShippingAt( current.fingerprint );
			return;
		}
		setNotice( message );
		if ( REMOUNT_AFTER.includes( answer.code ) ) {
			setAttempt( ( value ) => value + 1 );
		}
	}

	useEffect( () => {
		const manager = managerRef.current;
		if ( ! ready ) {
			manager.unmount();
			return undefined;
		}

		let cancelled = false;
		let expiry = null;
		const fingerprint = settled.fingerprint;
		const fail = () => {
			if ( cancelled ) {
				return;
			}
			manager.unmount();
			setFailedAt( fingerprint );
			// Blocks keeps its frame while canMakePayment holds and asks only when the cart changes.
			refusedFingerprint = fingerprint;
			dispatch(
				PAYMENT_STORE
			).__internalUpdateAvailablePaymentMethods?.();
			if ( interacted.current ) {
				latest.current.onError?.(
					latest.current.data.messages.unavailable
				);
			}
		};

		( async () => {
			if ( ! MollieCheckoutManager.isAvailable() ) {
				fail();
				return;
			}
			const session = await postToStore(
				latest.current.data,
				SESSION_ROUTE
			);
			if ( cancelled ) {
				return;
			}
			if ( ! session.ok || ! session.data.clientAccessToken ) {
				fail();
				return;
			}
			try {
				const mounted = await manager.mount(
					session.data.clientAccessToken,
					mountRef.current
				);
				if ( ! mounted || cancelled ) {
					return;
				}
				mountedToken.current = session.data.clientAccessToken;
			} catch ( e ) {
				fail();
				return;
			}
			const expiresIn =
				Date.parse( session.data.expiresAt ) -
				Date.now() -
				EXPIRY_MARGIN_MS;
			if ( expiresIn > 0 ) {
				expiry = setTimeout( () => {
					if ( sessionIsAReplacement.current ) {
						setIdle( true );
						return;
					}
					sessionIsAReplacement.current = true;
					setAttempt( ( value ) => value + 1 );
				}, expiresIn );
			}
		} )();

		return () => {
			cancelled = true;
			clearTimeout( expiry );
			manager.unmount();
		};
	}, [ ready, settled.fingerprint, attempt ] );

	useEffect( () => {
		sessionIsAReplacement.current = false;
		setIdle( false );
	}, [ settled.fingerprint ] );

	useEffect( () => {
		if ( ! idle ) {
			return undefined;
		}
		const wake = () => {
			sessionIsAReplacement.current = false;
			setIdle( false );
		};
		SHOPPER_IS_BACK.forEach( ( name ) =>
			window.addEventListener( name, wake )
		);
		return () =>
			SHOPPER_IS_BACK.forEach( ( name ) =>
				window.removeEventListener( name, wake )
			);
	}, [ idle ] );

	useEffect( () => () => managerRef.current.unmount(), [] );

	if ( settled.status === 'hidden' || ( failed && ! blocked ) ) {
		return null;
	}

	if ( blocked ) {
		return null;
	}

	return (
		<div className="mollie-express-component">
			{ notice && (
				<p className="mollie-express-component__notice" role="alert">
					{ notice }
				</p>
			) }
			<div
				ref={ mountRef }
				className="mollie-express-component__mount"
				style={ { minHeight: HEIGHT } }
			/>
		</div>
	);
}

/**
 * The Express Component in the block checkout's express area.
 *
 * It shows one of three states, decided by expressReadiness over the cart store:
 *   hidden   nothing
 *   blocked  the shipping message and our own neutral placeholder; no session, no Mollie2
 *   ready    asks the store for a session and mounts Mollie's component with its token
 *
 * While ready it watches the price — the total, the currency and the selected rates — and when that
 * settles on a new value it remounts with a new session. A change must hold for SETTLE_MS before it
 * counts, so typing an address or a recalculation does not produce a request each. On Mollie's
 * submit it asks the store to start the order and resolves with the details the store holds, or
 * rejects with the store's message.
 *
 * A failure shows nothing and leaves the normal payment methods alone (NF-2); it is reported through
 * the block's onError only once the shopper has interacted. The token lives in the manager only.
 */
/**
 * WordPress dependencies
 */
import { useEffect, useRef, useState } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
/**
 * External dependencies
 */
import { getSetting } from '@woocommerce/settings';
/**
 * Internal dependencies
 */
import { expressReadiness } from './expressReadiness';
import { MollieCheckoutManager } from './MollieCheckoutManager';
import { ORDER_ROUTE, SESSION_ROUTE, postToStore } from './expressTransport';

const CART_STORE = 'wc/store/cart';
const SETTLE_MS = 1000;
/** A session is replaced this long before it expires (the store reuses one only with 60s left). */
const EXPIRY_MARGIN_MS = 30000;
/** Refusals after which the session no longer fits the checkout: one fresh session, then give up. */
const REMOUNT_AFTER = [ 'cart_changed', 'session_expired', 'session_missing' ];
const ADDRESS_FIELDS = [
	'first_name',
	'last_name',
	'company',
	'address_1',
	'address_2',
	'country',
	'city',
	'state',
	'postcode',
	'phone',
];
const HEIGHT = '48px';

/**
 * The shipping fields WooCommerce requires for a country: the checkout's default fields, as the
 * country's locale overrides them.
 *
 * @param {string} country Country code.
 * @return {string[]} Field names.
 */
export function requiredShippingFields( country ) {
	const defaults = getSetting( 'defaultFields', {} );
	const locale = getSetting( 'countryData', {} )?.[ country ]?.locale ?? {};

	return ADDRESS_FIELDS.filter( ( field ) => {
		const merged = {
			...( defaults[ field ] ?? {} ),
			...( locale[ field ] ?? {} ),
		};
		return merged.required === true && merged.hidden !== true;
	} );
}

const isSubscription = ( item ) =>
	Boolean( item?.extensions?.subscriptions?.billing_period );

/**
 * Whether the cart can be paid this way at all; Blocks hides the express area otherwise.
 *
 * @param {Object} args Blocks' canMakePayment argument.
 * @return {boolean} Whether to offer the method.
 */
export function canMakePayment( args ) {
	const items = args?.cart?.cartItems ?? [];
	return (
		MollieCheckoutManager.isAvailable() &&
		items.length > 0 &&
		! items.some( isSubscription )
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
	const address = store.getCustomerData()?.shippingAddress ?? {};
	const packages = store.getShippingRates() ?? [];
	const selectedRates = packages.map(
		( pack ) =>
			`${ pack.package_id }:${
				( pack.shipping_rates ?? [] ).find( ( rate ) => rate.selected )
					?.rate_id ?? ''
			}`
	);
	const { status } = expressReadiness( {
		itemCount: cart.items?.length ?? 0,
		hasSubscription: ( cart.items ?? [] ).some( isSubscription ),
		needsShipping: store.getNeedsShipping(),
		shippingAddress: address,
		requiredShippingFields: requiredShippingFields( address.country ),
		hasSelectedRate:
			packages.length > 0 &&
			packages.every( ( pack ) =>
				( pack.shipping_rates ?? [] ).some( ( rate ) => rate.selected )
			),
		// A cost the store has actually worked out for this address, free shipping included.
		hasShippingAmount: totals.total_shipping !== undefined && totals.total_shipping !== null,
		isCalculating:
			store.isCustomerDataUpdating() ||
			store.isShippingRateBeingSelected() ||
			Boolean( store.isAddressFieldsForShippingRatesUpdating?.() ),
	} );

	return {
		status,
		// Everything PricingFingerprint::of() hashes server-side, and nothing more. The destination
		// counts only while something ships — a new city with the same flat rate still reprices the
		// session as far as the store is concerned — and the lines count for a swap of two equally
		// priced items. For a cart with nothing to ship the store ignores the address, because
		// WooCommerce fills the shipping fields from the billing ones behind the page's back.
		fingerprint: [
			totals.currency_code,
			totals.total_price,
			...selectedRates,
			...( store.getNeedsShipping()
				? [
						address.country ?? '',
						address.state ?? '',
						address.postcode ?? '',
						address.city ?? '',
				  ]
				: [] ),
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

function focusShippingAddress() {
	const field = document.querySelector(
		'#shipping-fields input, #shipping-fields select, .wc-block-checkout__shipping-fields input'
	);
	if ( field ) {
		field.scrollIntoView( { block: 'center' } );
		field.focus();
	}
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
	const [ notice, setNotice ] = useState( '' );
	const mountRef = useRef( null );
	const interacted = useRef( false );
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

	const blocked =
		settled.status === 'blocked' ||
		refusedShippingAt === settled.fingerprint;
	const failed = failedAt === settled.fingerprint;
	const ready = settled.status === 'ready' && ! blocked && ! failed;

	async function submit( event ) {
		// First, synchronously: without defer() Mollie does not wait for this handler and creates
		// the payment straight away, so a later reject() would arrive after the shopper has paid.
		event.defer();

		const current = latest.current;
		interacted.current = true;
		setNotice( '' );
		current.onClick?.();

		const answer = await postToStore( current.data, ORDER_ROUTE );
		if ( answer.ok ) {
			// Nothing is passed on purpose: details handed to resolve() override what the wallet
			// collected, and the wallet's contact and billing address are the ones that count
			event.resolve();
			return;
		}

		const message = answer.message || current.data.messages.unavailable;
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
			} catch ( e ) {
				fail();
				return;
			}
			const expiresIn =
				Date.parse( session.data.expiresAt ) -
				Date.now() -
				EXPIRY_MARGIN_MS;
			if ( expiresIn > 0 ) {
				// Replaced once; if the replacement fails, fail() gives up quietly.
				expiry = setTimeout(
					() => setAttempt( ( value ) => value + 1 ),
					expiresIn
				);
			}
		} )();

		return () => {
			cancelled = true;
			clearTimeout( expiry );
			manager.unmount();
		};
	}, [ ready, settled.fingerprint, attempt ] );

	useEffect( () => () => managerRef.current.unmount(), [] );

	if ( settled.status === 'hidden' || ( failed && ! blocked ) ) {
		return null;
	}

	if ( blocked ) {
		return (
			<div className="mollie-express-component">
				<p className="mollie-express-component__message">
					{ data.messages.shippingIncomplete }
				</p>
				<button
					type="button"
					className="mollie-express-placeholder"
					aria-disabled="true"
					onClick={ focusShippingAddress }
					style={ {
						width: '100%',
						minHeight: HEIGHT,
						border: '1px dashed currentColor',
						borderRadius: '4px',
						background: 'transparent',
						opacity: 0.6,
						cursor: 'not-allowed',
					} }
				>
					{ data.messages.waitingForShipping }
				</button>
			</div>
		);
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

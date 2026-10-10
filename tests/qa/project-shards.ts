/**
 * External dependencies
 */
import type { Project } from '@playwright/test';
/**
 * Internal dependencies
 */
import type { TestBaseExtend } from './utils';
import { MollieSettings } from './resources';

/**
 * Types
 */
type ApiMethod = MollieSettings.ApiMethod;

type ShardProject = Project< TestBaseExtend >;

type MollieState = keyof typeof SETUP_STATES;

interface Shard {
	/** Shard name, appended after `shard:<api>-api:`. */
	shard: string;
	/** Mollie state the shard needs; resolves to its setup project. */
	state: MollieState;
	testMatch: RegExp;
	grep?: RegExp;
	grepInvert?: RegExp;
	/**
	 * Run tests of the shard in parallel (package.json runs such shards with
	 * --workers=4). Only for shards whose tests don't mutate shared store or
	 * Mollie settings: each test there is a guest checkout in its own browser
	 * context on the currency set by the shard setup.
	 */
	fullyParallel?: boolean;
	/** Run in multistep checkout mode (sets `use.isMultistepCheckout`). */
	multistep?: boolean;
	/** Skip under the Orders API (behaviour is API-method-agnostic). */
	paymentOnly?: boolean;
}

/**
 * Distinct environment states a shard can start from. Each composes the
 * checkout-layout task (02-woocommerce), optionally the multistep-checkout
 * task (04-multistep) and one Mollie state task (03-mollie).
 */
const SETUP_STATES = {
	'mollie:block': {
		testMatch: /(02-woocommerce|03-mollie)\.setup\.ts/,
		grep: /setup:checkout:block;|setup:mollie;/,
	},
	'mollie:classic': {
		testMatch: /(02-woocommerce|03-mollie)\.setup\.ts/,
		grep: /setup:checkout:classic;|setup:mollie;/,
	},
	// Layout is toggled by the spec itself (block and classic describes)
	'mollie:card-disabled': {
		testMatch: /03-mollie\.setup\.ts/,
		grep: /setup:mollie:card-disabled;/,
	},
	// Layout is toggled by the spec itself (nl-classic-checkout)
	'mollie:nl': {
		testMatch: /(02-woocommerce|03-mollie)\.setup\.ts/,
		grep: /setup:checkout:block;|setup:mollie:nl;/,
	},
	'mollie:classic:subscription': {
		testMatch: /(02-woocommerce|03-mollie)\.setup\.ts/,
		grep: /setup:checkout:classic;|setup:mollie:subscription;/,
	},
	'mollie:block:multistep': {
		testMatch: /(02-woocommerce|03-mollie|04-multistep)\.setup\.ts/,
		grep: /setup:checkout:block;|setup:multistep:checkout;|setup:mollie;/,
	},
	'mollie:classic:multistep': {
		testMatch: /(02-woocommerce|03-mollie|04-multistep)\.setup\.ts/,
		grep: /setup:checkout:classic;|setup:multistep:checkout;|setup:mollie;/,
	},
	'mollie:card-disabled:multistep': {
		testMatch: /(03-mollie|04-multistep)\.setup\.ts/,
		grep: /setup:multistep:checkout;|setup:mollie:card-disabled;/,
	},
} as const;

// Multistep runs the transaction specs with isMultistepCheckout, which drives
// the extra checkout screens. Pay for order has no multistep variant.
const multistepFilter = {
	grep: /Transaction/,
	grepInvert: /Transaction - Pay for order/,
	multistep: true,
};

const shards: Shard[] = [
	// --- Admin (no webhooks, see ngrok-less shards in playwright.yml) ---
	{
		shard: 'plugin-foundation',
		state: 'mollie:block',
		testMatch: /01-plugin-foundation\/.*\.spec\.ts/,
		paymentOnly: true,
	},
	{
		shard: 'merchant-setup',
		state: 'mollie:block',
		testMatch: /02-merchant-setup\/.*\.spec\.ts/,
		paymentOnly: true,
	},
	{
		shard: 'plugin-settings',
		state: 'mollie:classic',
		testMatch: /03-plugin-settings\/mollie-settings-.*\.spec\.ts/,
		paymentOnly: true,
	},
	// Surcharge, one shard per fee type (each with its plain, under limit and
	// over limit describes). Every test rewrites store country/currency and
	// gateway fees, so tests within a shard run sequentially.
	...(
		[
			[ 'no-fee', /Surcharge fee > No fee/ ],
			[ 'fixed', /Surcharge fee > Fixed(?! and percentage)/ ],
			[ 'percentage', /Surcharge fee > Percentage/ ],
			[ 'fixed-and-percentage', /Surcharge fee > Fixed and percentage/ ],
		] as const
	).map( ( [ feeType, grep ] ): Shard => ( {
		shard: `surcharge:${ feeType }`,
		state: 'mollie:classic',
		testMatch: /03-plugin-settings\/surcharge\/surcharge\.spec\.ts/,
		grep,
		paymentOnly: true,
	} ) ),

	// --- Frontend ---
	{
		shard: 'frontend-ui',
		state: 'mollie:classic',
		testMatch: /04-frontend-ui\/frontend-ui\.spec\.ts/,
	},
	{
		shard: 'phone-validation',
		state: 'mollie:block',
		testMatch: /04-frontend-ui\/phone-validation\.spec\.ts/,
		fullyParallel: true,
	},

	// --- Transactions ---
	{
		shard: 'transaction:eur-block',
		state: 'mollie:block',
		testMatch:
			/(05-transaction\/eur-block|09-payment-status-transition\/eur-block-transition)\.spec\.ts/,
		fullyParallel: true,
	},
	{
		shard: 'transaction:eur-classic',
		state: 'mollie:classic',
		testMatch: /05-transaction\/eur-classic\.spec\.ts/,
		fullyParallel: true,
	},
	// Each test switches the store currency
	{
		shard: 'transaction:non-eur-block',
		state: 'mollie:block',
		testMatch: /05-transaction\/non-eur-block\.spec\.ts/,
	},
	{
		shard: 'transaction:non-eur-classic',
		state: 'mollie:classic',
		testMatch: /05-transaction\/non-eur-classic\.spec\.ts/,
	},
	{
		shard: 'transaction:card-disabled',
		state: 'mollie:card-disabled',
		testMatch:
			/05-transaction\/eur-credit-card-disabled-mollie-components\.spec\.ts/,
	},
	// Each describe toggles PayPal Express button settings
	{
		shard: 'transaction:paypal-express',
		state: 'mollie:block',
		testMatch: /05-transaction\/paypal-express\.spec\.ts/,
	},
	// Billink needs an NL merchant profile
	{
		shard: 'transaction:nl',
		state: 'mollie:nl',
		testMatch: /05-transaction\/nl-.*\.spec\.ts/,
		paymentOnly: true,
	},

	// --- Refund ---
	{
		shard: 'refund',
		state: 'mollie:block',
		testMatch: /06-refund\/.*\.spec\.ts/,
		fullyParallel: true,
	},

	// --- Subscription (shared registered customer) ---
	{
		shard: 'subscription',
		state: 'mollie:classic:subscription',
		testMatch: /07-subscription\/.*\.spec\.ts/,
	},

	// --- Bug verification (probe plugin, shared product stock) ---
	{
		shard: 'bug-verification',
		state: 'mollie:block',
		testMatch: /08-bug-verification\/.*\.spec\.ts/,
	},

	// --- Multistep ---
	{
		shard: 'multistep:eur-block',
		state: 'mollie:block:multistep',
		testMatch:
			/(05-transaction\/eur-block|09-payment-status-transition\/eur-block-transition)\.spec\.ts/,
		fullyParallel: true,
		...multistepFilter,
	},
	{
		shard: 'multistep:eur-classic',
		state: 'mollie:classic:multistep',
		testMatch: /05-transaction\/eur-classic\.spec\.ts/,
		fullyParallel: true,
		...multistepFilter,
	},
	{
		shard: 'multistep:non-eur-block',
		state: 'mollie:block:multistep',
		testMatch: /05-transaction\/non-eur-block\.spec\.ts/,
		...multistepFilter,
	},
	{
		shard: 'multistep:non-eur-classic',
		state: 'mollie:classic:multistep',
		testMatch: /05-transaction\/non-eur-classic\.spec\.ts/,
		...multistepFilter,
	},
	{
		shard: 'multistep:card-disabled',
		state: 'mollie:card-disabled:multistep',
		testMatch:
			/05-transaction\/eur-credit-card-disabled-mollie-components\.spec\.ts/,
		...multistepFilter,
	},
	{
		shard: 'multistep:paypal-express',
		state: 'mollie:block:multistep',
		testMatch: /05-transaction\/paypal-express\.spec\.ts/,
		...multistepFilter,
	},
];

/**
 * Shards that run for the given API method. Shards flagged `paymentOnly` are
 * unaffected by the Mollie API method, so they run only under the default
 * payment API instead of being duplicated across both methods.
 */
function shardsFor( api: ApiMethod ): Shard[] {
	return shards.filter( ( s ) => api === 'payment' || ! s.paymentOnly );
}

/**
 * Builds the per-state setup projects for a given Mollie API method, limited to
 * the states actually consumed by that API's shards. Each is a thin project
 * that greps the relevant setup task(s) and runs after the base
 * `setup-woocommerce`. The `mollieApiMethod` on the project drives how the
 * Mollie setup task configures the plugin (Payments vs Orders API).
 */
export function buildSetupProjects( api: ApiMethod ): ShardProject[] {
	const usedStates = new Set( shardsFor( api ).map( ( s ) => s.state ) );

	return ( Object.keys( SETUP_STATES ) as MollieState[] )
		.filter( ( state ) => usedStates.has( state ) )
		.map( ( state ) => {
			const { testMatch, grep } = SETUP_STATES[ state ];

			// workers: 1 - composed tasks live in separate files, which would
			// otherwise run concurrently (e.g. racing plugin activations).
			const project: ShardProject = {
				name: `setup:${ api }-api:${ state }`,
				testMatch,
				grep,
				dependencies: [ 'setup-woocommerce' ],
				fullyParallel: false,
				workers: 1,
			};

			if ( api === 'order' ) {
				project.use = { mollieApiMethod: 'order' };
			}

			return project;
		} );
}

/**
 * Builds the project shards for a given Mollie API method. Each shard depends
 * on its matching per-state setup project; order-api shards run against the
 * Orders API and multistep shards run in multistep checkout mode.
 */
export function buildShards( api: ApiMethod ): ShardProject[] {
	return shardsFor( api ).map(
		( { shard, state, multistep, paymentOnly, fullyParallel, ...rest } ) => {
			// Sequential shards are capped at one worker, so their spec files
			// don't run concurrently either.
			const project: ShardProject = {
				name: `shard:${ api }-api:${ shard }`,
				dependencies: [ `setup:${ api }-api:${ state }` ],
				fullyParallel: !! fullyParallel,
				...( fullyParallel ? {} : { workers: 1 } ),
				...rest,
			};

			if ( api === 'order' || multistep ) {
				project.use = {
					...( api === 'order' ? { mollieApiMethod: 'order' } : {} ),
					...( multistep ? { isMultistepCheckout: true } : {} ),
				};
			}

			return project;
		}
	);
}
